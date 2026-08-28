<?php

declare(strict_types=1);

namespace Fixture\Matchers;

interface Gate
{
    public function open(int $code): bool;

    public function record(string $service, int $outcome, bool $admitted, string $attemptId): void;
}
