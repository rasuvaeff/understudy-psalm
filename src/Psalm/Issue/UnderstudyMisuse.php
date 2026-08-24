<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\Psalm\Issue;

use Psalm\Issue\PluginIssue;

/**
 * A specification that cannot mean what it says.
 *
 * One issue type rather than one per rule, because a user configures the
 * plugin as a whole: `understudy` is either analysing your specifications or
 * it is not, and having to silence `UnderstudyContradictoryCardinality`
 * separately from `UnderstudyEmptyClosure` would be a worse contract than
 * having to silence neither.
 *
 * @api
 */
final class UnderstudyMisuse extends PluginIssue {}
