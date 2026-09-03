<?php

declare(strict_types=1);

namespace Fixture\Namesake\Other;

/**
 * Somebody else's object with a zero-argument `capture()`. It is not a captor,
 * and its `mixed` in an `int` parameter is a real problem.
 */
final class Recorder
{
    public function capture(): mixed
    {
        return null;
    }
}
