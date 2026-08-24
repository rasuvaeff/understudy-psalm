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
