<?php

declare(strict_types=1);

namespace Fixture\Namesake;

use Fixture\Namesake\Other\Arg;
use Rasuvaeff\Understudy\Understudy;

use function Rasuvaeff\Understudy\when;

final class Foreign
{
    public function insideASpecification(): void
    {
        $gate = Understudy::for(Gate::class);

        when(fn() => $gate->open(Arg::string()));
    }

    public function outsideOne(): void
    {
        $gate = Understudy::for(Gate::class);

        $gate->open(Arg::int());
    }
}
