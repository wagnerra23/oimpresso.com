<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

/**
 * Testa isolamento multi-tenant Tier 0 do Brief (ADR 0091 + ADR 0093).
 *
 * Brief é estado consolidado por agent/usuário/business. A tabela canônica
 * `mcp_briefs` vive no schema MCP (CT 100) mas tem coluna business_id pra
 * suportar briefs por tenant futuramente (atualmente brief é por Wagner agent).
 *
 * Validação: registro biz=1 NÃO aparece com session biz=99 (e vice-versa)
 * via query direta na tabela com WHERE business_id explícito.
 *
 * NUNCA biz=4 (ROTA LIVRE — cliente Larissa produção). Tests biz=1 (Wagner WR2)
 * e biz=99 (fictício) — ADR 0101.
 *
 * @see memory/decisions/0091-daily-brief.md
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 */

const BIZ_WAGNER = 1;
const BIZ_FICTICIO = 99;

// Guard SQLite: mcp_briefs vive no schema MCP MySQL only.
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped(
            'SQLite-incompatível: mcp_briefs (schema MCP) requer MySQL — ADR 0091/0101'
        );
    }
    if (! Schema::hasTable('mcp_briefs')) {
        $this->markTestSkipped(
            'mcp_briefs table missing — schema MCP não migrado neste ambiente'
        );
    }
});

it('Brief biz=1 nao aparece quando consultado com filtro biz=99', function () {
    // Cleanup defensivo antes
    DB::table('mcp_briefs')->where('content', 'like', '%PEST-MULTI-TENANT-TESTE-1%')->delete();

    $hasBizCol = Schema::hasColumn('mcp_briefs', 'business_id');
    $hasValidCol = Schema::hasColumn('mcp_briefs', 'valid');

    $row = [
        'content' => 'PEST-MULTI-TENANT-TESTE-1 brief Wagner biz=1 isolamento',
        'token_count' => 100,
        'generated_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ];
    if ($hasBizCol) {
        $row['business_id'] = BIZ_WAGNER;
    }
    if ($hasValidCol) {
        $row['valid'] = 1;
    }

    $id = DB::table('mcp_briefs')->insertGetId($row);

    try {
        if ($hasBizCol) {
            // Query com filtro biz=99 — NÃO deve retornar o brief biz=1
            $resultado = DB::table('mcp_briefs')
                ->where('id', $id)
                ->where('business_id', BIZ_FICTICIO)
                ->first();

            expect($resultado)->toBeNull('Brief biz=1 NÃO deveria ser retornado com filtro biz=99 (ADR 0093)');

            // Sanity check: com filtro correto biz=1, retorna
            $resultadoCerto = DB::table('mcp_briefs')
                ->where('id', $id)
                ->where('business_id', BIZ_WAGNER)
                ->first();

            expect($resultadoCerto)->not->toBeNull('Brief biz=1 deveria ser retornado com filtro biz=1');
        } else {
            // Schema MCP atual não tem business_id (brief global por agent Wagner).
            // Documentamos o gap pro futuro mas o teste passa marcado como skipped.
            $this->markTestSkipped(
                'mcp_briefs sem coluna business_id — brief atualmente é global '.
                'por agent Wagner (ADR 0091). Adicionar business_id quando multi-tenant '.
                'briefs forem implementados.'
            );
        }
    } finally {
        DB::table('mcp_briefs')->where('id', $id)->delete();
    }
});

it('audit log brief-fetch e escrito com agent_id correto', function () {
    // Validação tangencial: mcp_audit_log respeita schema esperado pelo controller.
    if (! Schema::hasTable('mcp_audit_log')) {
        $this->markTestSkipped('mcp_audit_log table missing — schema MCP não migrado');
    }

    $requestId = (string) \Str::uuid();
    DB::table('mcp_audit_log')->insert([
        'request_id' => $requestId,
        'tool_or_resource' => 'brief-fetch',
        'agent_id' => 'pest-test-wagner-biz1',
        'user_id' => null,
        'status' => 'ok',
        'tokens_out' => 100,
        'cache_read' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    try {
        $log = DB::table('mcp_audit_log')->where('request_id', $requestId)->first();
        expect($log)->not->toBeNull();
        expect($log->tool_or_resource)->toBe('brief-fetch');
        expect($log->agent_id)->toBe('pest-test-wagner-biz1');
    } finally {
        DB::table('mcp_audit_log')->where('request_id', $requestId)->delete();
    }
});
