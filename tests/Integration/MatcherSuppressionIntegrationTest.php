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
        // The nested capture too — it was in the fixture and in no assertion,
        // so nothing said whether the plugin was doing anything about it.
        Assert::true($this->countIn($report, 'CaptureShapes.php') > 0);
    }

    public function withThePluginOnlyTheLeakIsReported(): void
    {
        $report = $this->runPsalm('psalm.xml');

        // Matchers inside a specification closure: silent, which is the point.
        Assert::same($this->countIn($report, 'Correct.php'), 0);
        // A capture in a nested position is one too. Recognising the receiver
        // happens when Psalm asks for the method's return type, which can be
        // a different moment than the argument check — the reason the fixture
        // exists, and until now the only thing it did was exist.
        Assert::same($this->countIn($report, 'CaptureShapes.php'), 0);
        // A matcher passed to a real call: still an error, because it is one.
        Assert::true($this->countIn($report, 'Control.php') > 0);
    }

    /**
     * A verb the plugin does not know is not a missing feature — it is noise
     * on correct code, because the suppression the matcher needs is decided
     * by whether the call was recorded as a specification at all.
     *
     * `expectSequence()` was outside every rule until #20, the second verb
     * to be after `lastCall()`. Both of its spellings are exercised in the
     * `Matchers` fixture, and this is what would have caught it: seven
     * reports on a file that must be silent — four `MixedArgument`, three
     * `TooFewArguments` where the `Arg::rest()` tolerance needs both indexes
     * to agree and only one of them was filled.
     */
    public function anArmedProtocolIsASpecificationScopeLikeAnyOther(): void
    {
        $report = $this->runPsalm('psalm.xml');

        Assert::same($this->countIn($report, 'Correct.php'), 0);

        // And the other direction, in the fixture that collects mistakes: a
        // wrong-kind matcher in a protocol step is reported like any other.
        // Five of them — a plain `when()`, a block closure returning the
        // specified call, a `lastCall()` reader, a step of a
        // `verifySequence()` and a step of an armed `expectSequence()`.
        $misuse = $this->runPsalm('psalm.xml', 'Misuse');

        Assert::same(
            count(array_filter(
                $misuse,
                static fn(array $issue): bool => str_contains($issue['message'], '`Arg::string()` matches a string'),
            )),
            5,
        );
    }

    public function everyMisuseIsReportedAndNothingElseIs(): void
    {
        $report = $this->runPsalm('psalm.xml', 'Misuse');

        // Eleven mistakes, eleven reports, all of them ours.
        Assert::same($this->countIn($report, 'Wrong.php'), 11);
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

    /**
     * A refined type is still the kind it refines.
     *
     * The rule used to compare the PRINTED NAME of a parameter's type against
     * the word `string` or `int`, and `non-empty-string` is neither — so
     * `Arg::string()` against it was reported as a pairing no argument could
     * satisfy. Measured on this fixture before the rule moved to Psalm's
     * atomic types: six false reports in the control file, on
     * `non-empty-string`, `class-string`, a literal union, `positive-int`,
     * `int<1, 10>` and `callable`. All six are in `Right.php` now, and the
     * count above is what pins them.
     *
     * The other direction has to keep working, which is what `Wrong.php`'s
     * `Arg::int()` against a `non-empty-string` parameter is for: narrowing a
     * type does not change its kind.
     */
    public function aRefinedParameterTypeIsStillItsOwnKind(): void
    {
        $report = $this->runPsalm('psalm.xml', 'Misuse');

        Assert::same($this->countIn($report, 'Right.php'), 0);

        Assert::same(
            count(array_filter(
                $report,
                static fn(array $issue): bool => str_contains($issue['message'], '`Arg::int()` matches a int'),
            )),
            1,
        );
    }

    public function anAnswerOfTheWrongShapeIsReported(): void
    {
        $report = $this->runPsalm('psalm.xml', 'Returns');

        // Not our own diagnostics: filling in the builder's template
        // parameter is all the plugin does here, and Psalm checks
        // `returns()`/`answers()` against it on its own.
        Assert::same($this->countIn($report, 'Wrong.php'), 5);
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
     * The shape has to agree with what `Wire::resolve()` does with a
     * parameter naming more than one contract, and one half of it did not.
     *
     * An INTERSECTION is one double standing for both contracts, and Psalm
     * holds `A&B` as one `TNamedObject` carrying the rest in `extra_types` —
     * so copying the atomic verbatim keeps both halves callable. That works,
     * and the fixture pins it, because rebuilding the atomic from its name
     * would silently drop the other half.
     *
     * A UNION of two object types is refused outright by the core, and this
     * used to name one of them: `$wired['doubles']['either']->now()`
     * type-checked because the member the atomic map happened to hold first
     * was `Clock`. A call that always throws `CannotWire` passed analysis. No
     * shape is produced for such a class now, so the core's own declaration
     * stands and the call is reported as the unknown it is.
     */
    public function theWireShapeAgreesWithTheCoreOnUnionsAndIntersections(): void
    {
        $report = $this->runPsalm('psalm.xml', 'Wire');

        // The intersection lives in Right.php, with a method of each half
        // called on the key — pinned at zero reports.
        Assert::same($this->countIn($report, 'Right.php'), 0);

        Assert::same(
            count(array_filter(
                $report,
                static fn(array $issue): bool => $issue['type'] === 'MixedMethodCall'
                    && str_contains($issue['message'], "\$wired['doubles']['either']"),
            )),
            1,
        );
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
     * A `capture()` is a matcher written as a method call, and the class it
     * belongs to is what decides whether it is ours. It used to be the method
     * name and an empty argument list, so anybody else's zero-argument
     * `capture()` inside a specification lost its diagnostic.
     */
    public function aForeignCaptureKeepsItsDiagnostic(): void
    {
        $report = $this->runPsalm('psalm.xml', 'Namesake');

        // `Fixture\Namesake\Other\Recorder` is not a captor, and its `mixed`
        // in an `int` parameter is a real problem — the control run raises the
        // same issue with no plugin at all.
        Assert::same(
            $this->typesIn($report, 'ForeignCapture.php'),
            $this->typesIn($this->runPsalm('psalm-without-plugin.xml', 'Namesake'), 'ForeignCapture.php'),
        );
        Assert::true($this->typesIn($report, 'ForeignCapture.php') !== []);
    }

    /**
     * The suppression hook answers by resolution too, and this is the test
     * that says so: it used to pin the opposite, because the hook read the
     * reported source text and an unqualified `Arg::` cannot be traced to a
     * class that way.
     *
     * Both halves were consumer-visible. A foreign namesake inside a
     * specification lost a diagnostic about code that has nothing to do with
     * this package; ours under an alias kept one the plugin exists to remove.
     */
    public function theSuppressionHookJudgesByResolutionToo(): void
    {
        $report = $this->runPsalm('psalm.xml', 'Namesake');

        $types = static fn(string $file): array => array_values(array_map(
            static fn(array $issue): string => $issue['type'],
            array_filter($report, static fn(array $issue): bool => str_ends_with($issue['file_name'], $file)),
        ));

        // Foreign, unqualified, inside a specification closure: its own
        // argument diagnostic is kept, and the control run says it is the
        // same one Psalm raises with no plugin at all.
        Assert::same($types('Foreign.php'), $this->typesIn($this->runPsalm('psalm-without-plugin.xml', 'Namesake'), 'Foreign.php'));
        Assert::true($types('Foreign.php') !== []);

        // Ours under an alias: the class is what counts, not the short name,
        // so no argument diagnostic is left. The misuse verdict beside it is
        // the rules' business and is asserted above.
        Assert::false(\in_array('MixedArgument', $types('Aliased.php'), strict: true));
    }

    /**
     * @param list<array{file_name: string, type: string}> $report
     *
     * @return list<string>
     */
    private function typesIn(array $report, string $file): array
    {
        return array_values(array_map(
            static fn(array $issue): string => $issue['type'],
            array_filter($report, static fn(array $issue): bool => str_ends_with($issue['file_name'], $file)),
        ));
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
