<?php

declare(strict_types=1);

namespace Fixture\Misuse;

interface Gate
{
    public function open(int $code): bool;

    /**
     * @param non-empty-string $name
     */
    public function name(string $name): bool;

    /**
     * @param class-string $class
     */
    public function bind(string $class): bool;

    /**
     * @param 'read'|'write' $mode
     */
    public function choose(string $mode): bool;

    /**
     * @param positive-int $id
     */
    public function pick(int $id): bool;

    /**
     * @param int<1, 10> $level
     */
    public function level(int $level): bool;

    /**
     * @param callable(): void $handler
     */
    public function handle(callable $handler): bool;
}
