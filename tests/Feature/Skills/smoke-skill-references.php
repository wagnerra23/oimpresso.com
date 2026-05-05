<?php

/**
 * Smoke test standalone — valida as 2 skills novas SEM precisar de vendor/Pest.
 *
 * Usado pra validar localmente em worktree fresh sem composer install.
 * NÃO substitui os testes Pest em tests/Feature/Skills/ — só dá feedback rápido.
 *
 * Uso: php tests/Feature/Skills/smoke-skill-references.php
 */

declare(strict_types=1);

const ROOT = __DIR__.'/../../..';

const SKILLS_VALIDADAS = [
    'ads-decision-flow',
    'memoria-recall-flow',
];

$erros    = [];
$contagem = ['skills' => 0, 'links' => 0, 'links_quebrados' => 0, 'classes_check' => 0];

function rel(string $abs): string
{
    $root = realpath(ROOT);

    return str_replace('\\', '/', str_replace($root, '', realpath($abs) ?: $abs));
}

foreach (SKILLS_VALIDADAS as $slug) {
    $skillPath = ROOT."/.claude/skills/$slug/SKILL.md";
    if (! is_file($skillPath)) {
        $erros[] = "❌ Skill não encontrada: $slug ($skillPath)";

        continue;
    }
    $contagem['skills']++;

    $content = file_get_contents($skillPath);

    // 1) Frontmatter presente
    if (! preg_match('/^---\s*\n(.*?)\n---\s*\n/s', $content, $fm)) {
        $erros[] = "❌ $slug: frontmatter ausente";

        continue;
    }
    if (! preg_match('/^name:\s*(\S+)/m', $fm[1], $nm)) {
        $erros[] = "❌ $slug: campo 'name' ausente no frontmatter";
    } elseif ($nm[1] !== $slug) {
        $erros[] = "❌ $slug: name='{$nm[1]}' ≠ pasta";
    }
    if (! preg_match('/^description:\s*(.+)$/m', $fm[1], $desc)) {
        $erros[] = "❌ $slug: campo 'description' ausente";
    } elseif (mb_strlen(trim($desc[1])) < 60) {
        $erros[] = "❌ $slug: description curta demais (".mb_strlen(trim($desc[1])).' chars)';
    }

    // 2) Links relativos resolvem
    if (preg_match_all('/\[([^\]]+)\]\(([^)\s]+)\)/', $content, $links, PREG_SET_ORDER)) {
        foreach ($links as $l) {
            $dest = $l[2];
            if (preg_match('#^https?://#', $dest) || str_starts_with($dest, '#')) {
                continue;
            }
            $contagem['links']++;
            $clean = strtok($dest, '#'); // remove âncora
            // Padrão do projeto: links em SKILL.md são relativos à raiz do repo (como CLAUDE.md)
            $abs = realpath(ROOT.'/'.ltrim($clean, '/\\'));
            if ($abs === false || ! file_exists($abs)) {
                $erros[]                       = "❌ $slug: link quebrado [{$l[1]}]($dest)";
                $contagem['links_quebrados']++;
            }
        }
    }

    // 3) Classes em backtick + link .php → conferir class/interface no arquivo
    if (preg_match_all('/\[`(\w+)(?:::\w+\(\))?`\]\(([^)]+\.php)\)/', $content, $cls, PREG_SET_ORDER)) {
        foreach ($cls as $c) {
            $contagem['classes_check']++;
            $php = realpath(ROOT.'/'.ltrim($c[2], '/\\'));
            if ($php === false || ! file_exists($php)) {
                continue; // já reportado acima
            }
            $body = file_get_contents($php);
            if (! preg_match('/\b(class|interface|trait|enum)\s+'.preg_quote($c[1], '/').'\b/', $body)) {
                $erros[] = "❌ $slug: backtick `{$c[1]}` não encontrado em ".rel($php);
            }
        }
    }
}

// 4) Skill ads-decision-flow cita TODOS os agents reais
$adsAgents = glob(ROOT.'/Modules/ADS/Ai/Agents/*.php') ?: [];
$skillAds  = file_get_contents(ROOT.'/.claude/skills/ads-decision-flow/SKILL.md');
foreach ($adsAgents as $f) {
    $name = basename($f, '.php');
    if (! str_contains($skillAds, $name)) {
        $erros[] = "❌ ads-decision-flow: agent '$name' existe mas não é citado";
    }
}

// 5) Skill memoria-recall-flow cita services críticos
$skillMem = file_get_contents(ROOT.'/.claude/skills/memoria-recall-flow/SKILL.md');
foreach (['MeilisearchDriver', 'HydeQueryExpander', 'LlmReranker', 'NegativeCacheService'] as $svc) {
    if (! str_contains($skillMem, $svc)) {
        $erros[] = "❌ memoria-recall-flow: service crítico '$svc' não citado";
    }
}

// === Resultado ===
echo "Skills validadas: {$contagem['skills']}\n";
echo "Links checados:   {$contagem['links']} ({$contagem['links_quebrados']} quebrados)\n";
echo "Classes checadas: {$contagem['classes_check']}\n";
echo "\n";

if (empty($erros)) {
    echo "✅ Todas as validações passaram.\n";
    exit(0);
}

echo "❌ ".count($erros)." erro(s):\n";
foreach ($erros as $e) {
    echo "  $e\n";
}
exit(1);
