<?php

use Modules\Jana\Services\Mcp\AuditChainService;

/**
 * Teste de LOGICA PURA (sem DB, sem app boot) do hash-chain do mcp_audit_log (ADR 0294).
 * Roda no CI (SQLite/qualquer) porque NAO toca banco — chama so metodos estaticos com arrays.
 * A integracao real (trigger MySQL append-only + registrar() transacional) e exercitada no
 * CT 100 (Pest com MySQL), nunca no CI SQLite (proibicoes.md Ambiente).
 *
 * NAO usa `uses(TestCase::class)` — tests/Pest.php nao liga TestCase em Modules/Jana,
 * entao isto roda como teste plano (sem RefreshDatabase). De proposito.
 */
function acsRow(array $over = []): array
{
    return array_merge([
        'id'               => 1,
        'request_id'       => 'req-1',
        'user_id'          => 7,
        'business_id'      => 4,
        'ts'               => '2026-06-20 10:00:00',
        'endpoint'         => 'tools/call',
        'tool_or_resource' => 'brief-fetch',
        'status'           => 'ok',
        'error_code'       => null,
        'mcp_token_id'     => 3,
        'hash_anterior'    => null,
        'hash'             => null,
    ], $over);
}

/** Recomputa uma cadeia HONESTA sobre uma lista ordenada (prev encadeado). */
function acsChain(array $rows): array
{
    $out = [];
    $prev = null;
    foreach ($rows as $r) {
        $r['hash_anterior'] = $prev;
        $r['hash'] = AuditChainService::hash($r, $prev);
        $prev = $r['hash'];
        $out[] = $r;
    }

    return $out;
}

it('hash e deterministico (mesmo input -> mesmo hash)', function () {
    $r = acsRow();
    expect(AuditChainService::hash($r, null))->toBe(AuditChainService::hash($r, null));
});

it('hash muda quando o hash_anterior muda (encadeamento real)', function () {
    $r = acsRow();
    expect(AuditChainService::hash($r, 'aaa'))->not->toBe(AuditChainService::hash($r, 'bbb'));
});

it('nao lanca com campos faltando (FAILSAFE — audit nunca some por erro de hash)', function () {
    expect(AuditChainService::hash([], null))->toBeString()
        ->and(AuditChainService::hash(['status' => 'ok'], null))->toBeString();
});

it('cadeia honesta passa integra', function () {
    $rows = acsChain([acsRow(['id' => 1]), acsRow(['id' => 2, 'status' => 'denied']), acsRow(['id' => 3, 'tool_or_resource' => 'x'])]);
    expect(AuditChainService::verificarCadeia($rows)['ok'])->toBeTrue();
});

it('detecta payload adulterado (recompute diverge do hash armazenado)', function () {
    $rows = acsChain([acsRow(['id' => 1]), acsRow(['id' => 2]), acsRow(['id' => 3])]);
    $rows[1]['status'] = 'error'; // adultera SEM recomputar o hash
    $res = AuditChainService::verificarCadeia($rows);
    expect($res['ok'])->toBeFalse()
        ->and(array_column($res['quebrados'], 'id'))->toContain(2);
});

it('detecta elo quebrado / linha removida (hash_anterior nao bate)', function () {
    $rows = acsChain([acsRow(['id' => 1]), acsRow(['id' => 2]), acsRow(['id' => 3])]);
    array_splice($rows, 1, 1); // remove a linha id=2 -> elo de id=3 quebra
    expect(AuditChainService::verificarCadeia($rows)['ok'])->toBeFalse();
});

it('tolera prefixo legado (hash null pre-0294) e ancora a cadeia dali', function () {
    $legado = acsRow(['id' => 1, 'hash' => null, 'hash_anterior' => null]);
    $novos  = acsChain([acsRow(['id' => 2]), acsRow(['id' => 3])]);
    expect(AuditChainService::verificarCadeia(array_merge([$legado], $novos))['ok'])->toBeTrue();
});
