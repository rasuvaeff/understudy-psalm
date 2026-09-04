<?php

declare(strict_types=1);

namespace Fixture\Misuse;

use Rasuvaeff\Understudy\Arg;
use Rasuvaeff\Understudy\Understudy;

use function Rasuvaeff\Understudy\expect;
use function Rasuvaeff\Understudy\verify;
use function Rasuvaeff\Understudy\when;

/**
 * The control group for the rules: each of these is the nearest CORRECT
 * neighbour of a mistake next door. A plugin that reported any of them would
 * be worse than one that reported none, because a false accusation is one a
 * user cannot act on.
 */
final class Right
{
    public function matchersThatFit(): void
    {
        $gate = Understudy::for(Gate::class);

        when(static fn(): bool => $gate->open(Arg::int(min: 1)));
        // `any` matches anything, and the kinds it cannot know stay silent.
        when(static fn(): bool => $gate->open(Arg::any()));
        when(static fn(): bool => $gate->open(Arg::same(7)));
        when(static fn(): bool => $gate->open(Arg::not(Arg::int())));
    }

    /**
     * A refined type is still the kind it refines, and a matcher of that kind
     * can produce a value that fits it. Every one of these used to be
     * reported: the rule compared the printed name of the type against the
     * word `string` or `int`, and `non-empty-string` is neither.
     *
     * A `callable` belongs here too — it is spelled as a string often enough
     * that "no string can be one" would be a false accusation.
     */
    public function matchersThatFitARefinedType(): void
    {
        $gate = Understudy::for(Gate::class);

        when(static fn(): bool => $gate->name(Arg::string()));
        when(static fn(): bool => $gate->bind(Arg::string()));
        when(static fn(): bool => $gate->choose(Arg::string()));
        when(static fn(): bool => $gate->pick(Arg::int()));
        when(static fn(): bool => $gate->level(Arg::int()));
        when(static fn(): bool => $gate->handle(Arg::string()));
    }

    public function boundsThatCanBeMet(): void
    {
        $gate = Understudy::for(Gate::class);

        expect(static fn(): bool => $gate->open(1))->times(2, 5);
        expect(static fn(): bool => $gate->open(2))->times(3);
        expect(static fn(): bool => $gate->open(3))->times(0, 1);
    }

    public function verificationsThatDoNotContradict(): void
    {
        $gate = Understudy::for(Gate::class);
        $gate->open(1);

        verify(static fn(): bool => $gate->open(1), times: 1);
        verify(static fn(): bool => $gate->open(9), never: true);
        verify(static fn(): bool => $gate->open(1), minimum: 1, maximum: 3);
    }

    public function oneCallIsOneSpecification(): void
    {
        $gate = Understudy::for(Gate::class);

        // A nested closure is somebody else's call, not this specification's.
        expect(static fn(): bool => $gate->open(1))->answers(
            static fn(): bool => (new \ArrayObject([]))->count() === 0,
        );
    }
}
