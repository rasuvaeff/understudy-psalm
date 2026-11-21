<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\Psalm\Issue;

use Psalm\Issue\PluginIssue;

/**
 * A trailing run of `Arg::any()` that `Arg::rest()` would say in one word.
 *
 * A suggestion, not a mistake: the specification works as written. Its own
 * type rather than `UnderstudyMisuse`, so that the two can be configured
 * apart, and reported at `info` by default — the plugin sets that level
 * unless the project's `psalm.xml` already names this issue, in which case
 * the project's choice stands:
 *
 * ```xml
 * <PluginIssue name="UnderstudyRestSuggestion" errorLevel="suppress"/>
 * ```
 *
 * @api
 */
final class UnderstudyRestSuggestion extends PluginIssue {}
