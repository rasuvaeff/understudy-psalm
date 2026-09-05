<?php

declare(strict_types=1);

namespace Fixture\Misuse;

use Rasuvaeff\Understudy\Arg;
use Rasuvaeff\Understudy\Understudy;

use function Rasuvaeff\Understudy\expect;
use function Rasuvaeff\Understudy\expectSequence;
use function Rasuvaeff\Understudy\verify;
use function Rasuvaeff\Understudy\when;

/**
 * Every specification here is one the engine would refuse, or one no run
 * could ever satisfy. Each line is expected to draw exactly one
 * `UnderstudyMisuse`.
 */
final class Wrong
{
    public function wrongKindOfMatcher(): void
    {
        $gate = Understudy::for(Gate::class);

        // `open(int $code)`: no string argument can ever reach it.
        when(static fn(): bool => $gate->open(Arg::string()));
    }

    public function wrongKindOfMatcherInAReturnClosure(): void
    {
        $gate = Understudy::for(Gate::class);

        when(static function () use (&$gate): bool {
            return $gate->open(Arg::string());
        });
    }

    /**
     * Refining a type narrows it, it does not change its kind: no int
     * argument reaches a `non-empty-string` parameter either.
     */
    public function wrongKindOfMatcherAgainstARefinedType(): void
    {
        $gate = Understudy::for(Gate::class);

        when(static fn(): bool => $gate->name(Arg::int()));
    }

    public function nothingIsSpecified(): void
    {
        // No call on a double at all.
        when(static fn(): bool => true);
    }

    public function twoCallsInOneClosure(): void
    {
        $gate = Understudy::for(Gate::class);

        expect(static fn(): bool => $gate->open(1) && $gate->open(2));
    }

    public function swappedBounds(): void
    {
        $gate = Understudy::for(Gate::class);

        expect(static fn(): bool => $gate->open(1))->times(5, 2);
    }

    public function neverAndACount(): void
    {
        $gate = Understudy::for(Gate::class);
        $gate->open(1);

        verify(static fn(): bool => $gate->open(1), times: 3, never: true);
    }

    public function exactAndABound(): void
    {
        $gate = Understudy::for(Gate::class);
        $gate->open(1);

        verify(static fn(): bool => $gate->open(1), times: 3, minimum: 1);
    }

    /**
     * A reader is a specification too. `lastCall()` was added to the core
     * after this plugin was written, and until it was listed as a verb this
     * line drew nothing at all.
     */
    public function wrongKindOfMatcherInAReader(): void
    {
        $gate = Understudy::for(Gate::class);

        Understudy::lastCall(static fn(): bool => $gate->open(Arg::string()));
    }

    /**
     * The third step of a protocol is as checkable as the first — and used
     * to be checked in neither: only the first argument was ever read.
     */
    public function wrongKindOfMatcherLaterInASequence(): void
    {
        $gate = Understudy::for(Gate::class);

        Understudy::verifySequence(
            static fn(): bool => $gate->open(1),
            static fn(): bool => $gate->open(Arg::string()),
        );
    }

    /**
     * The same for an armed protocol, which is the other sequence verb and
     * was outside every rule of this plugin until it was listed (#20).
     */
    public function wrongKindOfMatcherInAnArmedSequence(): void
    {
        $gate = Understudy::for(Gate::class);

        expectSequence(
            static fn(): bool => $gate->open(1),
            static fn(): bool => $gate->open(Arg::string()),
        );
    }

    /**
     * The cardinality check used to read only the first link of the chain, so
     * every one of these was silent while `expect(...)->times(5, 2)` was
     * reported. `->returns(...)->times(...)` is the spelling the engine's own
     * README recommends for a repeated call.
     */
    public function impossibleBoundsLaterInTheChain(): void
    {
        $gate = Understudy::for(Gate::class);

        expect(static fn(): bool => $gate->open(1))->returns(true)->times(5, 2);
        when(static fn(): bool => $gate->open(2))->returns(true)->times(9, 3);
        expect(static fn(): bool => $gate->open(3))->throws(new \RuntimeException())->times(5, 2);
        expect(static fn(): bool => $gate->open(4))->ordered()->times(5, 2);
        expect(static fn(): bool => $gate->open(5))->returns(true)->ordered()->times(5, 2);
    }
}
