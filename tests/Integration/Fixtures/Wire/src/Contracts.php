<?php

declare(strict_types=1);

namespace Fixture\Wire;

interface BookRepository
{
    public function find(int $id): ?string;
}

interface Clock
{
    public function now(): int;
}

final readonly class Checkout
{
    public function __construct(
        private BookRepository $books,
        private Clock $clock,
    ) {}

    public function total(): int
    {
        return $this->clock->now() + (int) $this->books->find(1);
    }
}

interface Auditor
{
    public function audit(): bool;
}

/**
 * The core doubles an intersection parameter with one double implementing
 * every contract in it — `Wire::resolve()` sends it down the same branch a
 * single contract takes.
 */
final readonly class Reviewed
{
    public function __construct(
        private BookRepository&Auditor $books,
        private Clock $clock,
    ) {}

    public function total(): int
    {
        return $this->books->audit() ? $this->clock->now() : 0;
    }
}

/**
 * The core refuses this one: `CannotWire::undecidableParameter`, because the
 * union names more than one object type and picking either would be a guess.
 * There is no shape to describe — the call never returns.
 */
final readonly class Ambiguous
{
    public function __construct(private BookRepository|Clock $either) {}

    public function run(): void
    {
        if ($this->either instanceof Clock) {
            $this->either->now();
        }
    }
}
