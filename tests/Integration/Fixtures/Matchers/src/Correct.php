<?php

declare(strict_types=1);

namespace Fixture\Matchers;

use Rasuvaeff\Understudy\Arg;
use Rasuvaeff\Understudy\Understudy;

use function Rasuvaeff\Understudy\when;

/**
 * What the plugin exists for: a matcher standing in for an `int`, inside a
 * specification closure. Psalm must say nothing about this file.
 */
final class Correct
{
    public function setUp(): void
    {
        $gate = Understudy::for(Gate::class);

        when(static fn(): bool => $gate->open(Arg::int(min: 1)));
        when(static fn(): bool => $gate->open(Arg::any()));
        Understudy::when(static fn(): bool => $gate->open(Arg::int()));
    }

    /**
     * The call-closure readers are specification scopes too: `calls()` and
     * `verifySequence()` take the same closure shape and the same matchers
     * as `when()` does. Found by dogfooding on yii3-correlation-id: the
     * matcher in a `calls()` closure was reported exactly like a leak.
     */
    public function readBack(): void
    {
        $gate = Understudy::for(Gate::class);

        Understudy::calls(static fn(): bool => $gate->open(Arg::any()));
        Understudy::lastCall(static fn(): bool => $gate->open(Arg::int(min: 1)));
        Understudy::verifySequence(
            static fn(): bool => $gate->open(Arg::int()),
            static fn(): bool => $gate->open(Arg::int(min: 1)),
        );
    }
}
