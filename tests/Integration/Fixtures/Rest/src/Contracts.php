<?php

declare(strict_types=1);

namespace Fixture\Rest;

interface Storage
{
    /**
     * @param list<string> $kinds
     *
     * @return list<string>
     */
    public function claim(\DateTimeImmutable $at, int $max, array $kinds, int $limit): array;

    public function wide(int $a, int $b = 0, int $c = 0, int $d = 0, int $e = 0): bool;

    public function tag(string $name, string ...$labels): bool;

    public function numbers(int $a, int $b, int $c, int $d): bool;
}

interface Audit
{
    /**
     * @param array<string, string> $context
     */
    public function record(string $event, int $level, string $source, array $context): bool;
}
