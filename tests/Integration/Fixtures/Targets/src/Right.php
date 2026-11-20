<?php

declare(strict_types=1);

namespace Fixture\Targets;

use Rasuvaeff\Understudy\Arg;
use Rasuvaeff\Understudy\Understudy;

use function Rasuvaeff\Understudy\when;

/**
 * The same doubles used correctly. A double of two contracts has the methods
 * of both; nothing here may be reported.
 */
final class Right
{
    private Repository $repository;

    public function __construct()
    {
        $this->repository = Understudy::for(Repository::class);
    }

    public function throughAField(): void
    {
        when(fn(): int => $this->repository->find(Arg::int()))->returns(7);
    }

    public function bothContracts(): bool
    {
        $both = Understudy::for(Repository::class, Audit::class);

        when(static fn(): bool => $both->record(Arg::string()))->returns(true);
        when(static fn(): int => $both->find(Arg::int()))->returns(7);

        return $both->record('opened') && $both->find(1) === 7;
    }
}
