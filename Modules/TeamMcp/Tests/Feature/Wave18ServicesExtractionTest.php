<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Modules\TeamMcp\Http\Requests\ExportUsageCsvRequest;
use Modules\TeamMcp\Http\Requests\UpdateQuotaRequest;
use Modules\TeamMcp\Services\McpTokenIssuer;
use Modules\TeamMcp\Services\TeamUsageAggregator;
use Modules\TeamMcp\Services\UsageCsvExporter;

uses(Tests\TestCase::class);

/**
 * Wave 18 — TeamMcp D4 + D8 SATURATION (2026-05-16).
 *
 * Smoke das 3 Services extraídas + 2 FormRequests novos:
 *   D4: TeamUsageAggregator + McpTokenIssuer + UsageCsvExporter
 *   D8: UpdateQuotaRequest + ExportUsageCsvRequest
 *
 * Tier 0 segredo ({@see ADR 0081}): McpTokenIssuer NUNCA loga raw — span attrs
 * só carregam target_user_id + token_id.
 *
 * NUNCA usar biz=4 (ROTA LIVRE) — ADR 0101. Wagner aqui é superadmin/L0 dev.
 */
describe('Wave 18 — Services extracted (D4)', function () {
    it('TeamUsageAggregator carrega via container', function () {
        $svc = app(TeamUsageAggregator::class);
        expect($svc)->toBeInstanceOf(TeamUsageAggregator::class);
    });

    it('McpTokenIssuer carrega via container + expõe issue/revoke/countActive', function () {
        $svc = app(McpTokenIssuer::class);
        expect($svc)->toBeInstanceOf(McpTokenIssuer::class);

        $ref = new ReflectionClass($svc);
        expect($ref->hasMethod('issue'))->toBeTrue();
        expect($ref->hasMethod('revoke'))->toBeTrue();
        expect($ref->hasMethod('countActive'))->toBeTrue();
    });

    it('UsageCsvExporter carrega via container + expõe streamCsv', function () {
        $svc = app(UsageCsvExporter::class);
        expect($svc)->toBeInstanceOf(UsageCsvExporter::class);

        $ref = new ReflectionClass($svc);
        expect($ref->hasMethod('streamCsv'))->toBeTrue();
    });

    it('McpTokenIssuer::countActive retorna int >= 0 pra user inexistente', function () {
        // Guard de schema: countActive faz SELECT em mcp_tokens. Sem a tabela
        // (schema parcial / dump incompleto) o teste estourava QueryException
        // (ERROR) em vez de SKIP. Os smokes de container/reflection acima NÃO
        // tocam DB e seguem rodando — por isso o guard é por-teste, não no topo.
        if (! Schema::hasTable('mcp_tokens')) {
            $this->markTestSkipped('Tabela mcp_tokens ausente — rode migrate:fresh contra o dump completo.');
        }
        $svc = app(McpTokenIssuer::class);
        $count = $svc->countActive(99999999); // user inexistente — sem tokens
        expect($count)->toBe(0);
    });

    it('McpTokenIssuer::revoke retorna false pra token inexistente (idempotência)', function () {
        // revoke faz McpToken::find() (SELECT em mcp_tokens) antes de qualquer
        // efeito — sem a tabela vira ERROR. Guard por-teste pelo mesmo motivo acima.
        if (! Schema::hasTable('mcp_tokens')) {
            $this->markTestSkipped('Tabela mcp_tokens ausente — rode migrate:fresh contra o dump completo.');
        }
        $svc = app(McpTokenIssuer::class);
        $applied = $svc->revoke(99999999); // token inexistente
        expect($applied)->toBeFalse();
    });

    it('TeamUsageAggregator::globalStats retorna array com 4 chaves', function () {
        // globalStats() faz 4 SELECTs em mcp_audit_log — mesma exposição de schema
        // que countActive/revoke, só que em outra tabela. Guard por-teste pra SKIP
        // limpo quando o dump não trouxe mcp_audit_log.
        if (! Schema::hasTable('mcp_audit_log')) {
            $this->markTestSkipped('Tabela mcp_audit_log ausente — rode migrate:fresh contra o dump completo.');
        }
        // Wave 18 — só smoke estrutural, sem rodar pesado em mcp_audit_log
        $svc = app(TeamUsageAggregator::class);
        $stats = $svc->globalStats();

        expect($stats)->toBeArray();
        expect($stats)->toHaveKeys(['custo_hoje_brl', 'custo_mes_brl', 'usuarios_ativos_hoje', 'calls_hoje']);
    });
});

describe('Wave 18 — FormRequests novos (D8)', function () {
    it('UpdateQuotaRequest expõe rules + authorize', function () {
        expect(class_exists(UpdateQuotaRequest::class))->toBeTrue();

        $req = new UpdateQuotaRequest();
        $rules = $req->rules();

        expect($rules)->toHaveKey('period');
        expect($rules)->toHaveKey('limit_brl');
        expect($rules['period'])->toContain('in:daily,monthly');
        expect($rules['limit_brl'])->toContain('max:9999.99');
    });

    it('ExportUsageCsvRequest expõe rules + rangeOrDefaults', function () {
        expect(class_exists(ExportUsageCsvRequest::class))->toBeTrue();

        $req = new ExportUsageCsvRequest();
        $rules = $req->rules();

        expect($rules)->toHaveKey('de');
        expect($rules)->toHaveKey('ate');
        expect($rules['ate'])->toContain('after_or_equal:de');

        $ref = new ReflectionClass($req);
        expect($ref->hasMethod('rangeOrDefaults'))->toBeTrue();
    });
});
