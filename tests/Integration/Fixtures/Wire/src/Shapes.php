<?php

declare(strict_types=1);

namespace Fixture\Wire;

/**
 * A final class the core cannot double unless `bypassFinals()` was called —
 * a runtime fact the analyser cannot see.
 */
final readonly class SystemClock implements Clock
{
    #[\Override]
    public function now(): int
    {
        return 0;
    }
}

/**
 * `Wire` doubles `$clock` and, when it can, `$fallback`; when it cannot, the
 * parameter keeps its own default and has no key in `doubles`.
 */
final readonly class Stamped
{
    public function __construct(
        private Clock $clock,
        private SystemClock $fallback = new SystemClock(),
    ) {}

    public function now(): int
    {
        return $this->clock->now() + $this->fallback->now();
    }
}

/**
 * An interface with a default is still doubled: the default is never
 * reached.
 */
final readonly class Logged
{
    public function __construct(private ?Clock $clock = null) {}

    public function now(): int
    {
        return $this->clock?->now() ?? 0;
    }
}

/**
 * `Wire` stops at a variadic tail and never doubles it.
 */
final readonly class Fanout
{
    /** @var list<Auditor> */
    private array $auditors;

    public function __construct(
        private Clock $clock,
        Auditor ...$auditors,
    ) {
        $this->auditors = array_values($auditors);
    }

    public function count(): int
    {
        return $this->clock->now() + count($this->auditors);
    }
}

/**
 * Nothing but a variadic tail: `wire()` hands back no double at all.
 */
final readonly class Gathering
{
    /** @var list<Auditor> */
    private array $auditors;

    public function __construct(Auditor ...$auditors)
    {
        $this->auditors = array_values($auditors);
    }

    public function count(): int
    {
        return count($this->auditors);
    }
}
