<?php

declare(strict_types=1);

namespace Fixture\Rest;

use Rasuvaeff\Understudy\Arg;
use Rasuvaeff\Understudy\Understudy;

use function Rasuvaeff\Understudy\expect;
use function Rasuvaeff\Understudy\verify;
use function Rasuvaeff\Understudy\when;

/**
 * `Suggested.php` with every suggestion taken. Following the advice must
 * leave nothing to report — not an arity error, not a leaked matcher.
 */
final class Rewritten
{
    private Storage $storage;

    public function __construct()
    {
        $this->storage = Understudy::for(Storage::class);
    }

    public function everyArgument(): void
    {
        $storage = Understudy::for(Storage::class);

        when(static fn(): array => $storage->claim(Arg::rest()))->returns([]);
    }

    public function afterALiteral(\DateTimeImmutable $now): void
    {
        $storage = Understudy::for(Storage::class);

        when(static fn(): array => $storage->claim($now, Arg::rest()))->returns([]);
    }

    public function optionalParametersLeftOut(): void
    {
        $storage = Understudy::for(Storage::class);

        when(static fn(): bool => $storage->wide(1, Arg::rest()))->returns(true);
    }

    public function throughAField(): void
    {
        when(fn(): array => $this->storage->claim(Arg::rest()))->returns([]);
    }

    public function otherVerbs(): void
    {
        $storage = Understudy::for(Storage::class);

        expect(static fn(): bool => $storage->numbers(1, Arg::rest()))->returns(true);
        Understudy::when(static fn(): bool => $storage->numbers(2, Arg::rest()))->returns(true);
        verify(static fn(): bool => $storage->numbers(3, Arg::rest()), times: 1);
    }

    public function theSecondContract(): void
    {
        $both = Understudy::for(Storage::class, Audit::class);

        when(static fn(): bool => $both->record('opened', Arg::rest()))->returns(true);
    }
}
