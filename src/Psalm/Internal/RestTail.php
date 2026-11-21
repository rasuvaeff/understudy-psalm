<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\Psalm\Internal;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\StaticCall;

/**
 * A run of `Arg::any()` at the end of a specified call, which `Arg::rest()`
 * would say in one word.
 *
 * Only where the two are the same specification. The engine collects a
 * non-variadic method's arguments by name, so every call it records carries
 * exactly the declared arity; an `Arg::any()` per remaining position and one
 * `Arg::rest()` then accept the same calls. A variadic method is the
 * exception: its tail has a length, `any()` pins it and `rest()` does not.
 * A call that spells fewer arguments than the method requires, or more than
 * it declares, is a mistake of its own, and a named or unpacked argument
 * puts positions where the written order does not say.
 *
 * Three in a row, not two. `find(7, Arg::any(), Arg::any())` reads its
 * arity off the page and `rest()` saves one word; from three on, the run is
 * the noise the suggestion is for — and three is where the review that asked
 * for it started counting.
 *
 * Analyser-independent: the caller answers what the matcher is called and
 * what the method declares; this reads nothing but php-parser nodes.
 *
 * @internal
 */
final class RestTail
{
    /**
     * The shortest run worth a suggestion.
     */
    public const int MINIMUM = 3;

    private function __construct() {}

    /**
     * The position of the first `Arg::any()` of the trailing run and the
     * run's length, or null when there is nothing to suggest.
     *
     * @param list<Arg>                   $arguments the specified call's arguments, as written
     * @param \Closure(Expr): ?string     $matcher   the `Arg::` method name, when the expression is ours
     * @param int<0, max>                 $required  how many parameters the method requires
     * @param int<0, max>                 $declared  how many parameters the method declares
     *
     * @return array{int<0, max>, int<3, max>}|null
     */
    public static function of(array $arguments, \Closure $matcher, int $required, int $declared, bool $variadic): ?array
    {
        $count = count($arguments);

        if ($variadic || $count < $required || $count > $declared) {
            return null;
        }

        foreach ($arguments as $argument) {
            if ($argument->name !== null || $argument->unpack) {
                return null;
            }
        }

        $start = $count;

        while ($start > 0 && self::isAny($arguments[$start - 1]->value, $matcher)) {
            --$start;
        }

        $length = $count - $start;

        return $length >= self::MINIMUM ? [$start, $length] : null;
    }

    /**
     * @param int<3, max> $length
     */
    public static function message(int $length): string
    {
        return sprintf(
            'The last %d arguments are all `Arg::any()` and can be one `Arg::rest()`. '
            . 'The method has no variadic parameter, so `rest()` matches exactly the same calls '
            . 'and says outright that the arguments from here on do not matter.',
            $length,
        );
    }

    /**
     * `Arg::any()` called, not `Arg::any(...)` taken as a closure.
     *
     * @param \Closure(Expr): ?string $matcher
     */
    private static function isAny(Expr $value, \Closure $matcher): bool
    {
        return $value instanceof StaticCall
            && !$value->isFirstClassCallable()
            && strtolower($matcher($value) ?? '') === 'any';
    }
}
