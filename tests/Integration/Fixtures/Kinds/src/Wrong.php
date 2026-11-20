<?php

declare(strict_types=1);

namespace Fixture\Kinds;

use Rasuvaeff\Understudy\Arg;
use Rasuvaeff\Understudy\Understudy;

use function Rasuvaeff\Understudy\when;

/**
 * Matchers that name what they match in their own name or argument, each
 * against a parameter no such value can ever reach. One report per method.
 */
final class Wrong
{
    public function anInstanceOfAnUnrelatedClass(): void
    {
        $shelf = Understudy::for(Shelf::class);

        // A Pen is never a Book: two classes, neither extending the other.
        when(static fn(): bool => $shelf->book(Arg::instanceOf(Pen::class)));
    }

    public function anInstanceOfAnUnrelatedClassIntoANullable(): void
    {
        $shelf = Understudy::for(Shelf::class);

        when(static fn(): bool => $shelf->maybeBook(Arg::instanceOf(Pen::class)));
    }

    public function anInstanceIntoAScalar(): void
    {
        $shelf = Understudy::for(Shelf::class);

        when(static fn(): bool => $shelf->number(Arg::instanceOf(Book::class)));
    }

    public function aGetterOnAString(): void
    {
        $shelf = Understudy::for(Shelf::class);

        when(static fn(): bool => $shelf->text(Arg::which('title', 'Dune')));
    }

    public function anArrayMatcherIntoAnInt(): void
    {
        $shelf = Understudy::for(Shelf::class);

        when(static fn(): bool => $shelf->number(Arg::containing([1])));
    }

    public function anArrayMatcherIntoAnObject(): void
    {
        $shelf = Understudy::for(Shelf::class);

        // `containing()` asks `is_array()`: no object passes, Countable or not.
        when(static fn(): bool => $shelf->book(Arg::containing(['title' => 'Dune'])));
    }

    public function aCountIntoAString(): void
    {
        $shelf = Understudy::for(Shelf::class);

        when(static fn(): bool => $shelf->text(Arg::count(1, 3)));
    }

    public function aCaptorOfAnUnrelatedClass(): void
    {
        $shelf = Understudy::for(Shelf::class);
        $pens = Arg::captor(Pen::class);

        when(static fn(): bool => $shelf->book($pens->capture()));
    }
}
