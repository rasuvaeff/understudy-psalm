<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\Psalm\Tests\Internal;

use Rasuvaeff\Understudy\Psalm\Internal\VerbNames;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Test;

/**
 * Which names the plugin claims. Getting this wrong in either direction is
 * bad in a different way: too narrow and the plugin does nothing for the form
 * a user actually wrote, too wide and it silences somebody else's function
 * that happens to be called `expect`.
 *
 * @internal
 */
#[Test]
#[Covers(VerbNames::class)]
final class VerbNamesTest
{
    #[DataProvider('functionProvider')]
    public function decidesWhetherAFunctionNameIsOurs(string $name, bool $expected): void
    {
        Assert::same(VerbNames::isFunction($name), $expected);
    }

    public static function functionProvider(): iterable
    {
        yield 'imported when' => ['when', true];
        yield 'imported expect' => ['expect', true];
        yield 'imported verify' => ['verify', true];
        yield 'case is not meaningful in PHP' => ['When', true];
        yield 'fully qualified' => ['Rasuvaeff\Understudy\when', true];
        yield 'fully qualified, leading separator' => ['rasuvaeff\understudy\expect', true];
        // Pest owns a global expect(). It is a different function that happens
        // to share a word, and silencing it would be somebody else's bug.
        yield 'another vendor expect' => ['Pest\expect', false];
        yield 'another vendor when' => ['App\Support\when', false];
        yield 'our namespace, not a verb' => ['Rasuvaeff\Understudy\forwarding', false];
        yield 'not a verb at all' => ['array_map', false];
    }

    #[DataProvider('staticProvider')]
    public function decidesWhetherAStaticCallIsOurs(string $class, string $method, bool $expected): void
    {
        Assert::same(VerbNames::isStaticCall($class, $method), $expected);
    }

    public static function staticProvider(): iterable
    {
        yield 'the static form' => [\Rasuvaeff\Understudy\Understudy::class, 'when', true];
        yield 'leading separator' => [\Rasuvaeff\Understudy\Understudy::class, 'expect', true];
        yield 'lowercased' => ['rasuvaeff\understudy\understudy', 'verify', true];
        yield 'our class, not a verb' => [\Rasuvaeff\Understudy\Understudy::class, 'for', false];
        yield 'a verb on somebody else' => ['App\Testing\Doubles', 'when', false];
        yield 'a namesake class elsewhere' => ['App\Understudy', 'when', false];
    }
}
