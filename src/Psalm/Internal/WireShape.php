<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\Psalm\Internal;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use Psalm\Codebase;
use Psalm\Type\Atomic\TKeyedArray;
use Psalm\Type\Atomic\TNamedObject;
use Psalm\Type\Union;

/**
 * The precise shape `wire()` hands back for a named class.
 *
 * The core declares `array{sut: object, doubles: array<string, object>}`,
 * which is all it can say without knowing the class. Given a literal
 * `Sut::class` the constructor is readable, so `$wired['sut']` can be the
 * class itself and `$wired['doubles']['repository']` the contract it stands
 * for — and a key the constructor has no parameter for becomes an error
 * instead of a silent `object`.
 *
 * A dynamic class-string is left alone: the core's declaration is then the
 * honest answer, and guessing would be worse than saying less.
 *
 * @internal
 */
final class WireShape
{
    private function __construct() {}

    /**
     * @param list<Arg> $arguments
     */
    public static function of(array $arguments, Codebase $codebase): ?Union
    {
        $class = self::namedClass($arguments[0]->value ?? null);

        if ($class === null || !$codebase->classExists($class)) {
            return null;
        }

        $constructor = $class . '::__construct';

        if (!$codebase->methodExists($constructor)) {
            // Nothing to read. The core's own declaration is then the honest
            // answer, and a shape of ours would only be a narrower guess.
            return null;
        }

        $doubles = [];

        foreach ($codebase->getMethodParams($constructor) as $parameter) {
            $type = $parameter->type;

            if ($type === null) {
                continue;
            }

            foreach ($type->getAtomicTypes() as $atomic) {
                if ($atomic instanceof TNamedObject) {
                    $doubles[$parameter->name] = new Union([$atomic]);

                    break;
                }
            }
        }

        if ($doubles === []) {
            return null;
        }

        return self::shape($class, new Union([new TKeyedArray($doubles)]));
    }

    private static function shape(string $class, Union $doubles): Union
    {
        return new Union([
            new TKeyedArray([
                'sut' => new Union([new TNamedObject($class)]),
                'doubles' => $doubles,
            ]),
        ]);
    }

    /**
     * The class a literal `Sut::class` or `'Sut'` names.
     */
    private static function namedClass(mixed $expression): ?string
    {
        if ($expression instanceof ClassConstFetch
            && $expression->class instanceof Name
            && $expression->name instanceof \PhpParser\Node\Identifier
            && strtolower($expression->name->toString()) === 'class'
        ) {
            $attributes = $expression->class->getAttributes();

            return isset($attributes['resolvedName']) && \is_string($attributes['resolvedName'])
                ? $attributes['resolvedName']
                : $expression->class->toString();
        }

        return $expression instanceof String_ && $expression->value !== '' ? $expression->value : null;
    }
}
