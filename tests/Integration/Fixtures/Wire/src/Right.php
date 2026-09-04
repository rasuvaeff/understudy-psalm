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

    /**
     * An intersection parameter is one double standing for every contract in
     * it, which is what the core builds. Both halves have to be callable on
     * the key, or the shape is narrower than the object it describes.
     */
    public function anIntersectionParameterIsBothContracts(): void
    {
        $wired = Understudy::wire(Reviewed::class);

        $books = $wired['doubles']['books'];
        when(static fn(): ?string => $books->find(1))->returns('Dune');
        when(static fn(): bool => $books->audit())->returns(true);
    }
}
