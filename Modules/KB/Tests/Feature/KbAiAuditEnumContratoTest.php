<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\KB\Http\Controllers\KbAiController;

/**
 * Contrato: o que o KbAiController grava em `mcp_audit_log` cabe nas colunas ENUM.
 *
 * @covers-us US-KB-003
 *   Cobre o critério de auditoria da chamada de IA do KB. NÃO cobre o conteúdo
 *   do payload_summary nem o hash-chain (dono: McpSchemaTest / AuditChainServiceTest).
 *
 * O DEFEITO que este teste trava (levantamento 2026-07-28):
 *   `endpoint` e `status` são ENUM na migration 2026_04_29_100005. O controller
 *   gravava `endpoint='kb.ai.ask'|'kb.ai.summarize'|'kb.ai.suggest-meta'` e
 *   `status='ok_empty'` — nenhum desses valores existe nos enums. Em MySQL strict
 *   o INSERT falhava, o try/catch do controller engolia como "degradação", e a
 *   auditoria dos 3 endpoints de IA do KB simplesmente não era gravada. Um bug
 *   invisível protegendo outro: a chamada degradada era justamente a que sumia.
 *
 * Por que ler o enum VIVO em vez de repetir a lista aqui: uma lista copiada
 * envelhece em silêncio. O dono da verdade é o banco; este teste pergunta a ele.
 *
 * Tier 0: o schema do mcp_audit_log é intocável sem ADR — este teste conforma o
 * CÓDIGO ao schema, nunca o contrário.
 *
 * @see Modules/KB/Http/Controllers/KbAiController.php
 * @see Modules/Jana/Database/Migrations/2026_04_29_100005_create_mcp_audit_log_table.php
 */

/**
 * Extrai os valores permitidos de uma coluna ENUM no MySQL.
 *
 * @return array<int,string>
 */
function kbEnumValoresPermitidos(string $tabela, string $coluna): array
{
    $tipo = DB::selectOne(
        'SELECT COLUMN_TYPE AS t
           FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = ?
            AND COLUMN_NAME = ?',
        [$tabela, $coluna]
    )?->t;

    if ($tipo === null || ! preg_match("/^enum\((.*)\)$/i", $tipo, $m)) {
        return [];
    }

    return array_map(
        static fn (string $v): string => trim($v, "'"),
        str_getcsv($m[1], ',', "'")
    );
}

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'mysql') {
        // SQLite guarda ENUM como texto livre e aceita qualquer string — o defeito
        // é INVISÍVEL lá. Este contrato só tem sentido contra MySQL (CT 100 / CI).
        $this->markTestSkipped('Contrato de ENUM só é verificável em MySQL (rodar no CT 100).');
    }

    if (! Schema::hasTable('mcp_audit_log')) {
        $this->markTestSkipped('Tabela mcp_audit_log ausente neste banco.');
    }
});

it('o endpoint gravado pelo KbAiController existe no enum vivo da coluna', function () {
    $permitidos = kbEnumValoresPermitidos('mcp_audit_log', 'endpoint');

    expect($permitidos)->not->toBeEmpty(); // guarda anti-vácuo: o teste mediu de fato

    expect($permitidos)->toContain(KbAiController::AUDIT_ENDPOINT);
});

it('os status gravados pelo KbAiController existem no enum vivo da coluna', function () {
    $permitidos = kbEnumValoresPermitidos('mcp_audit_log', 'status');

    expect($permitidos)->not->toBeEmpty(); // guarda anti-vácuo

    expect($permitidos)->toContain(KbAiController::AUDIT_STATUS_OK);
    expect($permitidos)->toContain(KbAiController::AUDIT_STATUS_DEGRADED);
});

it('os valores legados do controller NAO cabiam no enum (prova do defeito)', function () {
    $endpoints = kbEnumValoresPermitidos('mcp_audit_log', 'endpoint');
    $status    = kbEnumValoresPermitidos('mcp_audit_log', 'status');

    expect($endpoints)->not->toBeEmpty();
    expect($status)->not->toBeEmpty();

    // Estes eram os valores gravados até 2026-07-28. Documentam POR QUE a
    // auditoria do KB IA nunca aparecia: o INSERT não tinha como passar.
    foreach (['kb.ai.ask', 'kb.ai.summarize', 'kb.ai.suggest-meta'] as $legado) {
        expect($endpoints)->not->toContain($legado);
    }

    expect($status)->not->toContain('ok_empty');
});

it('error_code de degradacao cabe no varchar(50) da coluna', function () {
    $tamanho = DB::selectOne(
        'SELECT CHARACTER_MAXIMUM_LENGTH AS n
           FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = ?
            AND COLUMN_NAME = ?',
        ['mcp_audit_log', 'error_code']
    )?->n;

    expect($tamanho)->not->toBeNull();
    expect(strlen(KbAiController::AUDIT_ERROR_CODE))->toBeLessThanOrEqual((int) $tamanho);
});
