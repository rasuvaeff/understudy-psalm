<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\Psalm\Internal;

use Psalm\Type\Atomic;
use Psalm\Type\Atomic\TArray;
use Psalm\Type\Atomic\TBool;
use Psalm\Type\Atomic\TCallable;
use Psalm\Type\Atomic\TFloat;
use Psalm\Type\Atomic\TInt;
use Psalm\Type\Atomic\TIterable;
use Psalm\Type\Atomic\TKeyedArray;
use Psalm\Type\Atomic\TNamedObject;
use Psalm\Type\Atomic\TNull;
use Psalm\Type\Atomic\TObject;
use Psalm\Type\Atomic\TResource;
use Psalm\Type\Atomic\TString;
use Psalm\Type\Union;

/**
 * Whether a matcher can ever match a parameter of a given type.
 *
 * Deliberately about the KIND, not about assignability. `Arg::int()` cannot
 * match a `string` parameter under any argument, and that is worth saying
 * before the test runs; `Arg::any()` can match anything and never complains.
 * Everything the plugin is not sure about is silent — a false accusation here
 * costs more than a missed one, because the runtime still catches the miss.
 *
 * The question is asked of Psalm's atomic types, not of the names they print
 * as. A name comparison called `non-empty-string`, `numeric-string`,
 * `class-string`, `positive-int`, `int<1, 10>` and every literal a mistake,
 * because none of those strings is `string` or `int` — and a refined type is
 * still the kind it refines. `TNonEmptyString` IS a `TString` and
 * `TIntRange` IS a `TInt`, so the class hierarchy answers what the spelling
 * could not.
 *
 * @internal
 */
final class MatcherKind
{
    /**
     * Matchers that assert a scalar kind, and the atomic types an argument of
     * that kind could have.
     *
     * `same`, `not`, `containing`, `count`, `which`, `instanceOf`,
     * `satisfies`, `any`, `none` and `remaining` are absent on purpose:
     * their argument decides what they match, so the kind is not knowable
     * from the matcher name alone.
     *
     * `int` carries `TFloat` beside it because PHP widens an int wherever a
     * float is declared, and the engine follows that. `string` carries
     * `TCallable` because a callable is spelled as a string often enough that
     * "no string can be one" would be a false accusation.
     *
     * Held as plain `class-string` rather than `class-string<Atomic>`:
     * parameterised, Psalm reads `$atomic instanceof $class` as always
     * true — every listed class IS an `Atomic`, so it cannot see that a
     * given atomic may not be that one.
     *
     * @var array<string, non-empty-list<class-string>>
     */
    private const array KINDS = [
        'int' => [Atomic\TInt::class, Atomic\TFloat::class],
        'float' => [Atomic\TFloat::class],
        'string' => [Atomic\TString::class, Atomic\TCallable::class],
        'bool' => [Atomic\TBool::class],
    ];

    private function __construct() {}

    /**
     * The complaint, or null when this pairing is fine or unknowable.
     *
     * @param non-empty-string $matcher the `Arg::` method name
     */
    public static function problem(string $matcher, Union $parameterType): ?string
    {
        $kind = strtolower($matcher);
        $accepted = self::KINDS[$kind] ?? null;

        if ($accepted === null) {
            return null;
        }

        $ids = [];

        // Not guarded against an empty union: Psalm declares
        // `getAtomicTypes()` non-empty, and a union of nothing is not a shape
        // its own analyser admits. A check here would be a branch no run
        // reaches, which is worse than no check — it reads as a case somebody
        // handled.
        foreach ($parameterType->getAtomicTypes() as $atomic) {
            // Only a definite no counts. `null` is "not for this plugin to
            // say", and one member of a union that might hold the value is
            // enough for the whole union to.
            if (self::mayHold($accepted, $atomic) !== false) {
                return null;
            }

            $ids[] = $atomic->getId();
        }

        return sprintf(
            '`Arg::%s()` matches a %s, and this parameter accepts %s. '
            . 'No argument can satisfy both, so the expectation can never match.',
            $matcher,
            $kind,
            implode('|', $ids),
        );
    }

    /**
     * True — a value of this kind fits. False — none ever could. Null — the
     * atomic is not one this plugin reads, so it has no opinion.
     *
     * @param non-empty-list<class-string> $accepted
     */
    private static function mayHold(array $accepted, Atomic $atomic): ?bool
    {
        foreach ($accepted as $class) {
            if ($atomic instanceof $class) {
                return true;
            }
        }

        return self::isConcrete($atomic) ? false : null;
    }

    /**
     * Whether the values of this atomic are ones the plugin can enumerate.
     *
     * A definite "no argument can satisfy both" is only honest about these.
     * An unresolved alias, a conditional type, a class constant — anything
     * absent from this list — stands for something the plugin has not looked
     * up, and guessing there is how a static check earns its false
     * accusations.
     *
     * `mixed`, `scalar`, `array-key`, `numeric` and a template parameter are
     * absent for the same reason rather than by oversight: each stands for a
     * SET of kinds this plugin does not take apart, and one of their members
     * could be the one the matcher asserts. There is no separate branch
     * letting them through — being missing from this list is what lets them
     * through, and a branch that only repeated it would be one no test could
     * tell from its absence.
     */
    private static function isConcrete(Atomic $atomic): bool
    {
        return $atomic instanceof TInt
            || $atomic instanceof TFloat
            || $atomic instanceof TString
            || $atomic instanceof TBool
            || $atomic instanceof TNull
            || $atomic instanceof TArray
            || $atomic instanceof TKeyedArray
            || $atomic instanceof TNamedObject
            || $atomic instanceof TObject
            || $atomic instanceof TCallable
            || $atomic instanceof TIterable
            || $atomic instanceof TResource;
    }
}
