<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\Psalm;

use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use Psalm\Plugin\EventHandler\BeforeExpressionAnalysisInterface;
use Psalm\Plugin\EventHandler\Event\BeforeExpressionAnalysisEvent;
use Rasuvaeff\Understudy\Psalm\Internal\MatcherClass;
use Rasuvaeff\Understudy\Psalm\Internal\ScopeIndex;
use Rasuvaeff\Understudy\Psalm\Internal\VerbNames;

/**
 * Records where a specification call is, so a diagnostic raised inside one
 * can be told apart from the same diagnostic anywhere else.
 *
 * Recorded BEFORE the expression is analysed, and that is not a preference:
 * the argument issues this plugin has an opinion about are raised WHILE the
 * call is analysed, so `AfterFunctionCallAnalysis` and every other
 * after-hook is already too late to have seen them.
 *
 * @internal
 */
final class SpecificationScope implements BeforeExpressionAnalysisInterface
{
    private static ?ScopeIndex $index = null;

    private static ?ScopeIndex $restCalls = null;

    private static ?ScopeIndex $matcherCalls = null;

    #[\Override]
    public static function beforeExpressionAnalysis(BeforeExpressionAnalysisEvent $event): ?bool
    {
        $expression = $event->getExpr();

        // `foo(...)` is a closure, not a call. php-parser stores a
        // VariadicPlaceholder where the arguments would be and asserts against
        // reading them, so `getArgs()` below is an AssertionError under
        // `zend.assertions=1` and a read of a missing property under `-1` —
        // and a throw from a `before` hook takes the whole Psalm run down,
        // whatever the file was about. Nothing here can apply to a callable
        // anyway: it specifies nothing and passes no matcher.
        if ($expression instanceof CallLike && $expression->isFirstClassCallable()) {
            return null;
        }

        if (self::endsWithRest($expression)) {
            // Recorded for every call, specification or not: at this point
            // the enclosing `when()` may not have been recorded yet, and the
            // suppression hook requires BOTH indexes to agree, so a real call
            // ending in `Arg::rest()` keeps its arity report either way.
            self::restCalls()->record(
                $event->getStatementsSource()->getFilePath(),
                $expression->getStartFilePos(),
                $expression->getEndFilePos(),
            );
        }

        if (self::isMatcherCall($expression)) {
            // File offsets, not lines: two `Arg::` calls — one ours, one a
            // namesake — can share a line, and a line-grained answer would
            // silence both.
            self::matcherCalls()->record(
                $event->getStatementsSource()->getFilePath(),
                $expression->getStartFilePos(),
                $expression->getEndFilePos(),
            );
        }

        if (!self::isSpecificationCall($expression)) {
            return null;
        }

        self::index()->record(
            $event->getStatementsSource()->getFilePath(),
            $expression->getStartFilePos(),
            $expression->getEndFilePos(),
        );

        return null;
    }

    public static function index(): ScopeIndex
    {
        return self::$index ??= new ScopeIndex();
    }

    /**
     * Where the calls whose last written argument is `Arg::rest()` are — the
     * one matcher that makes passing fewer arguments than the contract
     * declares legitimate, so `TooFewArguments` inside a specification stops
     * being a report about a mistake.
     */
    public static function restCalls(): ScopeIndex
    {
        return self::$restCalls ??= new ScopeIndex();
    }

    /**
     * Where the matcher calls of each file are, in FILE OFFSETS rather than
     * line numbers — the unit the other two indexes do not use.
     *
     * This is what lets the issue hook answer "is the thing complained about
     * one of our matchers" by resolution instead of by how it was spelled: a
     * `StaticCall` is recorded only when the resolver says its class is ours,
     * so a namesake keeps its diagnostics and an alias of ours loses the ones
     * it should never have had.
     */
    public static function matcherCalls(): ScopeIndex
    {
        return self::$matcherCalls ??= new ScopeIndex();
    }

    /**
     * Forgets every recorded range. A real run analyses each file once; this
     * is for tests driving the handlers in sequence.
     */
    public static function reset(): void
    {
        self::$index = null;
        self::$restCalls = null;
        self::$matcherCalls = null;
    }

    /**
     * Whether a call's last written argument is `Arg::rest()` — resolved, so
     * somebody else's `Arg` does not enable the tolerance, and ours does under
     * any alias.
     */
    private static function endsWithRest(object $expression): bool
    {
        if (
            !$expression instanceof FuncCall
            && !$expression instanceof MethodCall
            && !$expression instanceof NullsafeMethodCall
            && !$expression instanceof StaticCall
        ) {
            return false;
        }

        $arguments = $expression->getArgs();

        if ($arguments === []) {
            return false;
        }

        $last = $arguments[count($arguments) - 1]->value;

        return $last instanceof StaticCall
            && $last->class instanceof Name
            && $last->name instanceof Identifier
            && strtolower($last->name->toString()) === 'rest'
            && MatcherClass::isOurs(self::resolvedName($last->class));
    }

    /**
     * The name as Psalm resolved it, falling back to what was written.
     *
     * `use Rasuvaeff\\Understudy\\Understudy;` then `Understudy::when()` parses
     * as the bare name `Understudy`, and only the resolver knows which class
     * that is. Reading the written name alone would silently skip the static
     * form — which is the form Pest users are told to reach for, because Pest
     * owns the global `expect()`.
     */
    private static function resolvedName(Name $name): string
    {
        // Read out of the attribute bag rather than through getAttribute(),
        // which is declared `mixed`: assigning that needs a `@var` annotation
        // to satisfy psalm, and rector then removes the annotation as
        // useless. An array offset narrows on its own, so neither gate has an
        // opinion.
        $attributes = $name->getAttributes();

        return isset($attributes['resolvedName']) && \is_string($attributes['resolvedName'])
            ? $attributes['resolvedName']
            : $name->toString();
    }

    /**
     * Whether this expression IS a matcher: a static call on the `Arg` the
     * resolver says is ours, excluding the captor factory.
     *
     * A captor's `capture()` is a matcher too, and it is deliberately not
     * recognised here: the name of a method says nothing about the class it
     * belongs to, and this hook is handed no resolved receiver. Those are
     * recorded by {@see CaptorRecorder}, from a place where Psalm has already
     * resolved one.
     */
    private static function isMatcherCall(object $expression): bool
    {
        return $expression instanceof StaticCall
            && $expression->class instanceof Name
            && $expression->name instanceof Identifier
            && strtolower($expression->name->toString()) !== 'captor'
            && MatcherClass::isOurs(self::resolvedName($expression->class));
    }

    private static function isSpecificationCall(object $expression): bool
    {
        if ($expression instanceof FuncCall) {
            return $expression->name instanceof Name
                && VerbNames::isFunction(self::resolvedName($expression->name));
        }

        return $expression instanceof StaticCall
            && $expression->class instanceof Name
            && $expression->name instanceof Identifier
            && VerbNames::isStaticCall(self::resolvedName($expression->class), $expression->name->toString());
    }
}
