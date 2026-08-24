<?php

declare(strict_types=1);

namespace Fixture\Wire;

use Rasuvaeff\Understudy\Understudy;

use function Rasuvaeff\Understudy\when;

final class Right
{
    public function theShapeIsKnownFromTheConstructor(): void
    {
        $wired = Understudy::wire(Checkout::class);

        $sut = $wired['sut'];
        $sut->total();

        $books = $wired['doubles']['books'];
        when(static fn(): ?string => $books->find(1))->returns('Dune');
    }
}
