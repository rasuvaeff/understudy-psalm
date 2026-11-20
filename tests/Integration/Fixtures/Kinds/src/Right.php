<?php

declare(strict_types=1);

namespace Fixture\Kinds;

use Rasuvaeff\Understudy\Arg;
use Rasuvaeff\Understudy\Understudy;

use function Rasuvaeff\Understudy\when;

/**
 * The nearest correct neighbour of every mistake in `Wrong.php`, and the
 * pairings that are only a maybe. None of these may be reported.
 */
final class Right
{
    public function instances(): void
    {
        $shelf = Understudy::for(Shelf::class);

        when(static fn(): bool => $shelf->book(Arg::instanceOf(Book::class)));
        // A subclass, and a parent: either may be the object passed.
        when(static fn(): bool => $shelf->book(Arg::instanceOf(Novel::class)));
        when(static fn(): bool => $shelf->maybeBook(Arg::instanceOf(Book::class)));
        // An interface on either side is a maybe: a class may implement it,
        // and a double of `Understudy::for(Pen::class, Contract::class)` does.
        when(static fn(): bool => $shelf->contract(Arg::instanceOf(Pen::class)));
        when(static fn(): bool => $shelf->book(Arg::instanceOf(Contract::class)));
        when(static fn(): bool => $shelf->items(Arg::instanceOf(\ArrayIterator::class)));
        when(static fn(): bool => $shelf->anything(Arg::instanceOf(Pen::class)));
    }

    public function anInstanceOfANameNotWrittenOut(string $class): void
    {
        $shelf = Understudy::for(Shelf::class);

        /** @var class-string $class */
        when(static fn(): bool => $shelf->number(Arg::instanceOf($class)));
    }

    public function getters(): void
    {
        $shelf = Understudy::for(Shelf::class);

        when(static fn(): bool => $shelf->book(Arg::which('title', 'Dune')));
        when(static fn(): bool => $shelf->contract(Arg::which('title', 'Dune')));
        when(static fn(): bool => $shelf->items(Arg::which('count', 1)));
    }

    public function arrays(): void
    {
        $shelf = Understudy::for(Shelf::class);

        when(static fn(): bool => $shelf->list(Arg::containing([1])));
        when(static fn(): bool => $shelf->items(Arg::containing([1])));
        when(static fn(): bool => $shelf->list(Arg::count(1)));
        when(static fn(): bool => $shelf->countable(Arg::count(1)));
        // Whether a class implements Countable is not asked: a maybe.
        when(static fn(): bool => $shelf->book(Arg::count(1)));
    }

    public function captors(): void
    {
        $shelf = Understudy::for(Shelf::class);
        $novels = Arg::captor(Novel::class);
        $anything = Arg::captor();

        when(static fn(): bool => $shelf->book($novels->capture()));
        when(static fn(): bool => $shelf->number($anything->capture()));
    }
}
