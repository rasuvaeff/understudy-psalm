<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\Psalm\Tests\Internal;

use Rasuvaeff\Understudy\Psalm\Internal\ScopeIndex;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

/**
 * @internal
 */
#[Test]
#[Covers(ScopeIndex::class)]
final class ScopeIndexTest
{
    public function anOffsetInsideARecordedCallIsCovered(): void
    {
        $index = new ScopeIndex();
        $index->record('/a.php', 10, 12);

        Assert::true($index->covers('/a.php', 10));
        Assert::true($index->covers('/a.php', 11));
        Assert::true($index->covers('/a.php', 12));
    }

    public function anOffsetOutsideIsNot(): void
    {
        $index = new ScopeIndex();
        $index->record('/a.php', 10, 12);

        Assert::false($index->covers('/a.php', 9));
        Assert::false($index->covers('/a.php', 13));
    }

    public function offsetRangesAreKeptPerFile(): void
    {
        // Two files analysed in one run must not lend each other their scopes.
        $index = new ScopeIndex();
        $index->record('/a.php', 10, 12);

        Assert::false($index->covers('/b.php', 11));
    }

    public function severalOffsetRangesInOneFileAreAllRemembered(): void
    {
        $index = new ScopeIndex();
        $index->record('/a.php', 10, 12);
        $index->record('/a.php', 20, 20);

        Assert::true($index->covers('/a.php', 20));
        Assert::false($index->covers('/a.php', 15));
    }

    public function aReversedOffsetRangeIsStillARange(): void
    {
        // Nothing should hand these over backwards, but a silently empty
        // range would switch the whole plugin off for that call.
        $index = new ScopeIndex();
        $index->record('/a.php', 12, 10);

        Assert::true($index->covers('/a.php', 11));
    }

    public function forgettingEmptiesIt(): void
    {
        $index = new ScopeIndex();
        $index->record('/a.php', 10, 12);
        $index->forget();

        Assert::false($index->covers('/a.php', 11));
    }

    public function anUnknownFileCoversNothing(): void
    {
        Assert::false((new ScopeIndex())->covers('/never-seen.php', 1));
    }
}
