<?php

/**
 * GUARDA de escopo do handoff (files_json) — PR-5 Loop Zero-Paste (Fase 0 · ADR 0283).
 *
 * Garante que um PR que APLICA um handoff de design só toca arquivos declarados
 * em `prototipo-ui/handoffs/<slug>.md` frontmatter `files:`. Qualquer arquivo do
 * diff fora do escopo BLOQUEIA o PR. É o **A1 (escopo duro)** do adversário [AH]
 * e a "scope-guard files_json" do ADR 0283 (um dos 5 controles do "norte" do
 * auto-merge) — defesa-em-profundidade mesmo com o 1-clique humano da Fase 0.
 *
 * DISTINTO de bin/check-scope.php (que é controller-vs-SCOPE.md, Constituição
 * Art. 7) — este olha o diff vs o files_json de UM handoff.
 *
 * Slug: derivado do branch `handoff/<slug>` (o workflow passa --slug). PR que não
 * é de handoff (branch sem `handoff/`) → não se aplica (skip, exit 0).
 *
 * Uso:
 *   php bin/check-handoff-scope.php --slug=caixa-mobile --strict
 *   php bin/check-handoff-scope.php --slug=X --base=<sha> --head=<sha> --strict
 *   php bin/check-handoff-scope.php --self-test   # controle-negativo (o gate morde?)
 *
 * Exit: 0 OK/skip · 1 diff fora do escopo (--strict) · 2 erro (handoff md ausente).
 */

declare(strict_types=1);

// ─────────────────────────────────────────────────────────────────────────────
// Núcleo testável (puro): arquivos do diff que NÃO estão no escopo permitido.
// ─────────────────────────────────────────────────────────────────────────────
function handoffOutOfScope(array $changed, array $allowed): array
{
    $allowedSet = array_fill_keys(array_map('trim', $allowed), true);
    $out = [];
    foreach ($changed as $f) {
        $f = trim((string) $f);
        if ($f === '') {
            continue;
        }
        if (! isset($allowedSet[$f])) {
            $out[] = $f;
        }
    }

    return array_values(array_unique($out));
}

// Parse do `files:` (inline `[a, b]` ou bloco YAML) do frontmatter do handoff.
function handoffAllowedFiles(string $path): ?array
{
    $content = @file_get_contents($path);
    if ($content === false) {
        return null;
    }
    $content = str_replace("\r\n", "\n", $content);
    if (! preg_match('/^---\n(.*?)\n---\n/s', $content, $m)) {
        return null;
    }
    $fm = $m[1];
    $files = [];

    if (preg_match('/^files:\s*\[(.*?)\]/m', $fm, $im)) {
        foreach (explode(',', $im[1]) as $f) {
            $f = trim($f, " \t\"'");
            if ($f !== '') {
                $files[] = $f;
            }
        }

        return $files;
    }

    if (preg_match('/^files:\s*\n((?:[ \t]+-[ \t]*.+\n?)+)/m', $fm, $bm)) {
        foreach (preg_split('/\n/', $bm[1]) as $line) {
            if (preg_match('/^[ \t]+-[ \t]*"?(.+?)"?[ \t]*$/', $line, $lm)) {
                $files[] = trim($lm[1]);
            }
        }
    }

    return $files; // files: ausente/vazio → [] (nada permitido → todo diff é drift)
}

function handoffChangedFiles(string $base, string $head): array
{
    $cmd = sprintf('git diff --name-only %s...%s 2>/dev/null', escapeshellarg($base), escapeshellarg($head));
    $out = [];
    exec($cmd, $out);

    return array_values(array_filter(array_map('trim', $out), static fn ($f) => $f !== ''));
}

function optValue(array $args, string $key): ?string
{
    foreach ($args as $a) {
        if (str_starts_with($a, "--{$key}=")) {
            return substr($a, strlen("--{$key}="));
        }
    }

    return null;
}

// ─────────────────────────────────────────────────────────────────────────────
$args = array_slice($argv, 1);
$strict = in_array('--strict', $args, true);

// ── self-test: controle-negativo (prova que MORDE o ruim e PASSA o bom) ──
if (in_array('--self-test', $args, true)) {
    $fails = [];

    // 1) BITE: diff toca arquivo fora do escopo → tem de detectar.
    $bad = handoffOutOfScope(['resources/css/cockpit.css', 'app/Evil.php'], ['resources/css/cockpit.css']);
    if ($bad !== ['app/Evil.php']) {
        $fails[] = 'BITE falhou: esperava [app/Evil.php], veio ' . json_encode($bad);
    }

    // 2) CLEAN: diff todo dentro do escopo → zero out-of-scope.
    $good = handoffOutOfScope(['resources/css/cockpit.css'], ['resources/css/cockpit.css', 'x.tsx']);
    if ($good !== []) {
        $fails[] = 'FALSO-POSITIVO: esperava [], veio ' . json_encode($good);
    }

    // 3) PARSE: inline e bloco YAML produzem a mesma lista.
    $tmp = sys_get_temp_dir() . '/handoff-selftest-' . getmypid() . '.md';
    @file_put_contents($tmp, "---\nhandoff_id: t\nfiles: [a.css, b.tsx]\n---\nbody\n");
    $inline = handoffAllowedFiles($tmp);
    @file_put_contents($tmp, "---\nhandoff_id: t\nfiles:\n  - a.css\n  - b.tsx\n---\nbody\n");
    $block = handoffAllowedFiles($tmp);
    @unlink($tmp);
    if ($inline !== ['a.css', 'b.tsx'] || $block !== ['a.css', 'b.tsx']) {
        $fails[] = 'PARSE falhou: inline=' . json_encode($inline) . ' block=' . json_encode($block);
    }

    if ($fails !== []) {
        fwrite(STDERR, "handoff-scope-guard SELF-TEST 🔴\n" . implode("\n", $fails) . "\n");
        exit(1);
    }
    echo "handoff-scope-guard SELF-TEST 🟢 (morde caso ruim · passa caso bom · parse inline+bloco)\n";
    exit(0);
}

$slug = optValue($args, 'slug');
if ($slug === null || $slug === '') {
    echo "ℹ️  Sem --slug (PR não é de handoff: branch não-`handoff/`). Scope-guard não se aplica.\n";
    exit(0);
}

$mdPath = "prototipo-ui/handoffs/{$slug}.md";
$allowed = handoffAllowedFiles($mdPath);
if ($allowed === null) {
    fwrite(STDERR, "✗ Handoff não encontrado: {$mdPath} — o slug do branch `handoff/{$slug}` não casa um handoff ingerido.\n");
    exit(2);
}

$base = optValue($args, 'base') ?? 'origin/main';
$head = optValue($args, 'head') ?? 'HEAD';
$changed = handoffChangedFiles($base, $head);

// O próprio arquivo do handoff pode aparecer no diff — é permitido.
$drift = handoffOutOfScope($changed, array_merge($allowed, [$mdPath]));

echo "Handoff `{$slug}` · escopo declarado: " . count($allowed) . " arquivo(s) · diff: " . count($changed) . " arquivo(s)\n";

if ($drift === []) {
    echo "✓ Diff dentro do escopo declarado do handoff.\n";
    exit(0);
}

fwrite(STDERR, '🔴 ' . count($drift) . " arquivo(s) FORA do escopo do handoff `{$slug}`:\n");
foreach ($drift as $f) {
    fwrite(STDERR, "    → {$f}\n");
}
fwrite(STDERR, 'Permitido (files): ' . implode(', ', $allowed) . "\n");
fwrite(STDERR, "Corrija o PR pra tocar só os arquivos do handoff, OU revise o handoff (nova versão).\n");

exit($strict ? 1 : 0);
