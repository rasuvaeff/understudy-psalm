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
     * Which `Arg` a rule acts on is decided by the resolver, not by the last
     * segment of the written name. Both directions used to be wrong, and both
     * landed in the consumer's own code.
     */
    public function aNamesakeIsNotClaimedAndAnAliasIsStillOurs(): void
    {
        $report = $this->runPsalm('psalm.xml', 'Namesake');

        $ours = static fn(string $file): array => array_values(array_filter(
            $report,
            static fn(array $issue): bool => str_ends_with($issue['file_name'], $file)
                && $issue['type'] === 'UnderstudyMisuse',
        ));

        // `Fixture\Namesake\Other\Arg` is somebody else's class that happens
        // to be named `Arg`. Its methods are not matchers, and judging one
        // against a parameter was a false accusation about code that has
        // nothing to do with this package.
        Assert::same($ours('Foreign.php'), []);

        // Our own `Arg`, imported as `Matcher`. The short name is gone, the
        // class is not: a string matcher in an `int` parameter is a real
        // mistake and is now reported as one.
        Assert::same(\count($ours('Aliased.php')), 1);
    }

    /**
     * What text alone cannot decide, stated rather than implied.
     *
     * `beforeAddIssue` is handed the reported source selection and nothing
     * else, so an unqualified `Arg::` cannot be traced to a class: a foreign
     * one is silenced along with ours, and ours under an alias is not
     * silenced at all. The AST rules above are unaffected — they read the
     * resolver — which is why the misuse verdicts are right in both files
     * while these two argument diagnostics are not.
     */
    public function theSuppressionHookStillJudgesByText(): void
    {
        $report = $this->runPsalm('psalm.xml', 'Namesake');

        $types = static fn(string $file): array => array_values(array_map(
            static fn(array $issue): string => $issue['type'],
            array_filter($report, static fn(array $issue): bool => str_ends_with($issue['file_name'], $file)),
        ));

        // Foreign, unqualified, inside a specification closure: its own
        // InvalidScalarArgument is dropped, as the control run shows it would
        // otherwise be raised.
        Assert::same($types('Foreign.php'), []);
        Assert::true($this->countIn($this->runPsalm('psalm-without-plugin.xml', 'Namesake'), 'Foreign.php') > 0);

        // Ours under an alias: recognised by the rule, unrecognised by the
        // hook, so the argument diagnostic survives next to the misuse.
        Assert::true(\in_array('MixedArgument', $types('Aliased.php'), strict: true));
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
