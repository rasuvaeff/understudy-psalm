<?php

declare(strict_types=1);

namespace Fixture\Returns;

interface Gate
{
    public function open(int $code): bool;
}
