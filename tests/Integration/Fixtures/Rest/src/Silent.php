<?php

declare(strict_types=1);

namespace Fixture\Rest;

use Rasuvaeff\Understudy\Arg;
use Rasuvaeff\Understudy\Understudy;

use function Rasuvaeff\Understudy\when;

/**
 * Tails `Arg::rest()` would not say the same way, or that are too short to
 * be worth it. Nothing here may be suggested, and nothing else reported.
 */
final class Silent
{
    public function two(): void
    {
        $storage = Understudy::for(Storage::class);

        when(static fn(): bool => $storage->numbers(1, 2, Arg::any(), Arg::any()))->returns(true);
    }

    public function interrupted(): void
    {
        $storage = Understudy::for(Storage::class);

        when(static fn(): bool => $storage->numbers(Arg::any(), Arg::any(), 3, Arg::any()))->returns(true);
    }

    public function endedByAnotherMatcher(): void
    {
        $storage = Understudy::for(Storage::class);

        when(static fn(): bool => $storage->numbers(Arg::any(), Arg::any(), Arg::any(), Arg::int()))->returns(true);
    }

    /**
     * A variadic tail has a length: three `any()` pin it to two labels,
     * `rest()` would accept any number.
     */
    public function variadic(): void
    {
        $storage = Understudy::for(Storage::class);

        when(static fn(): bool => $storage->tag(Arg::any(), Arg::any(), Arg::any()))->returns(true);
    }

    public function named(): void
    {
        $storage = Understudy::for(Storage::class);

        when(static fn(): bool => $storage->numbers(1, Arg::any(), Arg::any(), d: Arg::any()))->returns(true);
    }

    public function somebodyElsesAny(): void
    {
        $storage = Understudy::for(Storage::class);

        when(static fn(): bool => $storage->numbers(1, Arg::any(), Arg::any(), Other\Arg::any()))->returns(true);
        when(static fn(): bool => $storage->numbers(Other\Arg::any(), Other\Arg::any(), Other\Arg::any(), Other\Arg::any()))->returns(true);
    }

    /**
     * Not a specification: a real call is not this rule's business.
     */
    public function aRealCall(Storage $storage): bool
    {
        return $storage->numbers(1, 2, 3, 4);
    }
}
