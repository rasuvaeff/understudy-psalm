<?php

declare(strict_types=1);

namespace Fixture\Namesake;

use Rasuvaeff\Understudy\Arg as Matcher;
use Rasuvaeff\Understudy\Understudy;

use function Rasuvaeff\Understudy\when;

final class Aliased
{
    public function ourMatcherUnderAnotherName(): void
    {
        $gate = Understudy::for(Gate::class);

        when(fn() => $gate->open(Matcher::string()));
    }
}
