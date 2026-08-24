<?php

declare(strict_types=1);

namespace Fixture\Misuse;

interface Gate
{
    public function open(int $code): bool;
}
