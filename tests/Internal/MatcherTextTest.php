<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\Psalm\Tests\Internal;

use Rasuvaeff\Understudy\Psalm\Internal\MatcherText;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Test;

/**
 * The last of the three conditions before a diagnostic is dropped, and the
 * one with the least to go on: the issue hook is handed the reported text,
 * not the node. Everything not recognisably a matcher keeps its report.
 *
 * @internal
 */
#[Test]
#[Covers(MatcherText::class)]
final class MatcherTextTest
{
    #[DataProvider('textProvider')]
    public function decidesWhetherTheReportedTextIsAMatcher(string $selected, bool $expected): void
    {
        Assert::same(MatcherText::looksLikeMatcher($selected), $expected);
    }

    public static function textProvider(): iterable
    {
        yield 'bare' => ['Arg::any()', true];
        yield 'with arguments' => ['Arg::int(min: 1, max: 5)', true];
        yield 'qualified' => ['Rasuvaeff\Understudy\Arg::string()', true];
        yield 'leading separator' => ['\Rasuvaeff\Understudy\Arg::same($book)', true];
        yield 'spaced out' => ['Arg :: any ()', true];
        yield 'nested inside another call' => ['Arg::not(Arg::int())', true];

        // A literal that merely sits inside a specification closure is an
        // ordinary argument and keeps its diagnostic.
        yield 'a plain literal' => ['7', false];
        yield 'a variable' => ['$id', false];
        yield 'a method call' => ['$this->id()', false];
        // Namesakes: neither is ours, and both would be silenced by a looser
        // test than this one.
        yield 'a class whose name ends in Arg' => ['MyArg::any()', false];
        yield 'a variable holding a class name' => ['$arg::any()', false];
        yield 'a constant, not a call' => ['Arg::ANY', false];
    }
}
