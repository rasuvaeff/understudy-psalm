<?php

declare(strict_types=1);

namespace Fixture\Namesake;

interface Gate
{
    public function open(int $code): bool;
}
