<?php

declare(strict_types=1);

use Rasuvaeff\RectorNamedLiterals\AddNameToLiteralArgumentRector;
use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    // The fixture projects are the test input, not code to improve: the
    // `Misuse` fixture pins that `times(maximum: 5, minimum: 1)` is read by
    // name, and `SortCallLikeNamedArgsRector` would rewrite it into the
    // order that never exercised the bug.
    ->withSkip([__DIR__ . '/tests/Integration/Fixtures'])
    ->withPhpSets(php83: true)
    ->withPreparedSets(deadCode: true, codeQuality: true)
    ->withRules([AddNameToLiteralArgumentRector::class]);
