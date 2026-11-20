<?php

declare(strict_types=1);

namespace Fixture\Targets;

use Rasuvaeff\Understudy\Arg;
use Rasuvaeff\Understudy\Understudy;

use function Rasuvaeff\Understudy\when;

/**
 * Doubles reached the ways tests really reach them: through a field set up
 * once, and combined from two contracts. One report per method.
 */
final class Wrong
{
    private Repository $repository;

    public function __construct()
    {
        $this->repository = Understudy::for(Repository::class);
    }

    public function aMatcherThroughAField(): void
    {
        when(fn(): int => $this->repository->find(Arg::string()));
    }

    public function anAnswerThroughAField(): void
    {
        when(fn(): int => $this->repository->find(1))->returns('one');
    }

    public function aMatcherOnTheSecondContract(): void
    {
        $both = Understudy::for(Repository::class, Audit::class);

        when(static fn(): bool => $both->record(Arg::int()));
    }

    public function anAnswerOnTheSecondContract(): void
    {
        $both = Understudy::for(Repository::class, Audit::class);

        when(static fn(): bool => $both->record('opened'))->returns('yes');
    }
}
