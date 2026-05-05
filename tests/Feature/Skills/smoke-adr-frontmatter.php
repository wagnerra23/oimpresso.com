<?php

/**
 * Smoke standalone — valida frontmatter + links de ADRs específicas.
 * Usado em worktree fresh sem composer install.
 *
 * Uso: php tests/Feature/Skills/smoke-adr-frontmatter.php <slug-arquivo>
 *      (sem extensão .md)
 */

declare(strict_types=1);

$alvos = $argv[1] ?? null;
if (! $alvos) {
    // default: validar as 2 ADRs novas desta sessão
    $alvos = [
        '0072-maturacao-memoria-team-mcp-openclaw-soa-2026',
        '0073-team-mcp-skills-policies-entidades-governadas',
        '0074-temporal-validity-bi-temporal-time-travel',
        '0075-team-mcp-skills-ui-prompt-management-style',
        '0076-skills-db-primary-git-destino-drift-alert',
    ];
} else {
    $alvos = [$alvos];
}

$root = realpath(__DIR__.'/../../..');
$campos = ['slug', 'number', 'title', 'type', 'status', 'authority', 'lifecycle', 'decided_by', 'decided_at'];
$erros = [];
$totalLinks = 0;
$totalQuebrados = 0;

foreach ($alvos as $slug) {
    $file = "$root/memory/decisions/$slug.md";
    if (! file_exists($file)) {
        $erros[] = "❌ não existe: $file";

        continue;
    }

    $content = file_get_contents($file);

    // Frontmatter
    if (! preg_match('/^---\s*\n(.*?)\n---/s', $content, $m)) {
        $erros[] = "❌ $slug: sem frontmatter";

        continue;
    }
    $presentes = [];
    foreach (explode("\n", $m[1]) as $l) {
        if (preg_match('/^(\w+):/', $l, $mm)) {
            $presentes[] = $mm[1];
        }
    }
    $faltando = array_diff($campos, $presentes);
    if (! empty($faltando)) {
        $erros[] = "❌ $slug: faltando ".implode(',', $faltando);
    }

    // Links — ADRs estão em memory/decisions/, então ../../X resolve pra raiz
    if (preg_match_all('/\[([^\]]+)\]\(([^)\s]+)\)/', $content, $links, PREG_SET_ORDER)) {
        foreach ($links as $l) {
            $dest = $l[2];
            if (preg_match('#^https?://#', $dest) || str_starts_with($dest, '#')) {
                continue;
            }
            $totalLinks++;
            $clean = strtok($dest, '#');
            $abs = realpath(dirname($file).'/'.$clean);
            if ($abs === false || ! file_exists($abs)) {
                $erros[] = "❌ $slug: link quebrado [{$l[1]}]($dest)";
                $totalQuebrados++;
            }
        }
    }

    echo "✓ $slug — frontmatter OK\n";
}

echo "\nLinks: $totalLinks ($totalQuebrados quebrados)\n";

if (! empty($erros)) {
    echo "\n".count($erros)." erro(s):\n";
    foreach ($erros as $e) {
        echo "  $e\n";
    }
    exit(1);
}

echo "✅ Tudo passou.\n";
exit(0);
