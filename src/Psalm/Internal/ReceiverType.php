<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\Psalm\Internal;

use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Variable;
use Psalm\Context;
use Psalm\NodeTypeProvider;
use Psalm\Type\Atomic\TNamedObject;

/**
 * The classes a specified call can land on, read off its receiver.
 *
 * The receiver's type comes from the node type provider first, which knows
 * every expression Psalm has analysed: `$this->repository`, `$this->gate()`,
 * a variable alike. `$context->vars_in_scope` is the fallback and nothing
 * more — keyed by variable name, it can only ever answer for a plain
 * variable, and that was all the plugin read before 1.2.0.
 *
 * Every member of an intersection counts: a double made by
 * `Understudy::for(A::class, B::class)` is `A&B`, and the method being
 * specified may be declared on either.
 *
 * @internal
 */
final class ReceiverType
{
    private function __construct() {}

    /**
     * @return list<string> class names, in the order Psalm holds them
     */
    public static function classesOf(Expr $receiver, NodeTypeProvider $types, Context $context): array
    {
        $type = $types->getType($receiver);

        if (!$type instanceof \Psalm\Type\Union && $receiver instanceof Variable && \is_string($receiver->name)) {
            $type = $context->vars_in_scope['$' . $receiver->name] ?? null;
        }

        if ($type === null) {
            return [];
        }

        $classes = [];

        foreach ($type->getAtomicTypes() as $atomic) {
            if (!$atomic instanceof TNamedObject) {
                continue;
            }

            $classes[] = $atomic->value;

            foreach ($atomic->extra_types as $extra) {
                if ($extra instanceof TNamedObject) {
                    $classes[] = $extra->value;
                }
            }
        }

        return $classes;
    }
}
