<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\Psalm\Tests\Integration;

use Testo\Assert;
use Testo\Codecov\CoversNothing;
use Testo\Test;

/**
 * The feasibility gate the plan puts before every other rule of this plugin
 * (§7): a matcher must stop being an error where it belongs, and must stay an
 * error where it does not.
 *
 * Driven as a real Psalm process over a fixture project, because a plugin
 * that loads and does nothing passes a positive fixture exactly as well as
 * one that works. The control run — the same files, the same config, no
 * plugin — is what tells those two apart.
 *
 * @internal
 */
#[Test]
#[CoversNothing]
final class MatcherSuppressionIntegrationTest
{
    public function withoutThePluginBothFilesAreReported(): void
    {
        // The premise. If Psalm has nothing to say here even without the
        // plugin, the test below proves nothing at all.
        $report = $this->runPsalm('psalm-without-plugin.xml');

        Assert::true($this->countIn($report, 'Correct.php') > 0);
        Assert::true($this->countIn($report, 'Control.php') > 0);
    }

    public function withThePluginOnlyTheLeakIsReported(): void
    {
        $report = $this->runPsalm('psalm.xml');

        // Matchers inside a specification closure: silent, which is the point.
        Assert::same($this->countIn($report, 'Correct.php'), 0);
        // A matcher passed to a real call: still an error, because it is one.
        Assert::true($this->countIn($report, 'Control.php') > 0);
    }

    public function everyMisuseIsReportedAndNothingElseIs(): void
    {
        $report = $this->runPsalm('psalm.xml', 'Misuse');

        // Eight mistakes, eight reports, all of them ours.
        Assert::same($this->countIn($report, 'Wrong.php'), 8);
        Assert::same(
            array_values(array_unique(array_map(
                static fn(array $issue): string => $issue['type'],
                $report,
            ))),
            ['UnderstudyMisuse'],
        );

        // And the control group: each of these is the nearest correct
        // neighbour of a mistake next door. A false accusation here is worse
        // than a missed one, because a user cannot act on it.
        Assert::same($this->countIn($report, 'Right.php'), 0);
    }

    public function anAnswerOfTheWrongShapeIsReported(): void
    {
        $report = $this->runPsalm('psalm.xml', 'Returns');

        // Not our own diagnostics: filling in the builder's template
        // parameter is all the plugin does here, and Psalm checks
        // `returns()`/`answers()` against it on its own.
        Assert::same($this->countIn($report, 'Wrong.php'), 4);
        Assert::same(
            array_values(array_unique(array_map(
                static fn(array $issue): string => $issue['type'],
                $report,
            ))),
            ['InvalidArgument'],
        );

        // Answers that fit, and the shapes the provider declines to judge.
        Assert::same($this->countIn($report, 'Right.php'), 0);
    }

    public function theWireShapeIsReadFromTheConstructor(): void
    {
        $report = $this->runPsalm('psalm.xml', 'Wire');

        $types = array_map(static fn(array $issue): string => $issue['type'], $report);

        // A key the constructor has no parameter for, and a method the
        // contract behind a key does not have.
        Assert::true(\in_array('InvalidArrayOffset', $types, strict: true));
        Assert::true(\in_array('UndefinedInterfaceMethod', $types, strict: true));
        Assert::same($this->countIn($report, 'Right.php'), 0);
    }

    /**
     * @return list<array{file_name: string, type: string}>
     */
    private function runPsalm(string $config, string $fixture = 'Matchers'): array
    {
        $root = dirname(__DIR__, 2);
        $project = __DIR__ . '/Fixtures/' . $fixture;

        $command = sprintf(
            '%s %s --config=%s --root=%s --output-format=json --no-progress --no-cache 2>/dev/null',
            escapeshellarg(PHP_BINARY),
            escapeshellarg($root . '/vendor/bin/psalm'),
            escapeshellarg($project . '/' . $config),
            escapeshellarg($project),
        );

        exec($command, $lines);

        /** @var list<array{file_name: string, type: string}> $report */
        $report = json_decode(implode("\n", $lines), associative: true) ?? [];

        return $report;
    }

    /**
     * @param list<array{file_name: string, type: string}> $report
     */
    private function countIn(array $report, string $file): int
    {
        return count(array_filter(
            $report,
            static fn(array $issue): bool => str_ends_with($issue['file_name'], $file),
        ));
    }
}
