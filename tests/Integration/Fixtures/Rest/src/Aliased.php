<?php

declare(strict_types=1);

namespace Fixture\Rest;

use Rasuvaeff\Understudy\Arg as Matcher;
use Rasuvaeff\Understudy\Understudy;

use function Rasuvaeff\Understudy\when;

/**
 * Our `Arg` under another name is still ours: one suggestion.
 */
final class Aliased
{
    public function aliased(): void
    {
        $storage = Understudy::for(Storage::class);

        when(static fn(): bool => $storage->numbers(1, Matcher::any(), Matcher::any(), Matcher::any()))->returns(true);
    }
}
