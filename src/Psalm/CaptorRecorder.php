<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\Psalm;

use Psalm\Plugin\EventHandler\Event\MethodReturnTypeProviderEvent;
use Psalm\Plugin\EventHandler\MethodReturnTypeProviderInterface;
use Psalm\Type\Union;
use Rasuvaeff\Understudy\Captor;

/**
 * Records where a captor's `capture()` is, with the receiver resolved.
 *
 * It answers `null` to every call, and that is not a stub waiting to be
 * filled in: this hook is here for the receiver, not for the type. A
 * `capture()` is a matcher written as a method call, so `SpecificationScope`
 * cannot recognise it the way it recognises `Arg::` — the name of a method
 * says nothing about the class it belongs to. Psalm has resolved that class by
 * the time it asks for a return type, and `getClassLikeNames()` means the hook
 * is handed nothing else, so the call it is looking at IS ours. All the
 * suppression hook needs is where it is.
 *
 * The obvious alternative does not work here. PHPStan types `capture()` as
 * `never`, which makes the argument acceptable everywhere and needs no
 * suppression at all; Psalm answers a `never`-typed argument with `NoValue` —
 * a false positive inside a specification, and at a real call the right
 * verdict under the wrong issue. Both halves of
 * `MatcherSuppressionIntegrationTest` go red on it. Measured, not assumed;
 * do not re-try it without re-reading this.
 *
 * The one thing this depends on that is not a contract: the return type is
 * asked for before the enclosing call's argument is judged. If that order ever
 * inverts, a diagnostic REAPPEARS inside a specification — noise, not silence,
 * which is the direction a failure here should take.
 *
 * @internal
 */
final class CaptorRecorder implements MethodReturnTypeProviderInterface
{
    /**
     * @return array<string>
     */
    #[\Override]
    public static function getClassLikeNames(): array
    {
        return [Captor::class];
    }

    #[\Override]
    public static function getMethodReturnType(MethodReturnTypeProviderEvent $event): ?Union
    {
        if ($event->getMethodNameLowercase() !== 'capture') {
            return null;
        }

        $call = $event->getStmt();

        SpecificationScope::matcherCalls()->record(
            $event->getSource()->getFilePath(),
            $call->getStartFilePos(),
            $call->getEndFilePos(),
        );

        // The declared `mixed` stands. Answering anything else would be a
        // claim about the value, and this hook has an opinion about the
        // location only.
        return null;
    }
}
