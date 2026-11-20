<?php

declare(strict_types=1);

namespace Fixture\Targets;

interface Repository
{
    public function find(int $id): int;
}

interface Audit
{
    public function record(string $event): bool;
}
