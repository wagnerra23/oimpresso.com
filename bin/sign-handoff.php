<?php

/**
 * ASSINADOR de handoff (HMAC) → payload do tool handoff-submit — PR-6b Loop
 * Zero-Paste (Fase 0 · ADR 0283).
 *
 * O "transporte" do loop zero-paste: a GitHub Action on-push
 * ({@see .github/workflows/handoff-sign-submit.yml}) roda este script pra CADA
 * handoff novo/alterado em `prototipo-ui/handoffs/*.md`, computando
 * `sig = HMAC-SHA256(body, HANDOFF_SECRET)` e emitindo o envelope JSON-RPC do
 * `tools/call` handoff-submit. A Action faz POST no endpoint MCP → pending. O [W]
 * nunca computa HMAC; o segredo vive só no repo secret/servidor (NÃO versionado).
 *
 * **Contrato do `body`** (idêntico ao HandoffIngestService/Command, PR-1): o arquivo
 * é `---\n<yaml>\n---\n<body>`; o `sig` cobre só o `<body>`, com CRLF→LF antes do
 * HMAC — determinístico cross-OS ({@see lição CRLF em writes}).
 *
 * Dependency-free de propósito (igual {@see bin/check-handoff-scope.php}): roda no
 * runner com só `php`, SEM `composer install` (sem vendor/autoload). Por isso o
 * frontmatter é parseado com regex nativo, não Symfony\Yaml.
 *
 * Uso:
 *   HANDOFF_SECRET=… php bin/sign-handoff.php --file=prototipo-ui/handoffs/<slug>.md
 *       → imprime no stdout o JSON-RPC do tools/call handoff-submit (pronto pro POST)
 *   php bin/sign-handoff.php --self-test   # controle-negativo, sem segredo/rede/DB
 *
 * Exit: 0 OK/selftest-verde · 1 erro (self-test vermelho, arquivo/segredo ausente).
 */

declare(strict_types=1);

// ─────────────────────────────────────────────────────────────────────────────
// Núcleo testável (puro) — sem I/O, sem env.
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Separa frontmatter (raw) e corpo, normalizando CRLF→LF ANTES de tudo.
 *
 * @return array{0: string, 1: string} [frontmatterRaw, body] — fm vazio se ausente.
 */
function handoffSplit(string $raw): array
{
    $raw = str_replace("\r\n", "\n", $raw);
    if (! preg_match('/^---\n(.*?)\n---\n(.*)$/s', $raw, $m)) {
        return ['', $raw];
    }

    return [$m[1], $m[2]];
}

/** `sig = HMAC-SHA256(body, secret)` — body normalizado (idempotente). */
function handoffSign(string $body, string $secret): string
{
    return hash_hmac('sha256', str_replace("\r\n", "\n", $body), $secret);
}

/** Lê um escalar do frontmatter raw (1ª ocorrência), sem aspas. */
function handoffScalar(string $fm, string $key): ?string
{
    if (preg_match('/^' . preg_quote($key, '/') . ':\s*(.+?)\s*$/m', $fm, $m)) {
        return trim($m[1], " \t\"'");
    }

    return null;
}

/**
 * Parser do `files:` — inline `[a, b]` ou bloco YAML `- a`. MESMA lógica do
 * {@see bin/check-handoff-scope.php} (consistência: o que assina = o que o
 * scope-guard valida).
 *
 * @return list<string>
 */
function handoffFiles(string $fm): array
{
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

    return $files;
}

/**
 * Monta os `arguments` do tool handoff-submit a partir do conteúdo de um handoff.
 *
 * @return array<string,mixed>
 */
function handoffArguments(string $raw, string $secret): array
{
    [$fm, $body] = handoffSplit($raw);

    return [
        'slug'            => handoffScalar($fm, 'handoff_id') ?? handoffScalar($fm, 'slug') ?? '',
        'body_md'         => $body,
        'sig'             => handoffSign($body, $secret),
        'files_json'      => handoffFiles($fm),
        'tela'            => handoffScalar($fm, 'tela') ?? '',
        'created_by'      => handoffScalar($fm, 'created_by') ?? 'CC',
        'audited_against' => handoffScalar($fm, 'audited_against'),
    ];
}

/**
 * Envelope JSON-RPC 2.0 do `tools/call` handoff-submit (o body do POST).
 *
 * @param  array<string,mixed>  $arguments
 * @return array<string,mixed>
 */
function handoffRpcEnvelope(array $arguments): array
{
    return [
        'jsonrpc' => '2.0',
        'id'      => 1,
        'method'  => 'tools/call',
        'params'  => ['name' => 'handoff-submit', 'arguments' => $arguments],
    ];
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

// ── self-test: controle-negativo, ZERO segredo real/rede/DB ──
if (in_array('--self-test', $args, true)) {
    $fails = [];
    $secret = 'selftest-secret';

    // 1) DETERMINISMO: assinar o mesmo corpo 2× dá a MESMA sig.
    $a = handoffSign("## corpo\nlinha", $secret);
    $b = handoffSign("## corpo\nlinha", $secret);
    if ($a !== $b) {
        $fails[] = 'DETERMINISMO falhou: ' . $a . ' != ' . $b;
    }

    // 2) CRLF: CRLF e LF do MESMO corpo produzem a MESMA sig (normalização).
    if (handoffSign("a\r\nb", $secret) !== handoffSign("a\nb", $secret)) {
        $fails[] = 'CRLF falhou: \r\n e \n deviam dar a mesma sig.';
    }

    // 3) SIG-BATE: a sig do helper bate com hash_hmac cru sobre o body normalizado.
    $body = "## ONDA A\nteste";
    if (handoffSign($body, $secret) !== hash_hmac('sha256', $body, $secret)) {
        $fails[] = 'SIG-BATE falhou: contrato HMAC divergente.';
    }

    // 4) PARSE: inline e bloco YAML produzem a mesma lista de files + slug/tela.
    $inlineRaw = "---\nhandoff_id: caixa\ntela: Atendimento/Caixa\nfiles: [a.css, b.tsx]\ncreated_by: CC\n---\n## body\nx";
    $blockRaw  = "---\nhandoff_id: caixa\ntela: Atendimento/Caixa\nfiles:\n  - a.css\n  - b.tsx\ncreated_by: CC\n---\n## body\nx";
    $ai = handoffArguments($inlineRaw, $secret);
    $ab = handoffArguments($blockRaw, $secret);
    if ($ai['files_json'] !== ['a.css', 'b.tsx'] || $ab['files_json'] !== ['a.css', 'b.tsx']) {
        $fails[] = 'PARSE files falhou: inline=' . json_encode($ai['files_json']) . ' block=' . json_encode($ab['files_json']);
    }
    if ($ai['slug'] !== 'caixa' || $ai['tela'] !== 'Atendimento/Caixa') {
        $fails[] = 'PARSE scalar falhou: slug=' . $ai['slug'] . ' tela=' . $ai['tela'];
    }
    // inline e bloco têm o MESMO body → MESMA sig.
    if ($ai['sig'] !== $ab['sig']) {
        $fails[] = 'PARSE body falhou: sig inline != bloco (body divergiu).';
    }

    // 5) ENVELOPE: forma JSON-RPC do tools/call.
    $env = handoffRpcEnvelope($ai);
    if (($env['method'] ?? null) !== 'tools/call' || ($env['params']['name'] ?? null) !== 'handoff-submit') {
        $fails[] = 'ENVELOPE falhou: ' . json_encode($env);
    }

    if ($fails !== []) {
        fwrite(STDERR, "sign-handoff SELF-TEST 🔴\n" . implode("\n", $fails) . "\n");
        exit(1);
    }
    echo "sign-handoff SELF-TEST 🟢 (determinismo · CRLF→LF · sig-bate · parse inline+bloco · envelope)\n";
    exit(0);
}

// ── modo real: assina UM arquivo e emite o envelope JSON-RPC ──
$file = optValue($args, 'file');
if ($file === null || $file === '') {
    fwrite(STDERR, "✗ Uso: HANDOFF_SECRET=… php bin/sign-handoff.php --file=prototipo-ui/handoffs/<slug>.md\n");
    exit(1);
}

$secret = (string) getenv('HANDOFF_SECRET');
if ($secret === '') {
    fwrite(STDERR, "✗ HANDOFF_SECRET ausente no ambiente — sem segredo não há como assinar.\n");
    exit(1);
}

$raw = @file_get_contents($file);
if ($raw === false) {
    fwrite(STDERR, "✗ Não consegui ler: {$file}\n");
    exit(1);
}

$arguments = handoffArguments($raw, $secret);
if ($arguments['slug'] === '') {
    fwrite(STDERR, "✗ {$file}: frontmatter sem handoff_id/slug — não dá pra submeter.\n");
    exit(1);
}

echo json_encode(handoffRpcEnvelope($arguments), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit(0);
