<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\Psalm;

use Psalm\Plugin\EventHandler\Event\MethodReturnTypeProviderEvent;
use Psalm\Plugin\EventHandler\MethodReturnTypeProviderInterface;
use Psalm\Type\Union;
use Rasuvaeff\Understudy\Psalm\Internal\BuilderType;
use Rasuvaeff\Understudy\Psalm\Internal\WireShape;

/**
 * Every return type on `Understudy` that depends on what was passed in.
 *
 * `when()` and `expect()` in their collision-free static form, which Pest
 * users are told to reach for because Pest owns the global `expect()` — a
 * rule that only knew the free functions would be silent exactly for them.
 * And `wire()`, whose shape is readable from the named class's constructor.
 *
 * @internal
 */
final class StaticBuilderReturnType implements MethodReturnTypeProviderInterface
{
    private const array BUILDERS = [
        'when' => \Rasuvaeff\Understudy\WhenBuilder::class,
        'expect' => \Rasuvaeff\Understudy\ExpectBuilder::class,
    ];

    #[\Override]
    public static function getClassLikeNames(): array
    {
        return [\Rasuvaeff\Understudy\Understudy::class];
    }

    #[\Override]
    public static function getMethodReturnType(MethodReturnTypeProviderEvent $event): ?Union
    {
        $method = $event->getMethodNameLowercase();
        $arguments = $event->getCallArgs();

        if ($method === 'wire') {
            return WireShape::of($arguments, $event->getSource()->getCodebase());
        }

        $builder = self::BUILDERS[$method] ?? null;

        if ($builder === null) {
            return null;
        }

        return BuilderType::of(
            $arguments,
            $builder,
            $event->getContext(),
            $event->getSource()->getCodebase(),
        );
    }
}
