<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\Psalm\Tests\Internal;

use Rasuvaeff\Understudy\Psalm\Internal\VerbNames;
use Rasuvaeff\Understudy\Understudy;
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
    /**
     * The one core static that takes a call closure first and is still not
     * a specification: `scope()` runs the test's own code, so claiming it
     * would silence every matcher diagnostic inside the block.
     *
     * `defaults()` needs no entry — its first parameter is the contract, so
     * the sweep never reaches its factory.
     */
    private const array NOT_SPECIFICATIONS = ['scope'];

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
        yield 'imported expectSequence' => ['expectSequence', true];
        yield 'case is not meaningful in PHP' => ['When', true];
        yield 'fully qualified' => ['Rasuvaeff\Understudy\when', true];
        yield 'fully qualified, leading separator' => ['rasuvaeff\understudy\expect', true];
        yield 'fully qualified sequence' => ['Rasuvaeff\Understudy\expectSequence', true];
        // Pest owns a global expect(). It is a different function that happens
        // to share a word, and silencing it would be somebody else's bug.
        yield 'another vendor expect' => ['Pest\expect', false];
        yield 'another vendor when' => ['App\Support\when', false];
        yield 'our namespace, not a verb' => ['Rasuvaeff\Understudy\forwarding', false];
        yield 'not a verb at all' => ['array_map', false];
        // Another vendor's namespace is never ours, whatever its length: the
        // prefix decides, not what happens to sit at the same offset. Without
        // this case the early `return false` is worth nothing — the fall
        // through reads a verb out of a foreign name of the right length.
        yield 'a foreign namespace as long as ours' => ['app\\aaaaaaaaaaaaaaaa\\when', false];
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
        // Built rather than written out: a literal FQCN string is what rector
        // rewrites into `::class`, and `::class` has no leading separator —
        // which is the whole point of this case.
        yield 'a written leading separator' => ['\\' . Understudy::class, 'expect', true];
        yield 'lowercased' => ['rasuvaeff\understudy\understudy', 'verify', true];
        yield 'the sequence form' => [Understudy::class, 'expectSequence', true];
        // Deliberately not a verb: `scope()` takes a call closure first
        // too, but it runs the test's own code, and claiming it would
        // silence every matcher diagnostic inside the block.
        yield 'a callable that is not a specification' => [Understudy::class, 'scope', false];
        yield 'our class, not a verb' => [\Rasuvaeff\Understudy\Understudy::class, 'for', false];
        yield 'a verb on somebody else' => ['App\Testing\Doubles', 'when', false];
        yield 'a namesake class elsewhere' => ['App\Understudy', 'when', false];
    }

    /**
     * Every closure-taking verb the core publishes is known here.
     *
     * Written against the core's own surface rather than a list, because a
     * list is exactly what has failed twice: `lastCall()` was outside every
     * rule until somebody noticed, and `expectSequence()` repeated it. A verb
     * added to the core now fails this build instead of turning into noise in
     * a consumer's project.
     */
    public function everyCallClosureVerbOfTheCoreIsKnown(): void
    {
        foreach (self::coreStaticVerbs() as $method) {
            Assert::true(
                VerbNames::isStaticCall(Understudy::class, $method),
                sprintf('Understudy::%s() takes a call closure and is unknown to VerbNames', $method),
            );
        }

        foreach (self::coreFunctionVerbs() as $function) {
            Assert::true(
                VerbNames::isFunction($function),
                sprintf('%s() takes a call closure and is unknown to VerbNames', $function),
            );
        }
    }

    /**
     * The name left out of that sweep still exists, and still looks like the
     * verb it is not.
     *
     * Without this the exclusion list rots silently: a renamed or removed
     * method would leave a name nobody checks, and the sweep would keep
     * passing while covering less.
     */
    public function theDeliberateNonVerbStillLooksLikeAVerb(): void
    {
        foreach (self::NOT_SPECIFICATIONS as $method) {
            Assert::true(
                self::takesCallClosure(new \ReflectionMethod(Understudy::class, $method)),
                sprintf('Understudy::%s() no longer takes a callable first — drop it from the list', $method),
            );
            Assert::false(VerbNames::isStaticCall(Understudy::class, $method));
        }
    }

    /**
     * Public statics of the core that take a call closure first, minus the
     * ones that deliberately are not specifications.
     *
     * @return list<string>
     */
    private static function coreStaticVerbs(): array
    {
        $verbs = [];

        foreach ((new \ReflectionClass(Understudy::class))->getMethods(\ReflectionMethod::IS_STATIC) as $method) {
            if (!$method->isPublic() || \in_array($method->getName(), self::NOT_SPECIFICATIONS, strict: true)) {
                continue;
            }

            if (self::takesCallClosure($method)) {
                $verbs[] = $method->getName();
            }
        }

        return $verbs;
    }

    /**
     * The same question asked of `functions.php`, whose functions composer
     * loads for us.
     *
     * @return list<string>
     */
    private static function coreFunctionVerbs(): array
    {
        $verbs = [];

        foreach (get_defined_functions()['user'] as $function) {
            if (!str_starts_with($function, VerbNames::NAMESPACE_PREFIX)) {
                continue;
            }

            $reflection = new \ReflectionFunction($function);

            if (self::takesCallClosure($reflection)) {
                $verbs[] = $function;
            }
        }

        return $verbs;
    }

    /**
     * Whether the first parameter is declared `callable` — the shape every
     * specification verb has, and the only one readable without knowing what
     * the verb means.
     */
    private static function takesCallClosure(\ReflectionFunctionAbstract $function): bool
    {
        $first = $function->getParameters()[0] ?? null;
        $type = $first?->getType();

        return $type instanceof \ReflectionNamedType && $type->getName() === 'callable';
    }
}
