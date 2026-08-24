<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\Psalm;

use Psalm\Plugin\EventHandler\BeforeAddIssueInterface;
use Psalm\Plugin\EventHandler\Event\BeforeAddIssueEvent;
use Rasuvaeff\Understudy\Psalm\Internal\MatcherText;

/**
 * Drops the one diagnostic the call-closure API cannot avoid on its own.
 *
 * `Arg::int()` is declared `mixed`, because a matcher has to be passable
 * wherever the contract declares anything at all. Inside a specification
 * closure that is exactly right and Psalm cannot know it, so it reports the
 * argument as mixed or invalid. Outside one, the same report is correct and
 * stays.
 *
 * Narrow by construction, and deliberately so — a blanket suppression would
 * hide the mistakes this plugin exists to surface. Three things must hold:
 * the issue is about an argument, the location is inside a recorded
 * specification call, and the offending text is an `Arg::` matcher.
 *
 * @internal
 */
final class MatcherArgument implements BeforeAddIssueInterface
{
    /**
     * Only argument-shaped issues. A `TooFewArguments` or an undefined method
     * inside the same closure is a real mistake and keeps its report.
     */
    private const array SUPPRESSED = [
        'InvalidArgument',
        'MixedArgument',
        'MixedArgumentTypeCoercion',
        'PossiblyInvalidArgument',
        'PossiblyNullArgument',
        'ArgumentTypeCoercion',
        'InvalidScalarArgument',
    ];

    #[\Override]
    public static function beforeAddIssue(BeforeAddIssueEvent $event): ?bool
    {
        $issue = $event->getIssue();

        if (!\in_array($issue::getIssueType(), self::SUPPRESSED, strict: true)) {
            return null;
        }

        $location = $issue->code_location;

        if (!SpecificationScope::index()->covers($location->file_path, $location->getLineNumber())) {
            return null;
        }

        return MatcherText::looksLikeMatcher($location->getSelectedText()) ? false : null;
    }
}
