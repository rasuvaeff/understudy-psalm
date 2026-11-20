<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\Psalm\Internal;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use Psalm\Codebase;
use Psalm\Type\Atomic\TNamedObject;
use Psalm\Type\Union;

/**
 * `Understudy::for(A::class, B::class)` is a double of both, and its type
 * says so: `A&B`.
 *
 * The core declares `@return T` for the first argument, and has no way to
 * name the rest — a variadic `class-string ...$interfaces` carries no
 * template. Without this the methods of `B` do not exist for the analyser,
 * on a double built precisely to have them.
 *
 * Only for the spelling whose classes are known without guessing: two or
 * more positional arguments, every one a written-out `Name::class`, the
 * first a class or interface Psalm knows and every other an interface — the
 * shape the core accepts. Anything else is left to the core's declaration,
 * which is right for the first argument and silent about the rest.
 *
 * @internal
 */
final class DoubleType
{
    private function __construct() {}

    /**
     * @param list<Arg> $arguments
     */
    public static function of(array $arguments, Codebase $codebase): ?Union
    {
        if (\count($arguments) < 2) {
            return null;
        }

        $names = [];

        foreach ($arguments as $argument) {
            $name = self::literalClass($argument);

            if ($name === null) {
                return null;
            }

            $names[] = $name;
        }

        $target = array_shift($names);

        if (!$codebase->classOrInterfaceExists($target)) {
            return null;
        }

        $extra = [];

        foreach ($names as $interface) {
            if (!$codebase->interfaceExists($interface)) {
                return null;
            }

            $extra[strtolower($interface)] = new TNamedObject($interface);
        }

        return new Union([new TNamedObject($target, is_static: false, definite_class: false, extra_types: $extra)]);
    }

    /**
     * @return non-empty-string|null
     */
    private static function literalClass(Arg $argument): ?string
    {
        $value = $argument->value;

        if ($argument->unpack
            || $argument->name instanceof \PhpParser\Node\Identifier
            || !$value instanceof ClassConstFetch
            || !$value->class instanceof Name
            || !$value->name instanceof Identifier
            || $value->name->toLowerString() !== 'class'
            || $value->class->isSpecialClassName()
        ) {
            return null;
        }

        // Read out of the attribute bag: see `SpecificationRules::resolvedName()`.
        $attributes = $value->class->getAttributes();
        $resolved = isset($attributes['resolvedName']) && \is_string($attributes['resolvedName'])
            ? $attributes['resolvedName']
            : $value->class->toString();
        $resolved = ltrim($resolved, '\\');

        return $resolved === '' ? null : $resolved;
    }
}
