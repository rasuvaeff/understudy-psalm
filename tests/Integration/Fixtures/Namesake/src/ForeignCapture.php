<?php

declare(strict_types=1);

namespace Fixture\Namesake;

use Fixture\Namesake\Other\Recorder;
use Rasuvaeff\Understudy\Understudy;

use function Rasuvaeff\Understudy\when;

final class ForeignCapture
{
    public function insideASpecification(): void
    {
        $gate = Understudy::for(Gate::class);
        $recorder = new Recorder();

        when(fn() => $gate->open($recorder->capture()));
    }
}
