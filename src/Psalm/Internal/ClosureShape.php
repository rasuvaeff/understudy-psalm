<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\Psalm\Internal;

use PhpParser\Node;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
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
     * Marks a node that is the receiver of a call — set on the way in, read
     * once the whole body has been walked.
     */
    private const string RECEIVER = 'understudy.receiverOfCall';

    /**
     * @param int<0, max> $methodCalls   calls on an object, which is what a
     *                                   specification is made of
     * @param int<0, max> $staticCalls   calls on a class, which a double can
     *                                   never intercept
     * @param int<0, max> $candidateCalls the subset of $methodCalls that could
     *                                   land on a double: the engine throws
     *                                   `InvocationSignal` on the first call
     *                                   that does, and never sees the rest
     */
    private function __construct(
        public int $methodCalls,
        public int $staticCalls,
        public int $candidateCalls,
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
            return new self(1, 0, 1);
        }

        $visitor = new class (self::RECEIVER) extends NodeVisitorAbstract {
            /** @var int<0, max> */
            public int $staticCalls = 0;

            /** @var list<MethodCall|NullsafeMethodCall> */
            public array $calls = [];

            public function __construct(
                private readonly string $receiverAttribute,
            ) {}

            #[\Override]
            public function enterNode(Node $node): ?int
            {
                if ($node instanceof Closure || $node instanceof ArrowFunction) {
                    return NodeVisitor::DONT_TRAVERSE_CHILDREN;
                }

                // `$d->m(...)` inside a specification is a closure the body
                // never calls, and its arguments cannot be read at all.
                if ($node instanceof Node\Expr\CallLike && $node->isFirstClassCallable()) {
                    return null;
                }

                if ($node instanceof MethodCall || $node instanceof NullsafeMethodCall) {
                    // A captor's `->capture()` is a matcher in method-call
                    // clothes, not a call being specified: during recording
                    // it runs for real on the Captor and hands the matcher
                    // over, exactly like an `Arg::` factory. Zero arguments
                    // by contract, which is what keeps this narrow.
                    if (
                        $node->name instanceof Node\Identifier
                        && $node->name->toLowerString() === 'capture'
                        && $node->getArgs() === []
                    ) {
                        return null;
                    }

                    $this->calls[] = $node;
                    // Nodes arrive outermost first, so the receiver is marked
                    // before it is ever visited. php-parser gives a visitor no
                    // parent, and an index by object id would have to answer
                    // the same question with a second data structure.
                    $node->var->setAttribute($this->receiverAttribute, true);
                }

                if ($node instanceof StaticCall) {
                    ++$this->staticCalls;
                }

                return null;
            }
        };

        $traverser = new NodeTraverser($visitor);
        $traverser->traverse($body);

        // Which of those calls could actually reach a double. Two kinds cannot,
        // and counting them is how a correct specification was reported as
        // making too many calls:
        //
        //   - the receiver of another call. `$this->gate()->find(1)` and
        //     `$double->head()->tail()` are one specified call each: the engine
        //     throws on the first call that lands on a double and never runs
        //     what follows.
        //   - a call on `$this`. That is the test class's own helper —
        //     `$gate->find($this->id())`, `$this->passThrough($double->m())` —
        //     and a test is not a double.
        //
        // The total still answers "no call at all", so a specification that
        // reaches its double only through a helper stays silent rather than
        // becoming a new false accusation.
        $candidates = 0;

        foreach ($visitor->calls as $call) {
            if ($call->getAttribute(self::RECEIVER) === true) {
                continue;
            }

            if ($call->var instanceof Variable && $call->var->name === 'this') {
                continue;
            }

            ++$candidates;
        }

        return new self(count($visitor->calls), $visitor->staticCalls, $candidates);
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

        if ($this->candidateCalls > 1) {
            return sprintf(
                'the closure makes %d calls, and a specification describes exactly one. '
                . 'Split it, or hoist the calls that are not being specified out of the closure.',
                $this->candidateCalls,
            );
        }

        return null;
    }
}
