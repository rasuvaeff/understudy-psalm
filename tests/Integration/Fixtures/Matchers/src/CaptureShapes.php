<?php

declare(strict_types=1);

namespace Fixture\Matchers;

use Rasuvaeff\Understudy\Arg;
use Rasuvaeff\Understudy\Understudy;

use function Rasuvaeff\Understudy\when;

/**
 * A capture that is not the argument itself. Recognising the receiver happens
 * when Psalm asks for the method's return type, and a nested position is where
 * that could happen at a different moment than the argument check — so it is
 * pinned rather than assumed.
 */
final class CaptureShapes
{
    public function nestedInsideACombinator(): void
    {
        $gate = Understudy::for(Gate::class);
        $codes = Arg::captor();

        when(static fn(): bool => $gate->open(Arg::allOf($codes->capture(), Arg::int(min: 1))));
    }
}
