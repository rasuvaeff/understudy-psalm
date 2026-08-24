<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\Psalm;

use Psalm\Plugin\PluginEntryPointInterface;
use Psalm\Plugin\RegistrationInterface;
use SimpleXMLElement;

/**
 * Entry point registered through `extra.psalm.pluginClass`, so
 * `vendor/bin/psalm-plugin enable rasuvaeff/understudy-psalm` is all a user
 * has to run.
 *
 * @api
 */
final class Plugin implements PluginEntryPointInterface
{
    #[\Override]
    public function __invoke(RegistrationInterface $registration, ?SimpleXMLElement $config = null): void
    {
        // Psalm checks the handler with `class_exists($handler, false)` —
        // autoloading disabled — and throws "Plugins must be loaded before
        // registration" otherwise. A plugin installed as a Composer package
        // usually gets away with it because Composer's autoloader ran first,
        // but a project analysing itself has no such guarantee, so the files
        // are required here rather than hoped for.
        require_once __DIR__ . '/Internal/VerbNames.php';
        require_once __DIR__ . '/Internal/MatcherText.php';
        require_once __DIR__ . '/Internal/ScopeIndex.php';
        require_once __DIR__ . '/Internal/ClosureShape.php';
        require_once __DIR__ . '/Internal/Cardinality.php';
        require_once __DIR__ . '/Internal/MatcherKind.php';
        require_once __DIR__ . '/Issue/UnderstudyMisuse.php';
        require_once __DIR__ . '/SpecificationScope.php';
        require_once __DIR__ . '/MatcherArgument.php';
        require_once __DIR__ . '/SpecificationRules.php';

        $registration->registerHooksFromClass(SpecificationScope::class);
        $registration->registerHooksFromClass(MatcherArgument::class);
        $registration->registerHooksFromClass(SpecificationRules::class);
    }
}
