<?php

declare(strict_types=1);

namespace Fixture\Matchers;

interface Gate
{
    public function open(int $code): bool;
}
