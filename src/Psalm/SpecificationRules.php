<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\Psalm;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\Int_;
use Psalm\Codebase;
use Psalm\CodeLocation;
use Psalm\IssueBuffer;
use Psalm\NodeTypeProvider;
use Psalm\Plugin\EventHandler\AfterExpressionAnalysisInterface;
use Psalm\Plugin\EventHandler\Event\AfterExpressionAnalysisEvent;
use Psalm\StatementsSource;
use Psalm\Storage\FunctionLikeParameter;
use Psalm\Type\Atomic\TGenericObject;
use Psalm\Type\Atomic\TNamedObject;
use Psalm\Type\Union;
use Rasuvaeff\Understudy\Psalm\Internal\Cardinality;
use Rasuvaeff\Understudy\Psalm\Internal\ClosureShape;
use Rasuvaeff\Understudy\Psalm\Internal\MatcherClass;
use Rasuvaeff\Understudy\Psalm\Internal\MatcherKind;
use Rasuvaeff\Understudy\Psalm\Internal\ReceiverType;
use Rasuvaeff\Understudy\Psalm\Internal\RestTail;
use Rasuvaeff\Understudy\Psalm\Internal\VerbNames;
use Rasuvaeff\Understudy\Psalm\Issue\UnderstudyMisuse;
use Rasuvaeff\Understudy\Psalm\Issue\UnderstudyRestSuggestion;

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

        if (VerbNames::shortVerb(self::writtenName($call)) === 'verify') {
            $problem = Cardinality::verifyProblem(Cardinality::namedLiterals($arguments));

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
     * A matcher whose kind no argument of that parameter could ever have,
     * and a tail of `Arg::any()` that `Arg::rest()` would say in one word.
     */
    private static function checkMatchers(
        Expr $closure,
        AfterExpressionAnalysisEvent $event,
        StatementsSource $source,
    ): void {
        $body = ClosureShape::singleCall($closure);

        if (!$body instanceof MethodCall || !$body->name instanceof Identifier) {
            return;
        }

        $parameters = self::parametersOfCall($body, $event);

        if ($parameters === null) {
            return;
        }

        self::checkRestTail($body, $parameters, $source);

        $codebase = $event->getCodebase();
        $types = $source->getNodeTypeProvider();

        foreach (array_values($body->getArgs()) as $position => $argument) {
            $parameter = ($parameters[$position] ?? null)?->type;

            if (!$parameter instanceof Union) {
                continue;
            }

            $problem = self::matcherProblem($argument->value, $parameter, $codebase, $types);

            if ($problem !== null) {
                self::report($problem, $argument->value, $source);
            }
        }
    }

    private static function matcherProblem(
        Expr $argument,
        Union $parameter,
        Codebase $codebase,
        NodeTypeProvider $types,
    ): ?string {
        $unrelated = static fn(string $one, string $other): bool => self::unrelatedClasses($codebase, $one, $other);

        $captured = self::capturedClass($argument, $types);

        if ($captured !== null) {
            return MatcherKind::instanceProblem('`capture()`', $captured, $parameter, $unrelated);
        }

        $matcher = self::matcherName($argument);

        if ($matcher === null || $matcher === '' || !$argument instanceof StaticCall) {
            return null;
        }

        if (strtolower($matcher) !== 'instanceof') {
            return MatcherKind::problem($matcher, $parameter);
        }

        $class = self::literalClass($argument->getArgs()[0]->value ?? null);

        return $class === null
            ? null
            : MatcherKind::instanceProblem('`Arg::' . $matcher . '()`', $class, $parameter, $unrelated);
    }

    /**
     * The class a captor's `capture()` holds instances of, read off the
     * receiver's resolved type: our `Captor` with a class for its template.
     * A namesake `capture()` (#18) or an untyped captor answers null.
     *
     * @return non-empty-string|null
     */
    private static function capturedClass(Expr $argument, NodeTypeProvider $types): ?string
    {
        if (!$argument instanceof MethodCall
            || !$argument->name instanceof Identifier
            || $argument->name->toLowerString() !== 'capture'
            || $argument->getArgs() !== []
        ) {
            return null;
        }

        $receiver = $types->getType($argument->var);

        if (!$receiver instanceof \Psalm\Type\Union || !$receiver->isSingle()) {
            return null;
        }

        $captor = $receiver->getSingleAtomic();

        if (!$captor instanceof TGenericObject
            || strtolower($captor->value) !== strtolower(\Rasuvaeff\Understudy\Captor::class)
        ) {
            return null;
        }

        $held = $captor->type_params[0] ?? null;

        if ($held === null || !$held->isSingle()) {
            return null;
        }

        $object = $held->getSingleAtomic();

        return $object instanceof TNamedObject && $object->extra_types === [] && $object->value !== ''
            ? $object->value
            : null;
    }

    /**
     * `Foo::class`, written out — the one spelling whose class is known
     * without guessing. `self::class`, `static::class` and a variable are
     * left alone.
     *
     * @return non-empty-string|null
     */
    private static function literalClass(?Expr $expression): ?string
    {
        if (!$expression instanceof ClassConstFetch
            || !$expression->class instanceof Name
            || !$expression->name instanceof Identifier
            || $expression->name->toLowerString() !== 'class'
            || $expression->class->isSpecialClassName()
        ) {
            return null;
        }

        $class = ltrim(self::resolvedName($expression->class), '\\');

        return $class === '' ? null : $class;
    }

    /**
     * Two classes neither of which extends the other — no object is an
     * instance of both. An interface or a name Psalm does not know is never
     * unrelated: the answer there is a maybe.
     */
    private static function unrelatedClasses(Codebase $codebase, string $one, string $other): bool
    {
        if (strtolower($one) === strtolower($other)) {
            return false;
        }

        foreach ([$one, $other] as $name) {
            if (!$codebase->classExists($name) || $codebase->interfaceExists($name)) {
                return false;
            }
        }

        return !$codebase->classExtendsOrImplements($one, $other)
            && !$codebase->classExtendsOrImplements($other, $one);
    }

    /**
     * The suggestion, on the first `Arg::any()` of the run. Reported apart
     * from `UnderstudyMisuse` and without its "cannot work" preamble, because
     * nothing here is broken.
     *
     * @param list<FunctionLikeParameter> $parameters
     */
    private static function checkRestTail(MethodCall $call, array $parameters, StatementsSource $source): void
    {
        $required = 0;
        $variadic = false;

        foreach ($parameters as $parameter) {
            $required += $parameter->is_optional ? 0 : 1;
            $variadic = $variadic || $parameter->is_variadic;
        }

        $arguments = array_values($call->getArgs());
        $tail = RestTail::of(
            $arguments,
            static fn(Expr $value): ?string => self::matcherName($value),
            $required,
            count($parameters),
            $variadic,
        );

        if ($tail === null) {
            return;
        }

        [$start, $length] = $tail;

        IssueBuffer::maybeAdd(
            new UnderstudyRestSuggestion(
                RestTail::message($length),
                new CodeLocation($source, $arguments[$start]->value),
            ),
            $source->getSuppressedIssues(),
        );
    }

    /**
     * The parameters of the called method, or null when the target cannot be
     * resolved — a receiver of unknown type, a method Psalm has no storage
     * for, anything the plugin should stay quiet about. A parameter with no
     * declared type has a null `type`: there is a parameter at that position,
     * and nothing is known about it.
     *
     * The receiver is any expression Psalm typed — `$this->repository` as
     * much as a variable; see `Internal\ReceiverType`.
     *
     * @return list<FunctionLikeParameter>|null
     */
    private static function parametersOfCall(MethodCall $call, AfterExpressionAnalysisEvent $event): ?array
    {
        if (!$call->name instanceof Identifier) {
            return null;
        }

        $codebase = $event->getCodebase();
        $classes = ReceiverType::classesOf(
            $call->var,
            $event->getStatementsSource()->getNodeTypeProvider(),
            $event->getContext(),
        );

        foreach ($classes as $class) {
            $parameters = self::parametersOf($codebase, $class, $call->name->toString());

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
     * @return list<FunctionLikeParameter>|null
     */
    private static function parametersOf(Codebase $codebase, string $class, string $method): ?array
    {
        $identifier = $class . '::' . $method;

        if (!$codebase->methodExists($identifier)) {
            return null;
        }

        return array_values($codebase->getMethodParams($identifier));
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

    /**
     * The verb as written — the resolver is not asked, because only the last
     * segment is read and an alias of the function is still that function.
     */
    private static function writtenName(FuncCall|StaticCall $call): string
    {
        return $call instanceof FuncCall
            ? ($call->name instanceof Name ? $call->name->toString() : '')
            : ($call->name instanceof Identifier ? $call->name->toString() : '');
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
