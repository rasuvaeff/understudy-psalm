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
     * Matchers whose name alone says what they can match, and the atomic
     * types an argument they match could have.
     *
     * `same`, `not`, `satisfies`, `any`, `none` and `remaining` are absent on
     * purpose: their argument decides what they match, so the kind is not
     * knowable from the matcher name alone. `instanceOf` and a captor's
     * `capture()` name their class in an argument, and are judged by
     * {@see instanceProblem()} instead.
     *
     * `int` carries `TFloat` beside it because PHP widens an int wherever a
     * float is declared, and the engine follows that. `string` carries
     * `TCallable` because a callable is spelled as a string often enough that
     * "no string can be one" would be a false accusation.
     *
     * `containing` matches arrays and nothing else — the engine asks
     * `is_array()` — so an iterable or a callable, which may be an array, is
     * the only company it keeps. `count` matches an array or a `Countable`,
     * and every object is a maybe: whether a class implements `Countable` is
     * not a question this table asks. `which` matches an object, which a
     * callable or an iterable may be.
     *
     * Held as plain `class-string` rather than `class-string<Atomic>`:
     * parameterised, Psalm reads `$atomic instanceof $class` as always
     * true — every listed class IS an `Atomic`, so it cannot see that a
     * given atomic may not be that one.
     *
     * @var array<string, array{non-empty-string, non-empty-list<class-string>}>
     */
    private const array KINDS = [
        'int' => ['int', [Atomic\TInt::class, Atomic\TFloat::class]],
        'float' => ['float', [Atomic\TFloat::class]],
        'string' => ['string', [Atomic\TString::class, Atomic\TCallable::class]],
        'bool' => ['bool', [Atomic\TBool::class]],
        'containing' => ['array', [Atomic\TArray::class, Atomic\TKeyedArray::class, Atomic\TIterable::class, Atomic\TCallable::class]],
        'count' => [
            'array|Countable',
            [
                Atomic\TArray::class,
                Atomic\TKeyedArray::class,
                Atomic\TIterable::class,
                Atomic\TCallable::class,
                Atomic\TNamedObject::class,
                Atomic\TObject::class,
            ],
        ],
        'which' => ['object', [Atomic\TNamedObject::class, Atomic\TObject::class, Atomic\TCallable::class, Atomic\TIterable::class]],
    ];

    /**
     * The atomic types an object could be an instance of. Everything else
     * {@see isConcrete()} reads holds no object at all.
     */
    private const array OBJECTS = [
        Atomic\TNamedObject::class,
        Atomic\TObject::class,
        Atomic\TCallable::class,
        Atomic\TIterable::class,
    ];

    private function __construct() {}

    /**
     * The complaint, or null when this pairing is fine or unknowable.
     *
     * @param non-empty-string $matcher the `Arg::` method name
     */
    public static function problem(string $matcher, Union $parameterType): ?string
    {
        $entry = self::KINDS[strtolower($matcher)] ?? null;

        if ($entry === null) {
            return null;
        }

        [$kind, $accepted] = $entry;

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
        }

        return self::complaint('`Arg::' . $matcher . '()`', $kind, $parameterType);
    }

    /**
     * `Arg::instanceOf(Foo::class)`, or a `capture()` on a `Captor<Foo>`:
     * a matcher that holds only instances of one class.
     *
     * A parameter that can hold no object at all is a definite no. So is a
     * parameter of another CLASS, when neither of the two extends the other:
     * no object is an instance of two unrelated classes, and that stays true
     * of a double too — `Understudy::for()` can combine a class with
     * interfaces, never with a second class.
     *
     * An interface on either side is a maybe, final or not. A double of a
     * final class implementing an interface the class does not is exactly
     * what `Understudy::for(Final::class, Contract::class)` builds, so the
     * finality a type checker would rightly lean on proves nothing here.
     *
     * @param non-empty-string                 $matcher   the matcher as written, for the message
     * @param non-empty-string                 $class     the class the matcher holds instances of
     * @param \Closure(string, string): bool   $unrelated whether two names are classes, neither
     *                                                    extending the other
     */
    public static function instanceProblem(
        string $matcher,
        string $class,
        Union $parameterType,
        \Closure $unrelated,
    ): ?string {
        foreach ($parameterType->getAtomicTypes() as $atomic) {
            if (self::mayHoldInstance($class, $atomic, $unrelated)) {
                return null;
            }
        }

        return self::complaint($matcher, $class, $parameterType);
    }

    /**
     * @param \Closure(string, string): bool $unrelated
     */
    private static function mayHoldInstance(string $class, Atomic $atomic, \Closure $unrelated): bool
    {
        if ($atomic instanceof TNamedObject) {
            return $atomic->extra_types !== [] || !$unrelated($class, $atomic->value);
        }

        return self::mayHold(self::OBJECTS, $atomic) !== false;
    }

    /**
     * @param non-empty-string $matcher
     * @param non-empty-string $kind
     */
    private static function complaint(string $matcher, string $kind, Union $parameterType): string
    {
        $ids = [];

        foreach ($parameterType->getAtomicTypes() as $atomic) {
            $ids[] = $atomic->getId();
        }

        return sprintf(
            // "a value of type int", not "a int": the kind is a type name, and
            // an article picked for it in advance is wrong half the time. The
            // PHPStan plugin words the same sentence this way already.
            '%s matches a value of type %s, and this parameter accepts %s. '
            . 'No argument can satisfy both, so the specification can never match.',
            $matcher,
            $kind,
            implode('|', $ids),
        );
    }

    /**
     * True — a value of this kind fits. False — none ever could. Null — the
     * atomic is not one this plugin reads, so it has no opinion.
     *
     * @param list<class-string> $accepted
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
