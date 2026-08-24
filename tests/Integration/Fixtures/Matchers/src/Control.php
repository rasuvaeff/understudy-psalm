<?php

declare(strict_types=1);

namespace Fixture\Matchers;

use Rasuvaeff\Understudy\Arg;
use Rasuvaeff\Understudy\Understudy;

/**
 * The control group. The same `mixed` going into the same `int`, but not
 * inside a specification closure — a matcher leaked into a real call, which
 * is a runtime error the plugin must NOT hide.
 *
 * If this file goes quiet, the suppression is too wide and the plugin is
 * worse than not having one.
 */
final class Control
{
    public function leak(): void
    {
        $gate = Understudy::for(Gate::class);

        $gate->open(Arg::int());
    }
}
