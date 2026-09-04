<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\Psalm\Internal;

/**
 * Cardinality claims that cannot be satisfied by any run.
 *
 * Read off literals only. `times($min, $max)` with variables is a question
 * about values, not about the specification, and the engine answers it at
 * runtime; a plugin guessing there would be wrong exactly when it mattered.
 *
 * @internal
 */
final class Cardinality
{
    private function __construct() {}

    /**
     * `expect(...)->times($minimum, $maximum)`.
     */
    public static function timesProblem(?int $minimum, ?int $maximum): ?string
    {
        if ($minimum !== null && $minimum < 0) {
            return sprintf('a call cannot happen %d times: the minimum is negative.', $minimum);
        }

        if ($maximum !== null && $maximum < 0) {
            return sprintf('a call cannot happen %d times: the maximum is negative.', $maximum);
        }

        if ($minimum !== null && $maximum !== null && $minimum > $maximum) {
            return sprintf(
                'no run satisfies at least %d calls and at most %d. '
                . 'Did the bounds get swapped?',
                $minimum,
                $maximum,
            );
        }

        return null;
    }

    /**
     * `verify(..., times:, minimum:, maximum:, never:)`, where the arguments
     * are named and can contradict each other outright.
     *
     * @param array<string, int|bool|null> $arguments literal values by name
     */
    public static function verifyProblem(array $arguments): ?string
    {
        $never = $arguments['never'] ?? false;
        $times = $arguments['times'] ?? null;
        $minimum = $arguments['minimum'] ?? null;
        $maximum = $arguments['maximum'] ?? null;

        if ($never === true) {
            foreach (['times', 'minimum', 'maximum'] as $name) {
                if (($arguments[$name] ?? null) !== null) {
                    return sprintf(
                        '`never: true` says the call never happened, and `%s` says how often it did. '
                        . 'Keep the one you mean.',
                        $name,
                    );
                }
            }

            return null;
        }

        if ($times !== null && ($minimum !== null || $maximum !== null)) {
            return '`times` is an exact count, so a `minimum` or `maximum` beside it has nothing left '
                . 'to constrain. Use one or the other.';
        }

        // An exact count of its own used to reach nothing: the bounds below
        // are the `minimum`/`maximum` pair, and `times` was dropped on the
        // way. `verify($call, times: -1)` is the same nonsense as a negative
        // bound, and the engine refuses it in the same breath.
        //
        // Not covered by an integration fixture, and that is the finding
        // rather than an omission: `verify()` declares `int<0, max>|null`, so
        // at the levels where Psalm checks that annotation it reports the
        // argument itself and this rule never speaks. What the rule buys is
        // the levels where it does not — which is why the check belongs here
        // and its test is a unit test.
        if (\is_int($times) && $times < 0) {
            return sprintf('a call cannot happen %d times: the count is negative.', $times);
        }

        return self::timesProblem(
            \is_int($minimum) ? $minimum : null,
            \is_int($maximum) ? $maximum : null,
        );
    }
}
