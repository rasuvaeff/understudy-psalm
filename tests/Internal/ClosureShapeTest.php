<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\Psalm\Tests\Internal;

use PhpParser\Node\Expr\FuncCall;
use Rasuvaeff\Understudy\Psalm\Internal\ClosureShape;
use Rasuvaeff\Understudy\Psalm\Tests\Support\Parse;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Test;

/**
 * How many calls a specification closure makes, and of what kind.
 *
 * The engine enforces "exactly one direct call" at runtime by throwing
 * `InvalidCallSpecification`; this is the same rule read off the syntax, so a
 * test that cannot possibly work says so before it runs.
 *
 * @internal
 */
#[Test]
#[Covers(ClosureShape::class)]
final class ClosureShapeTest
{
    #[DataProvider('shapeProvider')]
    public function readsTheShapeOfAClosure(string $code, ?string $expectedFragment): void
    {
        $call = Parse::expression($code);

        Assert::instanceOf($call, FuncCall::class);

        $problem = ClosureShape::of($call->getArgs()[0]->value)->problem();

        if ($expectedFragment === null) {
            Assert::null($problem);

            return;
        }

        Assert::string($problem ?? '')->contains($expectedFragment);
    }

    /**
     * The complaints are asserted whole. A message is what a user acts on,
     * and every half of a concatenation in one is a mutant a `contains()`
     * cannot see.
     */
    #[DataProvider('messageProvider')]
    public function saysExactlyWhatIsWrong(string $code, string $expected): void
    {
        $call = Parse::expression($code);

        Assert::instanceOf($call, FuncCall::class);
        Assert::same(ClosureShape::of($call->getArgs()[0]->value)->problem(), $expected);
    }

    public static function messageProvider(): iterable
    {
        yield 'no call at all' => [
            'when(fn () => true);',
            'the closure makes no call on a double, so there is nothing to specify. '
            . 'Call the method you mean: when(fn () => $double->method($argument)).',
        ];
        yield 'two calls' => [
            'when(fn () => $double->find(1) && $double->find(2));',
            'the closure makes 2 calls, and a specification describes exactly one. '
            . 'Split it, or hoist the calls that are not being specified out of the closure.',
        ];
        yield 'a static call' => [
            'when(fn () => Clock::now());',
            'the closure calls a static method, which a double cannot intercept. '
            . 'Inject an instance dependency instead.',
        ];
    }

    public static function shapeProvider(): iterable
    {
        yield 'one call, arrow function' => ['when(fn () => $double->find(1));', null];
        yield 'one call, closure' => ['when(function () use ($double) { $double->find(1); });', null];
        yield 'one nullsafe call' => ['when(fn () => $double?->find(1));', null];

        // A callback handed to something else is that thing's business, and
        // its calls are not this specification's.
        yield 'a nested closure is not descended into' => [
            'when(fn () => $double->each(fn () => $other->touch()));',
            null,
        ];

        // A captor's `->capture()` is a matcher in method-call clothes, not
        // a call being specified — it must not count.
        yield 'a capture is not a call' => ['when(fn () => $double->find($ids->capture()));', null];
        yield 'a capture alone specifies nothing' => ['when(fn () => $ids->capture());', 'nothing to specify'];
        // With arguments it is not our capture(): the contract takes none.
        yield 'a capture with an argument is a call' => [
            'when(fn () => $double->find($camera->capture($frame)));',
            'makes 2 calls',
        ];

        yield 'no call at all' => ['when(fn () => true);', 'nothing to specify'];
        yield 'two calls' => ['when(fn () => $double->find(1) && $double->find(2));', 'makes 2 calls'];
        yield 'three calls' => [
            'when(function () use ($double) { $double->a(); $double->b(); $double->c(); });',
            'makes 3 calls',
        ];
        yield 'a static call a double cannot intercept' => [
            'when(fn () => Clock::now());',
            'static method',
        ];

        // Not a closure literal: a variable, a first-class callable, a
        // string. Nothing to read, and nothing to complain about either.
        yield 'a variable instead of a closure' => ['when($specification);', null];
        yield 'a first-class callable' => ['when($double->find(...));', null];

        // Only one of the calls written below can reach a double: the engine
        // throws on the first one that does and never runs what follows, and a
        // call on `$this` is the test class's own helper. Counting them all
        // reported correct specifications as making too many calls.
        yield 'the double comes from a getter' => ['when(fn () => $this->gate()->find(1));', null];
        yield 'an argument comes from a helper' => ['when(fn () => $double->find($this->id()));', null];
        yield 'the call is wrapped in a helper' => ['when(fn () => $this->pass($double->find(1)));', null];
        yield 'a chain on the double is one specified call' => [
            'when(fn () => $double->head()->tail());',
            null,
        ];
        // The total still answers "no call at all", so a specification that
        // reaches its double only through a helper is not accused of
        // specifying nothing.
        yield 'a helper that reaches the double is not empty' => ['when(fn () => $this->configure());', null];
        // Two calls that could each land on a double are still two.
        yield 'two calls on two doubles' => [
            'when(fn () => $a->find(1) && $b->find(2));',
            'makes 2 calls',
        ];
    }
}
