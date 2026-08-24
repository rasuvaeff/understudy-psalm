<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\Psalm;

use Psalm\Plugin\EventHandler\Event\FunctionReturnTypeProviderEvent;
use Psalm\Plugin\EventHandler\FunctionReturnTypeProviderInterface;
use Psalm\Type\Union;
use Rasuvaeff\Understudy\Psalm\Internal\BuilderType;

/**
 * Gives `when()` and `expect()` the builder type they really produce, so that
 * `returns()` and `answers()` are checked against the method being specified
 * rather than against `mixed`.
 *
 * @internal
 */
final class BuilderReturnType implements FunctionReturnTypeProviderInterface
{
    private const array BUILDERS = [
        'rasuvaeff\understudy\when' => \Rasuvaeff\Understudy\WhenBuilder::class,
        'rasuvaeff\understudy\expect' => \Rasuvaeff\Understudy\ExpectBuilder::class,
    ];

    /**
     * @return array<lowercase-string>
     */
    #[\Override]
    public static function getFunctionIds(): array
    {
        return array_keys(self::BUILDERS);
    }

    #[\Override]
    public static function getFunctionReturnType(FunctionReturnTypeProviderEvent $event): ?Union
    {
        $builder = self::BUILDERS[$event->getFunctionId()] ?? null;

        if ($builder === null) {
            return null;
        }

        return BuilderType::of(
            $event->getCallArgs(),
            $builder,
            $event->getContext(),
            $event->getStatementsSource()->getCodebase(),
        );
    }
}
