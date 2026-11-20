<?php

declare(strict_types=1);

namespace Fixture\Kinds;

interface Contract {}

abstract class Book
{
    abstract public function title(): string;
}

abstract class Novel extends Book {}

abstract class Pen {}

interface Shelf
{
    public function book(Book $book): bool;

    public function maybeBook(?Book $book): bool;

    public function contract(Contract $contract): bool;

    public function number(int $number): bool;

    public function text(string $text): bool;

    /**
     * @param array<int> $list
     */
    public function list(array $list): bool;

    /**
     * @param iterable<int> $items
     */
    public function items(iterable $items): bool;

    public function countable(\Countable $countable): bool;

    public function anything(mixed $value): bool;
}
