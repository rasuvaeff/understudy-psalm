<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\Psalm\Internal;

/**
 * Where the specification calls of each file are.
 *
 * Line ranges rather than AST nodes: the issue hook is handed a location, not
 * a node, so a range is the only thing the two hooks can compare. A call
 * spanning several lines therefore covers all of them, which is right — its
 * arguments are on those lines.
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
