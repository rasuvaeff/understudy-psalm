<?php

declare(strict_types=1);

namespace Fixture\Rest;

use Rasuvaeff\Understudy\Arg;
use Rasuvaeff\Understudy\Understudy;

use function Rasuvaeff\Understudy\expect;
use function Rasuvaeff\Understudy\verify;
use function Rasuvaeff\Understudy\when;

/**
 * Trailing runs of `Arg::any()` that `Arg::rest()` says in one word. One
 * suggestion per specification, on the first `Arg::any()` of the run.
 * `Rewritten.php` is this file with every suggestion taken.
 */
final class Suggested
{
    private Storage $storage;

    public function __construct()
    {
        $this->storage = Understudy::for(Storage::class);
    }

    public function everyArgument(): void
    {
        $storage = Understudy::for(Storage::class);

        when(static fn(): array => $storage->claim(Arg::any(), Arg::any(), Arg::any(), Arg::any()))->returns([]);
    }

    public function afterALiteral(\DateTimeImmutable $now): void
    {
        $storage = Understudy::for(Storage::class);

        when(static fn(): array => $storage->claim($now, Arg::any(), Arg::any(), Arg::any()))->returns([]);
    }

    public function optionalParametersLeftOut(): void
    {
        $storage = Understudy::for(Storage::class);

        when(static fn(): bool => $storage->wide(1, Arg::any(), Arg::any(), Arg::any()))->returns(true);
    }

    public function throughAField(): void
    {
        when(fn(): array => $this->storage->claim(Arg::any(), Arg::any(), Arg::any(), Arg::any()))->returns([]);
    }

    public function otherVerbs(): void
    {
        $storage = Understudy::for(Storage::class);

        expect(static fn(): bool => $storage->numbers(1, Arg::any(), Arg::any(), Arg::any()))->returns(true);
        Understudy::when(static fn(): bool => $storage->numbers(2, Arg::any(), Arg::any(), Arg::any()))->returns(true);
        verify(static fn(): bool => $storage->numbers(3, Arg::any(), Arg::any(), Arg::any()), times: 1);
    }

    public function theSecondContract(): void
    {
        $both = Understudy::for(Storage::class, Audit::class);

        when(static fn(): bool => $both->record('opened', Arg::any(), Arg::any(), Arg::any()))->returns(true);
    }
}
