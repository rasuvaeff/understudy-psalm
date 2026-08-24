<?php

declare(strict_types=1);

namespace Fixture\Wire;

use Rasuvaeff\Understudy\Understudy;

final class Wrong
{
    public function aKeyTheConstructorHasNoParameterFor(): void
    {
        $wired = Understudy::wire(Checkout::class);

        $wired['doubles']['repository']->find(1);
    }

    public function aMethodTheContractDoesNotHave(): void
    {
        $wired = Understudy::wire(Checkout::class);

        $wired['doubles']['clock']->tick();
    }
}
