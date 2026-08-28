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

    /**
     * A real call ending in `Arg::rest()` is under-arity for real: the
     * engine answers it with `ArgumentCountError`, and the arity report must
     * survive the plugin — the tolerance exists only inside a specification.
     */
    public function underArityLeak(): void
    {
        $gate = Understudy::for(Gate::class);

        $gate->record('svc', Arg::rest());
    }

    public function captorLeak(): void
    {
        $gate = Understudy::for(Gate::class);
        $codes = Arg::captor();

        $gate->open($codes->capture());
    }
}
