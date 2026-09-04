<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\Psalm\Internal;

/**
 * Whether a called name is one of understudy's specification verbs.
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

    /**
     * The free functions of `functions.php`. `expectSequence()` is one of
     * them: it arms a whole protocol of call closures, and each carries the
     * same matchers `when()` does.
     */
    private const array VERBS = ['when', 'expect', 'expectsequence', 'verify'];

    /**
     * The static form carries the call-closure readers too. `calls()`,
     * `lastCall()` and `verifySequence()` have no free-function spelling, and
     * their closures take the same matchers `when()` does — found by
     * dogfooding on yii3-correlation-id, where the matcher in a `calls()`
     * closure was reported exactly like a leak. A reader added to the core
     * belongs in this list the day it is added: `lastCall()` arrived after
     * the plugin was written and was silently outside every rule until now.
     *
     * Saying that was not enough — `expectSequence()` repeated it, which is
     * two of two. `VerbNamesTest` now walks the core's own surface and fails
     * when a closure-taking verb is missing from either list, so the next one
     * is a red build rather than noise in a consumer's project.
     */
    private const array STATIC_VERBS = [
        'when',
        'expect',
        'expectsequence',
        'verify',
        'calls',
        'lastcall',
        'verifysequence',
    ];

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
