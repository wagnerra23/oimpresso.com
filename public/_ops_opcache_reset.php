<?php

/**
 * Reset OPcache do PHP-FPM/LSPHP em LiteSpeed (Hostinger prod).
 *
 * Origem 2026-06-05: PR #2274 (ADR 0246 — tipo Outros) mergeou + deploy success,
 * mas ContactController OPcache no LSPHP segurou bytecode antigo do whitelist
 * antes do hotfix. Resultado: `?type=other` rejeitado → fallback `customer`,
 * Wagner/Eliana viam aba "Outros" mostrando registros de Cliente.
 *
 * `php artisan config:clear/cache:clear` rodam em SAPI CLI, NÃO compartilham
 * OPcache com FPM/LSPHP. Pra invalidar cache de bytecode da SAPI servindo HTTP,
 * precisa rodar `opcache_reset()` NO MESMO CONTEXTO FPM (= via HTTP request).
 *
 * Este endpoint roda exatamente isso. Chamado pelo deploy.yml após `Maintenance
 * mode OFF` (curl localhost / oimpresso.com). Idempotente — múltiplas chamadas
 * resetam de novo sem efeito colateral.
 *
 * Proteção (token resolvido em 3 fontes, nesta ordem):
 * - 1) env var `OPCACHE_RESET_TOKEN` (getenv / $_SERVER) — caso LiteSpeed exponha
 * - 2) arquivo `storage/app/opcache_reset_token` (fora do webroot, fora do git,
 *      escrito pelo deploy.yml a partir do GitHub Secret OPCACHE_RESET_TOKEN).
 *      Esta é a fonte CANÔNICA: um script PHP cru (sem bootstrap Laravel) NÃO lê
 *      o `.env` da app via getenv(), então a fonte env quase nunca dispara no
 *      Hostinger/LSPHP — o arquivo é o que torna o reset confiável (ADR 0269).
 * - Sem token correto: 403 · Sem token configurado: 503 (nunca acesso anônimo)
 *
 * @see memory/decisions/0246-tipo-outros-default-migracoes-legacy.md
 * @see memory/decisions/0269-deploy-automatico-build-no-runner.md
 * @see https://www.php.net/manual/en/function.opcache-reset.php
 */

declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$expectedToken = getenv('OPCACHE_RESET_TOKEN') ?: ($_SERVER['OPCACHE_RESET_TOKEN'] ?? '');

if ($expectedToken === '') {
    // Fallback canônico: arquivo escrito pelo deploy fora do webroot e do git.
    $tokenFile = __DIR__ . '/../storage/app/opcache_reset_token';
    if (is_readable($tokenFile)) {
        $expectedToken = trim((string) file_get_contents($tokenFile));
    }
}

if ($expectedToken === '') {
    http_response_code(503);
    echo "OPCACHE_RESET_TOKEN não configurado no servidor (.env / environment).\n";
    echo "Configurar antes de usar.\n";
    exit;
}

$providedToken = $_GET['token'] ?? '';

if (! hash_equals($expectedToken, (string) $providedToken)) {
    http_response_code(403);
    echo "forbidden\n";
    exit;
}

if (! function_exists('opcache_reset')) {
    http_response_code(501);
    echo "OPCACHE_UNAVAILABLE — extensão opcache não carregada nesta SAPI.\n";
    exit;
}

$result = opcache_reset();
$status = opcache_get_status(false);

http_response_code($result ? 200 : 500);
echo $result ? "OPCACHE_RESET_OK\n" : "OPCACHE_RESET_FAIL\n";

if (is_array($status)) {
    $files = $status['opcache_statistics']['num_cached_scripts'] ?? 'n/a';
    $hits  = $status['opcache_statistics']['hits'] ?? 'n/a';
    $miss  = $status['opcache_statistics']['misses'] ?? 'n/a';
    echo "cached_scripts={$files} hits={$hits} misses={$miss}\n";
}
