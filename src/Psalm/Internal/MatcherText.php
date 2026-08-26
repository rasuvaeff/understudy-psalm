<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\Psalm\Internal;

/**
 * Whether the source text Psalm complained about is an `Arg::` matcher.
 *
 * Read off the reported selection rather than the AST because the selection
 * is all the issue hook is handed. That is a real limit, and it is the reason
 * this test is narrow: a literal that merely sits inside a specification
 * closure has to keep its diagnostic, so anything that is not recognisably a
 * matcher call is left alone.
 *
 * @internal
 */
final class MatcherText
{
    /**
     * `Arg` unqualified or under its own namespace, then `::`, a method name
     * and an opening parenthesis. The leading boundary stops `MyArg::`,
     * `$arg::` and `Acme\Arg::` from passing as ours — the last of those used
     * to, because any namespace was accepted in front of the name.
     *
     * What text alone still cannot decide is an unqualified `Arg::` in a file
     * that imported somebody else's class under that name. The AST rules read
     * the resolver and are not affected; this hook is handed a selection of
     * source and nothing else, which is why it stays narrow.
     */
    private const string MATCHER = '/(?<![\w$\\\\])(?:\\\\?Rasuvaeff\\\\Understudy\\\\)?Arg\s*::\s*[A-Za-z_]\w*\s*\(/i';

    private function __construct() {}

    public static function looksLikeMatcher(string $selected): bool
    {
        return preg_match(self::MATCHER, $selected) === 1;
    }
}
