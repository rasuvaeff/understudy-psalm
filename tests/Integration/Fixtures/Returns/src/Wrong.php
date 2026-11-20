<?php

declare(strict_types=1);

namespace Fixture\Returns;

use Rasuvaeff\Understudy\Understudy;

use function Rasuvaeff\Understudy\when;

/**
 * `open(int $code): bool`. Every answer here is the wrong shape for it, and
 * none of these lines is checkable while `when()` is declared to produce a
 * `WhenBuilder<mixed>`.
 */
final class Wrong
{
    public function aStringWhereABoolIsDeclared(): void
    {
        $gate = Understudy::for(Gate::class);

        when(static fn(): bool => $gate->open(1))->returns('yes');
    }

    public function aWrongAnswerCallback(): void
    {
        $gate = Understudy::for(Gate::class);

        when(static fn(): bool => $gate->open(1))->answers(static fn(): string => 'yes');
    }

    public function aWrongValueAfterAReturnClosure(): void
    {
        $gate = Understudy::for(Gate::class);

        when(static function () use (&$gate): bool {
            return $gate->open(1);
        })->returns('yes');
    }

    public function aWrongValueInASequence(): void
    {
        $gate = Understudy::for(Gate::class);

        // The first is fine; the second is not, and a sequence has to be
        // checked element by element.
        when(static fn(): bool => $gate->open(1))->returns(true, 'no');
    }

    /**
     * A string where an int is declared, and the wrong literal of a literal
     * type. The builder's template parameter carries both, so the analyser
     * reports them on its own wherever it checks argument types — no rule of
     * ours is needed, and one would only say it twice.
     */
    public function aNonNumericStringWhereAnIntIsDeclared(): void
    {
        $gate = Understudy::for(Gate::class);

        when(static fn(): int => $gate->count())->returns('abc');
    }

    public function trueWhereOnlyFalseIsDeclared(): void
    {
        $gate = Understudy::for(Gate::class);

        when(static fn(): bool => $gate->closed())->returns(true);
    }

    public function theStaticFormIsCheckedToo(): void
    {
        $gate = Understudy::for(Gate::class);

        Understudy::when(static fn(): bool => $gate->open(1))->returns(7);
    }
}
