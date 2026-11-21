<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\Psalm\Tests\Internal;

use PhpParser\Node\Expr;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use Rasuvaeff\Understudy\Psalm\Internal\RestTail;
use Rasuvaeff\Understudy\Psalm\Tests\Support\Parse;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Test;

/**
 * Where a tail of `Arg::any()` can be one `Arg::rest()`, and where it cannot.
 *
 * Every silent case is a place where the two would not be the same
 * specification, or where the written order does not say which position an
 * argument fills.
 *
 * @internal
 */
#[Test]
#[Covers(RestTail::class)]
final class RestTailTest
{
    /**
     * @param int<0, max>                          $required
     * @param int<0, max>                          $declared
     * @param array{int<0, max>, int<3, max>}|null $expected
     */
    #[DataProvider('tailProvider')]
    public function findsTheRunRestCanReplace(
        string $call,
        int $required,
        int $declared,
        bool $variadic,
        ?array $expected,
    ): void {
        Assert::same(
            RestTail::of($this->arguments($call), $this->matcherName(...), $required, $declared, $variadic),
            $expected,
        );
    }

    public static function tailProvider(): iterable
    {
        yield 'three after a literal' => ['$d->m(1, Arg::any(), Arg::any(), Arg::any())', 4, 4, false, [1, 3]];
        yield 'every argument' => ['$d->m(Arg::any(), Arg::any(), Arg::any())', 3, 3, false, [0, 3]];
        yield 'four' => ['$d->m(Arg::any(), Arg::any(), Arg::any(), Arg::any())', 4, 4, false, [0, 4]];
        yield 'optional parameters left unspelled' => ['$d->m(1, Arg::any(), Arg::any(), Arg::any())', 2, 6, false, [1, 3]];
        yield 'exactly the required count' => ['$d->m(Arg::any(), Arg::any(), Arg::any())', 3, 5, false, [0, 3]];
        yield 'the name is case-insensitive' => ['$d->m(Arg::ANY(), Arg::Any(), Arg::any())', 3, 3, false, [0, 3]];

        yield 'two' => ['$d->m(1, Arg::any(), Arg::any())', 3, 3, false, null];
        yield 'none' => ['$d->m(1, 2, 3)', 3, 3, false, null];
        yield 'no arguments' => ['$d->m()', 0, 0, false, null];
        yield 'a literal breaks the run' => ['$d->m(Arg::any(), Arg::any(), 1, Arg::any(), Arg::any())', 5, 5, false, null];
        yield 'another matcher ends it' => ['$d->m(Arg::any(), Arg::any(), Arg::any(), Arg::int())', 4, 4, false, null];
        yield 'a variadic method' => ['$d->m(Arg::any(), Arg::any(), Arg::any())', 1, 1, true, null];
        yield 'fewer than required' => ['$d->m(Arg::any(), Arg::any(), Arg::any())', 4, 4, false, null];
        yield 'more than declared' => ['$d->m(Arg::any(), Arg::any(), Arg::any())', 2, 2, false, null];
        yield 'a named argument' => ['$d->m(Arg::any(), Arg::any(), c: Arg::any())', 3, 3, false, null];
        yield 'an unpacked argument' => ['$d->m(1, ...$rest, Arg::any(), Arg::any(), Arg::any())', 1, 5, false, null];
        yield 'a first-class callable is not a matcher' => [
            '$d->m(Arg::any(), Arg::any(), Arg::any(...))',
            3,
            3,
            false,
            null,
        ];
        yield 'somebody else\'s any()' => ['$d->m(Other::any(), Other::any(), Other::any())', 3, 3, false, null];
        yield 'already rest()' => ['$d->m(1, Arg::rest())', 4, 4, false, null];
    }

    /**
     * The message is asserted whole: it is what a user acts on.
     */
    public function saysWhatToWriteInstead(): void
    {
        Assert::same(
            RestTail::message(4),
            'The last 4 arguments are all `Arg::any()` and can be one `Arg::rest()`. '
            . 'The method has no variadic parameter, so `rest()` matches exactly the same calls '
            . 'and says outright that the arguments from here on do not matter.',
        );
    }

    /**
     * A stand-in for each plugin's resolver: `Arg` is ours, anything else is
     * not.
     */
    private function matcherName(Expr $expression): ?string
    {
        return $expression instanceof StaticCall
            && $expression->class instanceof Name
            && $expression->class->toString() === 'Arg'
            && $expression->name instanceof Identifier
            ? $expression->name->toString()
            : null;
    }

    /**
     * @param non-empty-string $call
     *
     * @return list<\PhpParser\Node\Arg>
     */
    private function arguments(string $call): array
    {
        $expression = Parse::expression($call . ';');

        Assert::instanceOf($expression, MethodCall::class);

        return array_values($expression->getArgs());
    }
}
