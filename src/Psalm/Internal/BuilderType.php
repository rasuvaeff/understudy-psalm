<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\Psalm\Internal;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Return_;
use Psalm\Codebase;
use Psalm\Context;
use Psalm\Type;
use Psalm\Type\Atomic\TGenericObject;
use Psalm\Type\Atomic\TNamedObject;
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
    public static function of(array $arguments, string $builder, Context $context, Codebase $codebase): ?Union
    {
        $closure = $arguments[0]->value ?? null;

        if ($closure === null) {
            return null;
        }

        $returnType = self::specifiedReturnType($closure, $context, $codebase);

        if (!$returnType instanceof \Psalm\Type\Union) {
            return null;
        }

        return new Union([new TGenericObject($builder, [$returnType])]);
    }

    /**
     * The declared return type of the one call the closure makes.
     *
     * The receiver has to be a plain variable, and that is a limit rather
     * than a preference: `$context->vars_in_scope` is keyed by variable name,
     * so a double reached through `$this->repository` or the result of a call
     * has no entry to look up. Those specifications are left alone — the
     * plugin says less about them, which is the direction it fails in
     * everywhere else too.
     *
     * Null whenever anything is not certain — a body that is not a single
     * call, a receiver whose type is unknown, a method with no declared
     * return. The core's `mixed` then stands, which costs a check and cannot
     * cause a false one.
     */
    private static function specifiedReturnType(Expr $closure, Context $context, Codebase $codebase): ?Union
    {
        $call = self::singleCall($closure);

        if (!$call instanceof \PhpParser\Node\Expr\MethodCall || !$call->name instanceof Identifier) {
            return null;
        }

        if (!$call->var instanceof Expr\Variable || !\is_string($call->var->name)) {
            return null;
        }

        $receiver = $context->vars_in_scope['$' . $call->var->name] ?? null;

        if ($receiver === null) {
            return null;
        }

        foreach ($receiver->getAtomicTypes() as $atomic) {
            if (!$atomic instanceof TNamedObject) {
                continue;
            }

            $identifier = $atomic->value . '::' . $call->name->toString();

            if (!$codebase->methodExists($identifier)) {
                continue;
            }

            $self = null;
            $declared = $codebase->getMethodReturnType($identifier, $self);

            if ($declared instanceof \Psalm\Type\Union) {
                return $declared;
            }
        }

        return null;
    }

    private static function singleCall(Expr $closure): ?MethodCall
    {
        $body = match (true) {
            $closure instanceof ArrowFunction => $closure->expr,
            $closure instanceof Closure => self::onlyStatement($closure),
            default => null,
        };

        return $body instanceof MethodCall ? $body : null;
    }

    private static function onlyStatement(Closure $closure): ?Expr
    {
        if (\count($closure->stmts) !== 1) {
            return null;
        }

        $first = $closure->stmts[0];

        return match (true) {
            $first instanceof Expression => $first->expr,
            $first instanceof Return_ => $first->expr,
            default => null,
        };
    }
}
