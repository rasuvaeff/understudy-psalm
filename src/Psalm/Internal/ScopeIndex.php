<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\Psalm\Internal;

/**
 * Where something of each file is, as inclusive integer ranges.
 *
 * Ranges rather than AST nodes: the issue hook is handed a location, not a
 * node, so a range is the only thing the two hooks can compare. The unit is
 * the caller's to choose and to state — `SpecificationScope::index()` and
 * `restCalls()` hold line numbers, so a call spanning several lines covers
 * all of them; `matcherCalls()` holds file offsets, because two calls can
 * share a line and only one of them be ours.
 *
 * @internal
 */
final class ScopeIndex
{
    /** @var array<string, list<array{int, int}>> */
    private array $ranges = [];

    public function record(string $file, int $startLine, int $endLine): void
    {
        $this->ranges[$file][] = $startLine <= $endLine
            ? [$startLine, $endLine]
            : [$endLine, $startLine];
    }

    public function covers(string $file, int $line): bool
    {
        foreach ($this->ranges[$file] ?? [] as [$start, $end]) {
            if ($line >= $start && $line <= $end) {
                return true;
            }
        }

        return false;
    }

    public function forget(): void
    {
        $this->ranges = [];
    }
}
