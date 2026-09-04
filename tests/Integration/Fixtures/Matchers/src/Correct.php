<?php

declare(strict_types=1);

namespace Fixture\Matchers;

use Rasuvaeff\Understudy\Arg;
use Rasuvaeff\Understudy\Understudy;

use function Rasuvaeff\Understudy\expectSequence;
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
     * `Arg::rest()` legitimately passes fewer arguments than the contract
     * declares — "the arguments before me matter, the rest of the arity does
     * not" (understudy 0.4). Both the arity report and the matcher's own
     * argument report must go quiet here, and only here.
     */
    public function prefixSpecification(): void
    {
        $gate = Understudy::for(Gate::class);

        when(static fn() => $gate->record('svc', Arg::rest()));
        when(static fn() => $gate->record(Arg::rest()));
    }

    /**
     * A captor's `->capture()` is a matcher written as a method call on the
     * `Captor` that `Arg::captor()` handed back — `capture(): mixed` into a
     * typed parameter must go quiet inside a specification.
     */
    public function captorSpecification(): void
    {
        $gate = Understudy::for(Gate::class);
        $codes = Arg::captor();

        when(static fn(): bool => $gate->open($codes->capture()));
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

    /**
     * An armed protocol is a specification scope like any other, in both of
     * its spellings: `expectSequence()` is the one sequence verb that also
     * has a free function, so it has to be claimed by both lists.
     */
    public function armedProtocol(): void
    {
        $gate = Understudy::for(Gate::class);

        expectSequence(
            static fn(): bool => $gate->open(Arg::int()),
            static fn(): bool => $gate->open(Arg::any()),
        );

        Understudy::expectSequence(
            static fn(): bool => $gate->open(Arg::int(min: 1)),
            static fn() => $gate->record('svc', Arg::rest()),
        );
    }
}
