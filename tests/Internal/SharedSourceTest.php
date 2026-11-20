<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\Psalm\Tests\Internal;

use Testo\Assert;
use Testo\Codecov\CoversNothing;
use Testo\Test;

/**
 * The analyser-independent logic exists twice — here and in
 * `rasuvaeff/understudy-phpstan` — and the two copies must not drift apart.
 *
 * `Cardinality`, `ClosureShape` and `VerbNames` read nothing but
 * php-parser nodes and strings. Each plugin carries its own copy, because
 * neither depends on the other and the core is not the place for code that
 * needs php-parser. Two copies of a rule with nobody comparing them is how
 * one plugin gained a guard the other lacked, and how one wording of the
 * same complaint split in two.
 *
 * The comparison has to work across two repositories with no shared CI, so
 * it is a fingerprint rather than a diff: the token stream of the three
 * files, with comments and whitespace dropped and the analyser's namespace
 * segment replaced by a placeholder. Comments may differ — each copy speaks
 * about its own analyser — and nothing else may. `PhpToken` rather than a
 * pretty-printer, so that a php-parser minor cannot move the number.
 *
 * When this fails, the code changed on one side. Make the same change in
 * `understudy-phpstan/src/PhpStan/Internal/`, and update the constant in
 * BOTH repositories' copy of this test to the value the failure prints.
 *
 * @internal
 */
#[Test]
#[CoversNothing]
final class SharedSourceTest
{
    private const string FINGERPRINT = 'ce839377a11756cbee01fc641f83449599282dec47f84f9412ba41736ce09e42';

    private const array FILES = ['Cardinality.php', 'ClosureShape.php', 'VerbNames.php'];

    public function theSharedCopiesMatchTheSibling(): void
    {
        Assert::same(
            $this->fingerprint(),
            self::FINGERPRINT,
            'The analyser-independent code changed. Change rasuvaeff/understudy-phpstan the same way '
            . 'and set FINGERPRINT in both repositories to the value shown here.',
        );
    }

    private function fingerprint(): string
    {
        $parts = [];

        foreach (self::FILES as $file) {
            $parts[] = $file;
            $source = file_get_contents(\dirname(__DIR__, 2) . '/src/Psalm/Internal/' . $file);

            if ($source === false) {
                throw new \RuntimeException('Cannot read ' . $file);
            }

            foreach (\PhpToken::tokenize($source) as $token) {
                if ($token->isIgnorable()) {
                    continue;
                }

                $parts[] = str_replace('\\Psalm\\', '\\ANALYSER\\', $token->text);
            }
        }

        return hash('sha256', implode("\n", $parts));
    }
}
