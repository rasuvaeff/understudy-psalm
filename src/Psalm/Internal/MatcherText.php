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
     * `Arg` possibly qualified, then `::`, a method name and an opening
     * parenthesis. The leading boundary stops `MyArg::` and `$arg::` from
     * passing as ours.
     */
    private const string MATCHER = '/(?<![\w$])(?:\\\\?[A-Za-z_][\w]*\\\\)*Arg\s*::\s*[A-Za-z_]\w*\s*\(/';

    private function __construct() {}

    public static function looksLikeMatcher(string $selected): bool
    {
        return preg_match(self::MATCHER, $selected) === 1;
    }
}
