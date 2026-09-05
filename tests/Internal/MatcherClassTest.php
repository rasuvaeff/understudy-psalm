<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\Psalm\Tests\Internal;

use Rasuvaeff\Understudy\Arg;
use Rasuvaeff\Understudy\Psalm\Internal\MatcherClass;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Test;

/**
 * Which `Arg` the rules act on. The question is asked of a resolved name, so
 * an import — plain or aliased — has already been followed by the time it
 * gets here, and what is left to decide is only whether the class is ours.
 *
 * @internal
 */
#[Test]
#[Covers(MatcherClass::class)]
final class MatcherClassTest
{
    #[DataProvider('classProvider')]
    public function decidesWhetherTheClassIsOurs(string $resolved, bool $expected): void
    {
        Assert::same(MatcherClass::isOurs($resolved), $expected);
    }

    public static function classProvider(): iterable
    {
        yield 'ours' => [Arg::class, true];
        yield 'ours with a leading separator' => ['\\' . Arg::class, true];
        // PHP class names are case-insensitive, and a resolver may hand back
        // whichever spelling the file used.
        yield 'ours, lowercased' => [strtolower(Arg::class), true];

        // Namesakes. Reading the short name alone claimed every one of these.
        yield 'a foreign class of the same short name' => ['Acme\Console\Arg', false];
        yield 'a global class of the same short name' => ['Arg', false];
        yield 'a name that merely ends in Arg' => ['Rasuvaeff\Understudy\MyArg', false];
        yield 'our namespace, another class' => [\Rasuvaeff\Understudy\Understudy::class, false];
    }
}
