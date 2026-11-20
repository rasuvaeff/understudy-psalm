<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\Psalm\Internal;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use Psalm\Codebase;
use Psalm\NodeTypeProvider;
use Psalm\Type;
use Psalm\Type\Atomic;
use Psalm\Type\Atomic\TArray;
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
 * A key is in the shape only where the core puts one in `doubles`, which
 * `Wire` decides per parameter: a parameter named in `$overrides` takes the
 * given value and has no double, a variadic tail is never doubled, and a
 * parameter whose type cannot be doubled keeps its own default instead. The
 * first is read from the overrides argument; the other two from the
 * constructor.
 *
 * @internal
 */
final class WireShape
{
    private function __construct() {}

    /**
     * @param list<Arg> $arguments
     */
    public static function of(array $arguments, Codebase $codebase, NodeTypeProvider $types): ?Union
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

        $overrides = self::overrides($arguments, $types);
        $doubles = [];
        $known = false;

        foreach ($codebase->getMethodParams($constructor) as $parameter) {
            if (isset($overrides['definite'][$parameter->name]) || $parameter->is_variadic) {
                // The test handed the value in, and `Wire` takes it before
                // looking at the type: no double, and no refusal of a union
                // either. A variadic tail is never doubled — it is optional
                // by definition, and inventing entries for it would invent
                // collaborators. Either way there is never a key, and that is
                // worth an exact shape even when nothing else is doubled.
                $known = true;

                continue;
            }

            $type = $parameter->type;

            if ($type === null) {
                continue;
            }

            $named = array_values(array_filter(
                $type->getAtomicTypes(),
                static fn(Atomic $atomic): bool => $atomic instanceof TNamedObject,
            ));

            if (count($named) > 1) {
                // The core refuses to wire this class at all —
                // `CannotWire::undecidableParameter`, because picking one of
                // the union's object types would be a guess — so the call
                // throws before returning anything and there is no shape to
                // describe, not even for the parameters that would be fine.
                //
                // Naming one of them is worse than saying nothing: it makes a
                // call that always throws type-check, and which member gets
                // picked is not even stable — it is whichever the type's
                // atomic map happens to hold first.
                return null;
            }

            // Copied verbatim, and that is what makes an intersection work:
            // Psalm holds `A&B` as ONE `TNamedObject` carrying the rest in
            // `extra_types`, and the core builds one double implementing all
            // of them. Rebuilding the atomic from its name would drop the
            // other half and make the key narrower than the object it stands
            // for.
            $atomic = $named[0] ?? null;

            if (!$atomic instanceof TNamedObject) {
                continue;
            }

            $name = $parameter->name;
            $key = new Union([$atomic]);

            if ($overrides['open'] || isset($overrides['possible'][$name])
                || ($parameter->is_optional && !self::isInterface($atomic, $codebase))
            ) {
                $key = $key->setPossiblyUndefined(possibly_undefined: true);
            }

            $doubles[$name] = $key;
        }

        if ($doubles === [] && !$known) {
            return null;
        }

        return self::shape(
            $class,
            $doubles === [] ? Type::getEmptyArray() : new Union([TKeyedArray::make($doubles)]),
        );
    }

    /**
     * Whether every contract the parameter names is an interface.
     *
     * A class parameter with a default is answered by that default whenever
     * the class cannot be doubled — final unless `bypassFinals()` was called,
     * an enum, an internal class — and which of those holds is a runtime fact,
     * so its key may be missing. An interface is doubled whenever it is
     * reached; the residual is one that extends `Throwable` or declares a
     * constructor, which the core refuses too.
     */
    private static function isInterface(TNamedObject $atomic, Codebase $codebase): bool
    {
        if (!$codebase->interfaceExists($atomic->value)) {
            return false;
        }

        foreach ($atomic->extra_types as $extra) {
            if (!$extra instanceof TNamedObject || !$codebase->interfaceExists($extra->value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * The constructor parameters the overrides argument names.
     *
     * `definite` keys are always passed, so the core never doubles them;
     * `possible` keys may be; `open` means any key may be, because the
     * argument is not an array whose keys are known.
     *
     * @param list<Arg> $arguments
     *
     * @return array{definite: array<string, true>, possible: array<string, true>, open: bool}
     */
    private static function overrides(array $arguments, NodeTypeProvider $types): array
    {
        $none = ['definite' => [], 'possible' => [], 'open' => false];
        $open = ['definite' => [], 'possible' => [], 'open' => true];
        $argument = null;

        foreach ($arguments as $position => $candidate) {
            if ($candidate->unpack) {
                return $open;
            }

            $name = $candidate->name?->toString();

            if ($name === 'overrides' || $name === null && $position === 1) {
                $argument = $candidate;
            }
        }

        if (!$argument instanceof Arg) {
            return $none;
        }

        $type = $types->getType($argument->value);

        if (!$type instanceof Union) {
            return $open;
        }

        /** @var array<string, true>|null $definite */
        $definite = null;
        $possible = [];

        foreach ($type->getAtomicTypes() as $atomic) {
            if ($atomic instanceof TArray && $atomic->isEmptyArray()) {
                $definite = [];

                continue;
            }

            if (!$atomic instanceof TKeyedArray || $atomic->fallback_params !== null) {
                return $open;
            }

            $always = [];

            foreach ($atomic->properties as $key => $value) {
                $key = (string) $key;
                $possible[$key] = true;

                if (!$value->possibly_undefined) {
                    $always[$key] = true;
                }
            }

            if ($definite === null) {
                $definite = $always;

                continue;
            }

            foreach (array_keys($definite) as $key) {
                if (!isset($always[$key])) {
                    unset($definite[$key]);
                }
            }
        }

        return ['definite' => $definite ?? [], 'possible' => $possible, 'open' => false];
    }

    private static function shape(string $class, Union $doubles): Union
    {
        return new Union([
            TKeyedArray::make([
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
