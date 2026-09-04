<?php

declare(strict_types=1);

namespace Rasuvaeff\Understudy\Psalm\Tests\Support;

use PhpParser\Node\Expr;
use PhpParser\Node\Stmt\Expression;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;

/**
 * Source text turned into the nodes a rule is handed.
 *
 * With the name resolver attached, because that is what makes an imported
 * `Understudy::when()` carry the class it means — and reading names is half
 * of what the helpers under test do.
 *
 * @internal
 */
final class Parse
{
    private function __construct() {}

    /**
     * The single expression a snippet is, with names resolved.
     *
     * @param non-empty-string $code without the opening tag
     */
    public static function expression(string $code): Expr
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $statements = $parser->parse("<?php\n" . $code) ?? [];

        $traverser = new NodeTraverser(new NameResolver());
        $statements = $traverser->traverse($statements);

        // A `namespace` declaration owns the statements after it, and a
        // snippet that opens one is exactly how the aliasing cases are
        // written.
        $last = self::lastStatement($statements);

        if (!$last instanceof Expression) {
            throw new \InvalidArgumentException('The snippet does not end in an expression');
        }

        return $last->expr;
    }

    /**
     * @param list<\PhpParser\Node\Stmt> $statements
     */
    private static function lastStatement(array $statements): ?\PhpParser\Node\Stmt
    {
        $last = $statements[\count($statements) - 1] ?? null;

        return $last instanceof \PhpParser\Node\Stmt\Namespace_
            ? self::lastStatement(array_values($last->stmts))
            : $last;
    }
}
