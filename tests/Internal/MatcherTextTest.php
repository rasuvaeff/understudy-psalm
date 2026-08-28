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
        // Somebody else's class actually called `Arg`, under its own
        // namespace: accepted while any prefix was allowed in front of the
        // name, so a real diagnostic about their code was dropped.
        yield 'a foreign Arg under its own namespace' => ['Acme\\Console\\Arg::string()', false];
        yield 'a foreign Arg with a leading separator' => ['\\Acme\\Arg::string()', false];
        // Class names are case-insensitive in PHP, and so is this.
        yield 'our own, lowercased' => ['arg::any()', true];
        yield 'a variable holding a class name' => ['$arg::any()', false];
        yield 'a constant, not a call' => ['Arg::ANY', false];

        // A captor's capture() is a matcher in method-call clothes — the one
        // matcher produced by a method call rather than an `Arg::` factory.
        yield 'a capture on a captor' => ['$options->capture()', true];
        yield 'a capture, spaced out' => ['$options -> capture ( )', true];
        yield 'a capture on a property' => ['$this->codes->capture()', true];
        // With arguments it is not our capture(): the contract takes none,
        // and a foreign method that happens to share the name keeps its
        // diagnostic where the parentheses are not empty.
        yield 'a capture with an argument' => ['$camera->capture($frame)', false];
        yield 'a static capture is not ours' => ['Camera::capture()', false];
    }
}
