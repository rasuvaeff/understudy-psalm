<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\Psalm\Internal;

/**
 * Whether a matcher can ever match a parameter of a given type.
 *
 * Deliberately about the KIND, not about assignability. `Arg::int()` cannot
 * match a `string` parameter under any argument, and that is worth saying
 * before the test runs; `Arg::any()` can match anything and never complains.
 * Everything the plugin is not sure about is silent — a false accusation here
 * costs more than a missed one, because the runtime still catches the miss.
 *
 * @internal
 */
final class MatcherKind
{
    /**
     * Matchers that assert a scalar kind, and the atomic type each produces.
     *
     * `same`, `not`, `containing`, `count`, `which`, `instanceOf`,
     * `satisfies`, `any`, `none` and `remaining` are absent on purpose:
     * their argument decides what they match, so the kind is not knowable
     * from the matcher name alone.
     */
    private const array KINDS = [
        'int' => 'int',
        'float' => 'float',
        'bool' => 'bool',
        'string' => 'string',
    ];

    private function __construct() {}

    /**
     * The complaint, or null when this pairing is fine or unknowable.
     *
     * @param non-empty-string $matcher      the `Arg::` method name
     * @param list<string>     $parameterTypes atomic types the parameter accepts
     */
    public static function problem(string $matcher, array $parameterTypes): ?string
    {
        $kind = self::KINDS[strtolower($matcher)] ?? null;

        if ($kind === null || $parameterTypes === []) {
            return null;
        }

        // A parameter that takes anything takes this too.
        foreach ($parameterTypes as $type) {
            if (\in_array($type, ['mixed', 'scalar', 'array-key'], strict: true)) {
                return null;
            }
        }

        foreach ($parameterTypes as $type) {
            if (self::satisfies($kind, $type)) {
                return null;
            }
        }

        return sprintf(
            '`Arg::%s()` matches a %s, and this parameter accepts %s. '
            . 'No argument can satisfy both, so the expectation can never match.',
            $matcher,
            $kind,
            implode('|', $parameterTypes),
        );
    }

    private static function satisfies(string $kind, string $parameterType): bool
    {
        if ($kind === $parameterType) {
            return true;
        }

        return match ($kind) {
            // PHP widens an int to a float wherever one is declared, and the
            // engine follows that: an int argument matches a float parameter.
            'int' => $parameterType === 'float',
            // A literal type is still that kind.
            'string' => str_starts_with($parameterType, 'string('),
            'bool' => \in_array($parameterType, ['true', 'false'], strict: true),
            default => false,
        };
    }
}
