<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\Psalm;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Stmt\Return_;
use Psalm\Codebase;
use Psalm\CodeLocation;
use Psalm\IssueBuffer;
use Psalm\Plugin\EventHandler\AfterExpressionAnalysisInterface;
use Psalm\Plugin\EventHandler\Event\AfterExpressionAnalysisEvent;
use Psalm\StatementsSource;
use Psalm\Type\Atomic\TNamedObject;
use Psalm\Type\Union;
use Rasuvaeff\Understudy\Psalm\Internal\Cardinality;
use Rasuvaeff\Understudy\Psalm\Internal\ClosureShape;
use Rasuvaeff\Understudy\Psalm\Internal\MatcherClass;
use Rasuvaeff\Understudy\Psalm\Internal\MatcherKind;
use Rasuvaeff\Understudy\Psalm\Internal\VerbNames;
use Rasuvaeff\Understudy\Psalm\Issue\UnderstudyMisuse;

/**
 * Says what the engine would say, before the test runs.
 *
 * Every rule here has a runtime counterpart — `InvalidCallSpecification` for
 * a closure that specifies nothing, an unsatisfiable `Cardinality`, an
 * expectation that can never match. Reporting them statically buys the one
 * thing runtime cannot: the mistake is visible without running the suite, and
 * a specification that can never match is exactly the mistake a green suite
 * hides.
 *
 * Silent whenever it is not sure. A false accusation costs more than a missed
 * one here, because the engine still catches what this misses.
 *
 * @internal
 */
final class SpecificationRules implements AfterExpressionAnalysisInterface
{
    #[\Override]
    public static function afterExpressionAnalysis(AfterExpressionAnalysisEvent $event): ?bool
    {
        $expression = $event->getExpr();
        $source = $event->getStatementsSource();

        // See SpecificationScope: a first-class callable carries no arguments
        // and reading them throws. `when(...)` is a closure over the verb, not
        // a specification, so there is nothing here to check either.
        if ($expression instanceof CallLike && $expression->isFirstClassCallable()) {
            return null;
        }

        if (($expression instanceof FuncCall || $expression instanceof StaticCall)
            && self::isSpecification($expression)
        ) {
            self::checkSpecification($expression, $event, $source);

            return null;
        }

        if ($expression instanceof MethodCall) {
            self::checkFluentCardinality($expression, $source);
        }

        return null;
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

    private static function isSpecification(Expr $expression): bool
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

    private static function checkSpecification(
        FuncCall|StaticCall $call,
        AfterExpressionAnalysisEvent $event,
        StatementsSource $source,
    ): void {
        $arguments = array_values($call->getArgs());

        // Every closure argument, not the first one: `verifySequence()` takes
        // a whole protocol, and a mistake in its third step is the same
        // mistake as in its first. The non-closure arguments of `verify()`
        // are the cardinality, checked below.
        $closures = array_values(array_filter(
            array_map(static fn(Arg $argument): Expr => $argument->value, $arguments),
            static fn(Expr $value): bool => $value instanceof Closure || $value instanceof ArrowFunction,
        ));

        if ($closures === []) {
            return;
        }

        foreach ($closures as $closure) {
            $problem = ClosureShape::of($closure)->problem();

            if ($problem !== null) {
                self::report($problem, $closure, $source);
            }
        }

        if (self::verbOf($call) === 'verify') {
            $problem = Cardinality::verifyProblem(self::namedLiterals($arguments));

            if ($problem !== null) {
                self::report($problem, $call, $source);
            }
        }

        foreach ($closures as $closure) {
            self::checkMatchers($closure, $event, $source);
        }
    }

    /**
     * `expect(...)->times($min, $max)` — the fluent form of the same claim.
     */
    private static function checkFluentCardinality(MethodCall $call, StatementsSource $source): void
    {
        if (!$call->name instanceof Identifier || strtolower($call->name->toString()) !== 'times') {
            return;
        }

        if (!self::isSpecificationChain($call->var)) {
            return;
        }

        [$minimum, $maximum] = self::timesBounds($call);
        $problem = Cardinality::timesProblem($minimum, $maximum);

        if ($problem !== null) {
            self::report($problem, $call, $source);
        }
    }

    /**
     * A matcher whose kind no argument of that parameter could ever have.
     */
    private static function checkMatchers(
        Expr $closure,
        AfterExpressionAnalysisEvent $event,
        StatementsSource $source,
    ): void {
        $body = match (true) {
            $closure instanceof ArrowFunction => $closure->expr,
            $closure instanceof Closure => self::firstExpression($closure),
            default => null,
        };

        if (!$body instanceof MethodCall || !$body->name instanceof Identifier) {
            return;
        }

        $parameters = self::parameterTypesOf($body, $event);

        if ($parameters === null) {
            return;
        }

        foreach (array_values($body->getArgs()) as $position => $argument) {
            $matcher = self::matcherName($argument->value);
            $parameter = $parameters[$position] ?? null;

            if ($matcher === null || $matcher === '' || !$parameter instanceof Union) {
                continue;
            }

            $problem = MatcherKind::problem($matcher, $parameter);

            if ($problem !== null) {
                self::report($problem, $argument->value, $source);
            }
        }
    }

    /**
     * The single expression a closure body is, when it is one.
     */
    private static function firstExpression(Closure $closure): ?Expr
    {
        $first = $closure->stmts[0] ?? null;

        if ($first instanceof \PhpParser\Node\Stmt\Expression) {
            return $first->expr;
        }

        return \count($closure->stmts) === 1 && $first instanceof Return_ ? $first->expr : null;
    }

    /**
     * The declared type of each parameter of the called method, or null when
     * the target cannot be resolved — an untyped variable, a method Psalm has
     * no storage for, anything the plugin should stay quiet about. A
     * parameter with no declared type is a null entry: there is a parameter
     * at that position, and nothing is known about it.
     *
     * The receiver has to be a plain variable, and that is a limit rather
     * than a preference: `$context->vars_in_scope` is keyed by variable name,
     * so a double reached through `$this->repository` or the result of a call
     * has no entry to look up. Those specifications are left alone — the
     * plugin says less about them, which is the direction it fails in
     * everywhere else too.
     *
     * @return list<Union|null>|null
     */
    private static function parameterTypesOf(MethodCall $call, AfterExpressionAnalysisEvent $event): ?array
    {
        $context = $event->getContext();
        $codebase = $event->getCodebase();

        if (!$call->var instanceof Expr\Variable || !\is_string($call->var->name)) {
            return null;
        }

        $type = $context->vars_in_scope['$' . $call->var->name] ?? null;

        if ($type === null || !$call->name instanceof Identifier) {
            return null;
        }

        foreach ($type->getAtomicTypes() as $atomic) {
            if (!$atomic instanceof TNamedObject) {
                continue;
            }

            $parameters = self::parametersOf($codebase, $atomic->value, $call->name->toString());

            if ($parameters !== null) {
                return $parameters;
            }
        }

        return null;
    }

    /**
     * Public API only, and that is a constraint rather than a preference:
     * `Psalm\Internal\MethodIdentifier` and `Methods::getStorage()` would
     * answer the same question and are marked internal, so a plugin built on
     * them is one Psalm release away from breaking. `Codebase` takes a plain
     * `Class::method` string for both.
     *
     * @return list<Union|null>|null
     */
    private static function parametersOf(Codebase $codebase, string $class, string $method): ?array
    {
        $identifier = $class . '::' . $method;

        if (!$codebase->methodExists($identifier)) {
            return null;
        }

        $parameters = [];

        foreach ($codebase->getMethodParams($identifier) as $parameter) {
            $parameters[] = $parameter->type;
        }

        return $parameters;
    }

    /**
     * The `Arg::` method name, when the expression is a matcher.
     *
     * Read through the resolver, like every other name this rule looks at. A
     * short-name comparison claimed anybody's class whose name ends in `Arg`
     * and lost our own the moment a file imported it under another name — a
     * false accusation and a missed check, both in the consumer's code.
     */
    private static function matcherName(Expr $expression): ?string
    {
        if (!$expression instanceof StaticCall || !$expression->class instanceof Name) {
            return null;
        }

        if (!MatcherClass::isOurs(self::resolvedName($expression->class))
            || !$expression->name instanceof Identifier
        ) {
            return null;
        }

        return $expression->name->toString();
    }

    /**
     * @param list<Arg> $arguments
     *
     * @return array<string, int|bool|null>
     */
    private static function namedLiterals(array $arguments): array
    {
        $literals = [];

        foreach ($arguments as $argument) {
            if (!$argument->name instanceof Identifier) {
                continue;
            }

            $value = $argument->value;
            $literals[$argument->name->toString()] = match (true) {
                $value instanceof Int_ => $value->value,
                $value instanceof \PhpParser\Node\Expr\ConstFetch
                    => match (strtolower($value->name->toString())) {
                        'true' => true,
                        'false' => false,
                        default => null,
                    },
                default => null,
            };
        }

        return $literals;
    }

    /**
     * Whether `->times()` sits anywhere on a specification chain, not only
     * directly on the verb.
     *
     * `expect(...)->returns('b')->times(5, 2)` is the spelling the engine's own
     * README recommends for a repeated call, and reading only the immediate
     * receiver saw a `MethodCall` there and gave up — so the check fired on
     * one of the four spellings people write.
     */
    private static function isSpecificationChain(Expr $expression): bool
    {
        while ($expression instanceof MethodCall || $expression instanceof NullsafeMethodCall) {
            $expression = $expression->var;
        }

        return self::isSpecification($expression);
    }

    /**
     * The bounds of `times()`, by name where the call names them.
     *
     * `times(maximum: 5, minimum: 1)` is valid and means one to five calls;
     * read positionally it says `(5, 1)` and correct code was reported as
     * impossible — which costs more than the missed report above, because
     * there is no way around it but removing the plugin.
     *
     * @return array{0: ?int, 1: ?int}
     */
    private static function timesBounds(MethodCall $call): array
    {
        $minimum = null;
        $maximum = null;
        $position = 0;

        foreach ($call->getArgs() as $argument) {
            if ($argument->name instanceof Identifier) {
                match (strtolower($argument->name->toString())) {
                    'minimum' => $minimum = self::literalInt($argument),
                    'maximum' => $maximum = self::literalInt($argument),
                    default => null,
                };

                continue;
            }

            match ($position) {
                0 => $minimum = self::literalInt($argument),
                1 => $maximum = self::literalInt($argument),
                default => null,
            };

            ++$position;
        }

        return [$minimum, $maximum];
    }

    private static function literalInt(?Arg $argument): ?int
    {
        $value = $argument?->value;

        return $value instanceof Int_ ? $value->value : null;
    }

    private static function verbOf(FuncCall|StaticCall $call): string
    {
        $name = $call instanceof FuncCall
            ? ($call->name instanceof Name ? $call->name->toString() : '')
            : ($call->name instanceof Identifier ? $call->name->toString() : '');

        $lower = strtolower($name);

        $separator = strrpos($lower, '\\');

        return $separator === false ? $lower : substr($lower, $separator + 1);
    }

    private static function report(string $problem, Expr $at, StatementsSource $source): void
    {
        IssueBuffer::maybeAdd(
            new UnderstudyMisuse(
                'This understudy specification cannot work: ' . $problem,
                new CodeLocation($source, $at),
            ),
            $source->getSuppressedIssues(),
        );
    }
}
