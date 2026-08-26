<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\Psalm\Internal;

use Rasuvaeff\Understudy\Arg;

/**
 * Whether a resolved class name is the `Arg` this plugin knows about.
 *
 * Compared against the resolved name rather than the written one, and in
 * full: somebody else's `Acme\Console\Arg` is not ours no matter how its name
 * ends, and our own `Arg` is still ours when a file imports it under another
 * name. Reading the short name alone gets both of those backwards, and both
 * mistakes land in a consumer's own code.
 *
 * @internal
 */
final class MatcherClass
{
    private function __construct() {}

    public static function isOurs(string $resolved): bool
    {
        return strtolower(ltrim($resolved, '\\')) === strtolower(Arg::class);
    }
}
