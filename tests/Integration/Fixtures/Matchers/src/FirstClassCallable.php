<?php

declare(strict_types=1);

namespace Fixture\Matchers;

use Rasuvaeff\Understudy\Arg;
use Rasuvaeff\Understudy\Understudy;

use function Rasuvaeff\Understudy\when;

/**
 * First-class callable syntax, most of it with no understudy near it.
 *
 * `foo(...)` carries a VariadicPlaceholder where its arguments would be, and
 * php-parser asserts against reading them. A `before` hook that reached for
 * them took the whole Psalm run down — no diagnostics at all, on any file, at
 * any level, whatever the file was about. This fixture keeps every hook of
 * this plugin honest about the syntax it is handed.
 */
final class FirstClassCallable
{
    /**
     * @return list<callable>
     */
    public function callables(Gate $gate): array
    {
        $double = Understudy::for(Gate::class);

        when(static fn(): bool => $double->open(Arg::int()))->returns(true);

        return [
            strlen(...),
            $gate->open(...),
            self::helper(...),
            $double->open(...),
        ];
    }

    public static function helper(): void {}
}
