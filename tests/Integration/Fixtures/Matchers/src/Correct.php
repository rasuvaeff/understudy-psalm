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
}
