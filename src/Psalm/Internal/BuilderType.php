<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\Psalm\Internal;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use Psalm\Codebase;
use Psalm\Context;
use Psalm\NodeTypeProvider;
use Psalm\Type\Atomic\TGenericObject;
use Psalm\Type\Union;

/**
 * The builder type a specification really produces.
 *
 * The core declares `when(): WhenBuilder<mixed>`, and it has no choice: the
 * method being specified is only known from the closure that makes the call.
 * With `mixed` the template parameter buys nothing, so `returns('oops')` on a
 * `: ?Book` method type-checks.
 *
 * Filling the parameter in is all this needs to do. `WhenBuilder<TReturn>`
 * already declares `returns(TReturn ...$values)` and
 * `answers(callable(Invocation): TReturn)`, so once the parameter is real,
 * Psalm checks both on its own — there is no rule of ours to get wrong.
 *
 * @internal
 */
final class BuilderType
{
    private function __construct() {}

    /**
     * @param list<Arg>        $arguments the specification call's arguments
     * @param non-empty-string $builder   the builder class to parameterise
     */
    public static function of(
        array $arguments,
        string $builder,
        Context $context,
        Codebase $codebase,
        NodeTypeProvider $types,
    ): ?Union {
        $closure = $arguments[0]->value ?? null;

        if ($closure === null) {
            return null;
        }

        $returnType = self::specifiedReturnType($closure, $context, $codebase, $types);

        if (!$returnType instanceof Union) {
            return null;
        }

        return new Union([new TGenericObject($builder, [$returnType])]);
    }

    /**
     * The declared return type of the one call the closure makes.
     *
     * Null whenever anything is not certain — a body that is not a single
     * call, a receiver whose type is unknown, a method with no declared
     * return. The core's `mixed` then stands, which costs a check and cannot
     * cause a false one.
     */
    private static function specifiedReturnType(
        Expr $closure,
        Context $context,
        Codebase $codebase,
        NodeTypeProvider $types,
    ): ?Union {
        $call = ClosureShape::singleCall($closure);

        if (!$call instanceof MethodCall || !$call->name instanceof Identifier) {
            return null;
        }

        foreach (ReceiverType::classesOf($call->var, $types, $context) as $class) {
            $identifier = $class . '::' . $call->name->toString();

            if (!$codebase->methodExists($identifier)) {
                continue;
            }

            $self = null;
            $declared = $codebase->getMethodReturnType($identifier, $self);

            if ($declared instanceof Union) {
                return $declared;
            }
        }

        return null;
    }
}
