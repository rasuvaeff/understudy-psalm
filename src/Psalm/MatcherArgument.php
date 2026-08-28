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
     * Only argument-shaped issues. An undefined method inside the same
     * closure is a real mistake and keeps its report. `TooFewArguments` is
     * handled apart, because its legitimacy hangs on `Arg::rest()` rather
     * than on the offending argument's own text.
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

    private const string TOO_FEW = 'TooFewArguments';

    #[\Override]
    public static function beforeAddIssue(BeforeAddIssueEvent $event): ?bool
    {
        $issue = $event->getIssue();
        $type = $issue::getIssueType();
        $location = $issue->code_location;

        // `Arg::rest()` is "the arguments before me matter, the rest of the
        // arity does not" — the engine materialises the omitted parameters
        // with a sentinel, so inside a specification the short call is the
        // idiom, not a mistake. Both indexes must agree: a real call ending
        // in `Arg::rest()` sits outside every specification range and keeps
        // its report, on top of the leak the engine raises at runtime.
        if ($type === self::TOO_FEW) {
            return SpecificationScope::index()->covers($location->file_path, $location->getLineNumber())
                && SpecificationScope::restCalls()->covers($location->file_path, $location->getLineNumber())
                ? false
                : null;
        }

        if (!\in_array($type, self::SUPPRESSED, strict: true)) {
            return null;
        }

        if (!SpecificationScope::index()->covers($location->file_path, $location->getLineNumber())) {
            return null;
        }

        return MatcherText::looksLikeMatcher($location->getSelectedText()) ? false : null;
    }
}
