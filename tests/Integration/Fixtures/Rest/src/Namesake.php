<?php

declare(strict_types=1);

namespace Fixture\Rest\Other;

/**
 * Somebody else's `Arg`, with an `any()` of its own.
 */
final class Arg
{
    public static function any(): int
    {
        return 0;
    }
}
