<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\Psalm\Internal;

/**
 * Whether a called name is one of understudy's three specification verbs.
 *
 * Split out of the hook because this is the whole decision and the hook is
 * only plumbing: reaching it through Psalm would mean building an `Expr`, a
 * `Context`, a `StatementsSource` and a `Codebase` to ask a question about a
 * string.
 *
 * @internal
 */
final class VerbNames
{
    public const string NAMESPACE_PREFIX = 'rasuvaeff\\understudy\\';

    private const array VERBS = ['when', 'expect', 'verify'];

    /**
     * The static form carries the call-closure readers too. `calls()` and
     * `verifySequence()` have no free-function spelling, and their closures
     * take the same matchers `when()` does — found by dogfooding on
     * yii3-correlation-id, where the matcher in a `calls()` closure was
     * reported exactly like a leak.
     */
    private const array STATIC_VERBS = ['when', 'expect', 'verify', 'calls', 'verifysequence'];

    private function __construct() {}

    /**
     * A free function: `when(...)`, or its fully qualified form.
     *
     * An unqualified name is ours because the file imported it — that is what
     * `use function` means, and Psalm resolves what it can. A qualified name
     * from another namespace keeps its own diagnostics: Pest's global
     * `expect()` is a different function that happens to share a word.
     */
    public static function isFunction(string $name): bool
    {
        $lower = strtolower($name);

        if (!str_contains($lower, '\\')) {
            return \in_array($lower, self::VERBS, strict: true);
        }

        if (!str_starts_with($lower, self::NAMESPACE_PREFIX)) {
            return false;
        }

        return \in_array(substr($lower, \strlen(self::NAMESPACE_PREFIX)), self::VERBS, strict: true);
    }

    /**
     * The collision-free static form, `Understudy::when()`. Pest users reach
     * for it because Pest owns the global `expect()`.
     */
    public static function isStaticCall(string $class, string $method): bool
    {
        return strtolower(ltrim($class, '\\')) === self::NAMESPACE_PREFIX . 'understudy'
            && \in_array(strtolower($method), self::STATIC_VERBS, strict: true);
    }
}
