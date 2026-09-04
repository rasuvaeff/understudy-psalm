<?php

declare(strict_types=1);

namespace Fixture\Matchers;

use Rasuvaeff\Understudy\Arg;
use Rasuvaeff\Understudy\Understudy;

use function Rasuvaeff\Understudy\when;

/**
 * Calls that deliberately sit next to a specification. The plugin must use
 * the call's offsets, not a shared source line, when deciding what to hide.
 */
final class Boundary
{
    public function captorFactoryIsNotAMatcher(): void
    {
        $gate = Understudy::for(Gate::class);

        when(static fn(): bool => $gate->open(Arg::captor()));
    }

    public function aRealRestCallOnTheSameLineKeepsItsArityIssue(): void
    {
        $gate = Understudy::for(Gate::class);

        [when(static fn() => $gate->record('svc', Arg::rest())), $gate->record('svc', Arg::rest())];
    }
}
