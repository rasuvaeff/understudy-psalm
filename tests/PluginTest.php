<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\Psalm\Tests;

use Rasuvaeff\Understudy\Psalm\Plugin;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

/**
 * The plugin loads every source file explicitly because Psalm checks hook
 * classes before Composer autoloading is guaranteed. Keep that list in sync
 * with the source tree so a new hook cannot fail only in a user's Psalm run.
 */
#[Test]
#[Covers(Plugin::class)]
final class PluginTest
{
    public function everyPsalmSourceFileIsLoadedByThePlugin(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__) . '/src/Psalm/Plugin.php');
        preg_match_all("/require_once __DIR__ \. '([^']+)';/", $source, $matches);

        $required = array_map(
            static fn(string $path): string => str_replace('\\', '/', ltrim($path, '/')),
            $matches[1],
        );
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(dirname(__DIR__) . '/src/Psalm', \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || $file->getFilename() === 'Plugin.php' || $file->getExtension() !== 'php') {
                continue;
            }

            $files[] = str_replace('\\', '/', substr($file->getPathname(), strlen(dirname(__DIR__) . '/src/Psalm/')));
        }

        sort($required);
        sort($files);

        Assert::same($required, $files);
    }
}
