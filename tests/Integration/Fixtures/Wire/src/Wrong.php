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

    /**
     * A union naming two object types makes the core refuse the whole class,
     * so no shape describes this call — the core's own declaration stands and
     * the double is a plain `object`.
     *
     * Typing the key as one member of the union, which is what this used to
     * do, was worse than saying nothing: `now()` type-checked because the
     * member it happened to pick was `Clock`, and the call it type-checked
     * throws `CannotWire` before it is ever made.
     */
    public function aUnionTheCoreRefusesToWire(): void
    {
        $wired = Understudy::wire(Ambiguous::class);

        $wired['doubles']['either']->now();
    }
}
