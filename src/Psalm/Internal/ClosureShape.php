<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\Psalm\Internal;

use PhpParser\Node;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitorAbstract;

/**
 * How many calls a specification closure makes, and of what kind.
 *
 * The engine enforces "exactly one direct call" at runtime by throwing
 * `InvalidCallSpecification`; this is the same rule read off the syntax, so a
 * test that cannot possibly work says so before it runs.
 *
 * Nested closures are not descended into: a callback handed to something else
 * is that thing's business, and its calls are not this specification's.
 *
 * @internal
 */
final class ClosureShape
{
    /**
     * @param int<0, max> $methodCalls   calls on an object, which is what a
     *                                   specification is made of
     * @param int<0, max> $staticCalls   calls on a class, which a double can
     *                                   never intercept
     */
    private function __construct(
        public int $methodCalls,
        public int $staticCalls,
    ) {}

    public static function of(Node $closure): self
    {
        $body = match (true) {
            $closure instanceof ArrowFunction => [$closure->expr],
            $closure instanceof Closure => $closure->stmts,
            default => null,
        };

        if ($body === null) {
            // Not a closure literal — a variable, a first-class callable, a
            // string. Nothing to read, and nothing to complain about either.
            return new self(1, 0);
        }

        $visitor = new class extends NodeVisitorAbstract {
            /** @var int<0, max> */
            public int $methodCalls = 0;

            /** @var int<0, max> */
            public int $staticCalls = 0;

            #[\Override]
            public function enterNode(Node $node): ?int
            {
                if ($node instanceof Closure || $node instanceof ArrowFunction) {
                    return NodeVisitor::DONT_TRAVERSE_CHILDREN;
                }

                if ($node instanceof MethodCall || $node instanceof NullsafeMethodCall) {
                    ++$this->methodCalls;
                }

                if ($node instanceof StaticCall) {
                    ++$this->staticCalls;
                }

                return null;
            }
        };

        $traverser = new NodeTraverser($visitor);
        $traverser->traverse($body);

        return new self($visitor->methodCalls, $visitor->staticCalls);
    }

    /**
     * The complaint, or null when the shape is fine.
     */
    public function problem(): ?string
    {
        if ($this->methodCalls === 0 && $this->staticCalls > 0) {
            return 'the closure calls a static method, which a double cannot intercept. '
                . 'Inject an instance dependency instead.';
        }

        if ($this->methodCalls === 0) {
            return 'the closure makes no call on a double, so there is nothing to specify. '
                . 'Call the method you mean: when(fn () => $double->method($argument)).';
        }

        if ($this->methodCalls > 1) {
            return sprintf(
                'the closure makes %d calls, and a specification describes exactly one. '
                . 'Split it, or hoist the calls that are not being specified out of the closure.',
                $this->methodCalls,
            );
        }

        return null;
    }
}
