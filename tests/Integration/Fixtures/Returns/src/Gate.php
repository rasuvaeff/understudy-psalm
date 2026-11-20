<?php

declare(strict_types=1);

namespace Fixture\Returns;

interface Gate
{
    public function open(int $code): bool;

    public function count(): int;

    public function closed(): false;
}
