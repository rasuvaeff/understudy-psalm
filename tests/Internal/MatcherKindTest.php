<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\Psalm\Tests\Internal;

use Psalm\Type;
use Psalm\Type\Atomic;
use Psalm\Type\Union;
use Rasuvaeff\Understudy\Psalm\Internal\MatcherKind;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Test;

/**
 * Which matcher/parameter pairings the plugin is willing to call impossible.
 *
 * The asymmetry is the whole design: a missed report costs a diagnostic the
 * engine still raises at runtime, while a false one is an accusation the user
 * cannot act on, in code that is correct. So the rule speaks only when EVERY
 * member of the parameter's type is a definite no.
 *
 * @internal
 */
#[Test]
#[Covers(MatcherKind::class)]
final class MatcherKindTest
{
    #[DataProvider('pairingProvider')]
    public function saysOnlyWhatNoArgumentCouldSatisfy(string $matcher, Union $parameter, bool $reported): void
    {
        Assert::same(MatcherKind::problem($matcher, $parameter) !== null, $reported);
    }

    public static function pairingProvider(): iterable
    {
        // The plain kinds, both ways round.
        yield 'int into int' => ['int', self::of(new Atomic\TInt()), false];
        yield 'int into string' => ['int', self::of(new Atomic\TString()), true];
        yield 'string into string' => ['string', self::of(new Atomic\TString()), false];
        yield 'string into int' => ['string', self::of(new Atomic\TInt()), true];
        yield 'bool into bool' => ['bool', self::of(new Atomic\TBool()), false];
        yield 'bool into int' => ['bool', self::of(new Atomic\TInt()), true];
        yield 'float into float' => ['float', self::of(new Atomic\TFloat()), false];

        // PHP widens an int wherever a float is declared, and the engine
        // follows that. Not the other way round, under strict_types.
        yield 'int into float' => ['int', self::of(new Atomic\TFloat()), false];
        yield 'float into int' => ['float', self::of(new Atomic\TInt()), true];

        // A refinement is still the kind it refines. Every one of these was
        // reported while the rule compared printed type names.
        yield 'string into non-empty-string' => ['string', self::of(new Atomic\TNonEmptyString()), false];
        yield 'string into numeric-string' => ['string', self::of(new Atomic\TNumericString()), false];
        yield 'string into class-string' => ['string', self::of(new Atomic\TClassString()), false];
        yield 'string into lowercase-string' => ['string', self::of(new Atomic\TLowercaseString()), false];
        yield 'int into positive-int' => ['int', self::of(new Atomic\TIntRange(1, null)), false];
        yield 'int into an int range' => ['int', self::of(new Atomic\TIntRange(1, 10)), false];
        yield 'int into a literal int' => ['int', self::of(new Atomic\TLiteralInt(7)), false];
        yield 'bool into true' => ['bool', self::of(new Atomic\TTrue()), false];
        yield 'bool into false' => ['bool', self::of(new Atomic\TFalse()), false];

        // ...and a refinement of the WRONG kind is still the wrong kind.
        yield 'int into non-empty-string' => ['int', self::of(new Atomic\TNonEmptyString()), true];
        yield 'string into an int range' => ['string', self::of(new Atomic\TIntRange(1, 10)), true];

        // Types standing for a set of kinds the plugin does not take apart.
        yield 'int into mixed' => ['int', self::of(new Atomic\TMixed()), false];
        yield 'int into scalar' => ['int', self::of(new Atomic\TScalar()), false];
        yield 'float into array-key' => ['float', self::of(new Atomic\TArrayKey()), false];
        yield 'bool into numeric' => ['bool', self::of(new Atomic\TNumeric()), false];
        yield 'int into a template parameter' => [
            'int',
            self::of(new Atomic\TTemplateParam('T', Type::getMixed(), 'Fixture\\Gate')),
            false,
        ];

        // A callable is spelled as a string often enough that calling it
        // impossible would be a false accusation. The other kinds cannot.
        yield 'string into callable' => ['string', self::of(new Atomic\TCallable()), false];
        yield 'int into callable' => ['int', self::of(new Atomic\TCallable()), true];

        // A union needs one member that could hold the value, and nothing in
        // it that the plugin does not read.
        yield 'string into int|string' => [
            'string',
            new Union([new Atomic\TInt(), new Atomic\TString()]),
            false,
        ];
        yield 'bool into int|string' => [
            'bool',
            new Union([new Atomic\TInt(), new Atomic\TString()]),
            true,
        ];
        yield 'int into a nullable int' => [
            'int',
            new Union([new Atomic\TInt(), new Atomic\TNull()]),
            false,
        ];
        // Null on its own holds nothing, which is a definite no rather than a
        // silence — this is the report `?string` keeps when the string half
        // is gone.
        yield 'string into null' => ['string', self::of(new Atomic\TNull()), true];

        // Every shape the rule is willing to judge, each as the ONLY reason
        // its pairing is impossible. Drop one from that list and exactly one
        // of these stops being reported.
        yield 'string into float' => ['string', self::of(new Atomic\TFloat()), true];
        yield 'int into bool' => ['int', self::of(new Atomic\TBool()), true];
        yield 'int into a named object' => ['int', self::of(new Atomic\TNamedObject('Fixture\\Gate')), true];
        yield 'int into a bare object' => ['int', self::of(new Atomic\TObject()), true];
        yield 'string into an array' => [
            'string',
            self::of(new Atomic\TArray([Type::getArrayKey(), Type::getMixed()])),
            true,
        ];
        yield 'string into a keyed array' => [
            'string',
            self::of(Atomic\TKeyedArray::make(['id' => Type::getInt()])),
            true,
        ];
        yield 'string into an iterable' => [
            'string',
            self::of(new Atomic\TIterable([Type::getArrayKey(), Type::getMixed()])),
            true,
        ];
        yield 'string into a resource' => ['string', self::of(new Atomic\TResource()), true];

        // Matchers whose argument decides what they match: the kind is not
        // knowable from the name, so there is nothing to compare.
        yield 'any is never a kind' => ['any', self::of(new Atomic\TInt()), false];
        yield 'same is never a kind' => ['same', self::of(new Atomic\TString()), false];
        yield 'instanceOf is never a kind' => ['instanceOf', self::of(new Atomic\TInt()), false];

        // `containing()` matches arrays and nothing else — the engine asks
        // `is_array()` — so every object is a definite no, and an iterable
        // or a callable, either of which may be an array, is a maybe.
        yield 'containing into an array' => [
            'containing',
            self::of(new Atomic\TArray([Type::getArrayKey(), Type::getMixed()])),
            false,
        ];
        yield 'containing into a keyed array' => [
            'containing',
            self::of(Atomic\TKeyedArray::make(['id' => Type::getInt()])),
            false,
        ];
        yield 'containing into an iterable' => [
            'containing',
            self::of(new Atomic\TIterable([Type::getArrayKey(), Type::getMixed()])),
            false,
        ];
        yield 'containing into a callable' => ['containing', self::of(new Atomic\TCallable()), false];
        yield 'containing into int' => ['containing', self::of(new Atomic\TInt()), true];
        yield 'containing into a named object' => [
            'containing',
            self::of(new Atomic\TNamedObject('ArrayAccess')),
            true,
        ];
        yield 'containing into a bare object' => ['containing', self::of(new Atomic\TObject()), true];

        // `count()` matches an array or a `Countable`. Whether a class
        // implements `Countable` is not asked, so every object is a maybe.
        yield 'count into an array' => [
            'count',
            self::of(new Atomic\TArray([Type::getArrayKey(), Type::getMixed()])),
            false,
        ];
        yield 'count into a keyed array' => ['count', self::of(Atomic\TKeyedArray::make(['id' => Type::getInt()])), false];
        yield 'count into an iterable' => [
            'count',
            self::of(new Atomic\TIterable([Type::getArrayKey(), Type::getMixed()])),
            false,
        ];
        yield 'count into a callable' => ['count', self::of(new Atomic\TCallable()), false];
        yield 'count into a named object' => ['count', self::of(new Atomic\TNamedObject('Fixture\\Gate')), false];
        yield 'count into a bare object' => ['count', self::of(new Atomic\TObject()), false];
        yield 'count into string' => ['count', self::of(new Atomic\TString()), true];
        yield 'count into null' => ['count', self::of(new Atomic\TNull()), true];

        // `which()` reads a getter, so it matches objects only.
        yield 'which into a named object' => ['which', self::of(new Atomic\TNamedObject('Fixture\\Gate')), false];
        yield 'which into a bare object' => ['which', self::of(new Atomic\TObject()), false];
        yield 'which into a callable' => ['which', self::of(new Atomic\TCallable()), false];
        yield 'which into an iterable' => [
            'which',
            self::of(new Atomic\TIterable([Type::getArrayKey(), Type::getMixed()])),
            false,
        ];
        yield 'which into string' => ['which', self::of(new Atomic\TString()), true];
        yield 'which into an array' => [
            'which',
            self::of(new Atomic\TArray([Type::getArrayKey(), Type::getMixed()])),
            true,
        ];
        yield 'which into a nullable object' => [
            'which',
            new Union([new Atomic\TNamedObject('Fixture\\Gate'), new Atomic\TNull()]),
            false,
        ];

        // `whichProperty()` reads a public property: objects only, like `which()`.
        yield 'whichProperty into a named object' => [
            'whichProperty',
            self::of(new Atomic\TNamedObject('Fixture\\Gate')),
            false,
        ];
        yield 'whichProperty into a bare object' => ['whichProperty', self::of(new Atomic\TObject()), false];
        yield 'whichProperty into a callable' => ['whichProperty', self::of(new Atomic\TCallable()), false];
        yield 'whichProperty into an iterable' => [
            'whichProperty',
            self::of(new Atomic\TIterable([Type::getArrayKey(), Type::getMixed()])),
            false,
        ];
        yield 'whichProperty into mixed' => ['whichProperty', Type::getMixed(), false];
        yield 'whichProperty into string' => ['whichProperty', self::of(new Atomic\TString()), true];
        yield 'whichProperty into an int' => ['whichProperty', self::of(new Atomic\TInt()), true];

        // Case is not meaningful in a PHP method name.
        yield 'the matcher name is case-insensitive' => ['INT', self::of(new Atomic\TString()), true];
    }

    #[DataProvider('instanceProvider')]
    public function holdsAnInstanceOnlyWhereOneCanBe(Union $parameter, bool $unrelated, bool $reported): void
    {
        $asked = [];
        $problem = MatcherKind::instanceProblem(
            '`Arg::instanceOf()`',
            'Fixture\\Pen',
            $parameter,
            static function (string $one, string $other) use ($unrelated, &$asked): bool {
                $asked[] = [$one, $other];

                return $unrelated;
            },
        );

        Assert::same($problem !== null, $reported);

        foreach ($asked as $pair) {
            Assert::same($pair[0], 'Fixture\\Pen');
        }
    }

    public static function instanceProvider(): iterable
    {
        $book = new Atomic\TNamedObject('Fixture\\Book');

        // No object at all.
        yield 'into int' => [self::of(new Atomic\TInt()), false, true];
        yield 'into string' => [self::of(new Atomic\TString()), false, true];
        yield 'into null' => [self::of(new Atomic\TNull()), false, true];
        yield 'into an array' => [self::of(new Atomic\TArray([Type::getArrayKey(), Type::getMixed()])), false, true];

        // Another class: decided by the relation, and nothing else.
        yield 'into an unrelated class' => [self::of($book), true, true];
        yield 'into a related class' => [self::of($book), false, false];
        yield 'into a nullable unrelated class' => [new Union([$book, new Atomic\TNull()]), true, true];

        // An intersection may name the matcher's class among its members.
        yield 'into an intersection' => [
            self::of(new Atomic\TNamedObject('Fixture\\Book', is_static: false, definite_class: false, extra_types: [
                'fixture\\contract' => new Atomic\TNamedObject('Fixture\\Contract'),
            ])),
            true,
            false,
        ];

        // Shapes that may hold an object of any class.
        yield 'into a bare object' => [self::of(new Atomic\TObject()), true, false];
        yield 'into a callable' => [self::of(new Atomic\TCallable()), true, false];
        yield 'into an iterable' => [self::of(new Atomic\TIterable([Type::getArrayKey(), Type::getMixed()])), true, false];
        yield 'into mixed' => [self::of(new Atomic\TMixed()), true, false];
    }

    public function theInstanceComplaintNamesTheClass(): void
    {
        Assert::same(
            MatcherKind::instanceProblem(
                '`capture()`',
                'Fixture\\Pen',
                new Union([new Atomic\TInt(), new Atomic\TNull()]),
                static fn(string $one, string $other): bool => true,
            ),
            '`capture()` matches a value of type Fixture\\Pen, and this parameter accepts int|null. '
            . 'No argument can satisfy both, so the specification can never match.',
        );
    }

    public function theKindIsNamedByWhatItMatches(): void
    {
        Assert::same(
            MatcherKind::problem('count', self::of(new Atomic\TString())),
            '`Arg::count()` matches a value of type array|Countable, and this parameter accepts string. '
            . 'No argument can satisfy both, so the specification can never match.',
        );
    }

    /**
     * The message names the matcher, its kind and the type as Psalm prints
     * it — which is what a user needs to see to act on the report.
     */
    public function theComplaintNamesBothSides(): void
    {
        $problem = MatcherKind::problem('int', new Union([new Atomic\TString(), new Atomic\TNull()]));

        // Whole, not by fragments: half a concatenation reads as a sentence
        // too, and every member of the union has to be named — the report is
        // what a user acts on.
        Assert::same(
            $problem,
            '`Arg::int()` matches a value of type int, and this parameter accepts string|null. '
            . 'No argument can satisfy both, so the specification can never match.',
        );
    }

    /**
     * The matcher is quoted as it was written, while the kind is the one the
     * rule decided on.
     */
    public function theComplaintQuotesTheMatcherAsWritten(): void
    {
        Assert::same(
            MatcherKind::problem('INT', self::of(new Atomic\TString())),
            '`Arg::INT()` matches a value of type int, and this parameter accepts string. '
            . 'No argument can satisfy both, so the specification can never match.',
        );
    }

    private static function of(Atomic $atomic): Union
    {
        return new Union([$atomic]);
    }
}
