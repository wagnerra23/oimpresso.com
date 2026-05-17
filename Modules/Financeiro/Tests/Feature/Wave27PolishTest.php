<?php

declare(strict_types=1);

use Modules\Financeiro\Models\BoletoRemessa;
use Modules\Financeiro\Repositories\BaixaRepository;
use Modules\Financeiro\Repositories\TituloRepository;
use Modules\Financeiro\Services\FinanceiroAuditLogger;
use Modules\Financeiro\Services\FluxoCaixaService;
use Modules\Financeiro\Services\TituloAutoService;
use Modules\Financeiro\Services\TituloService;
use Modules\Financeiro\Services\UnificadoService;
use Spatie\Activitylog\Traits\LogsActivity;

uses(Tests\TestCase::class);

/**
 * Wave 27 POLISH FINAL Financeiro — D9 spans + US-FIN sentry + US-RB-044 sentinel.
 *
 * Cobre:
 *   - D9 spans: FinanceiroAuditLogger::redactContext wrap em OtelHelper::spanBiz (NEW W27)
 *   - D9 spans canônicos preservados (UnificadoService, FluxoCaixaService, TituloService, TituloAutoService)
 *   - D2 BaixaRepository + TituloRepository W18 sentinel (4+5 métodos canônicos)
 *   - US-FIN-013/020 sentry — UnificadoService::kpis API estável
 *   - US-FIN-014 sentry — FluxoCaixaService::projetar API estável
 *   - US-RB-044 sentinel — BoletoRemessa.STATUS_PAGO existe + LogsActivity preserva audit
 *
 * Multi-tenant Tier 0 (ADR 0093): NÃO chama session/DB real. Source-level + reflection.
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md
 */
describe('Wave 27 Financeiro Polish', function () {
    // ─── D9 spans ────────────────────────────────────────────────────────────

    it('D9 W27 — FinanceiroAuditLogger::redactContext wrap em OtelHelper::spanBiz', function () {
        $source = file_get_contents(__DIR__ . '/../../Services/FinanceiroAuditLogger.php');
        expect($source)->toContain('use App\Util\OtelHelper');
        expect($source)->toContain("OtelHelper::spanBiz('financeiro.audit.redact_context'");
    });

    it('D9 W27 — UnificadoService::kpis span preservado (sentinel W25)', function () {
        $source = file_get_contents(__DIR__ . '/../../Services/UnificadoService.php');
        expect($source)->toContain("OtelHelper::spanBiz('financeiro.unificado.kpis'");
    });

    it('D9 W27 — FluxoCaixaService::projetar span preservado', function () {
        $source = file_get_contents(__DIR__ . '/../../Services/FluxoCaixaService.php');
        expect($source)->toContain("OtelHelper::spanBiz('financeiro.fluxo_caixa.projetar'");
    });

    it('D9 W27 — TituloService::emitirBoleto span preservado', function () {
        $source = file_get_contents(__DIR__ . '/../../Services/TituloService.php');
        expect($source)->toContain("OtelHelper::spanBiz('financeiro.titulo.emitir_boleto'");
    });

    it('D9 W27 — TituloAutoService::sincronizarDeTransaction span preservado', function () {
        $source = file_get_contents(__DIR__ . '/../../Services/TituloAutoService.php');
        expect($source)->toContain("OtelHelper::spanBiz('financeiro.titulo_auto.sincronizar'");
    });

    // ─── D2 Repositories W18 expandido ───────────────────────────────────────

    it('D2 W27 — BaixaRepository expõe 4 métodos canônicos W18', function () {
        $reflection = new ReflectionClass(BaixaRepository::class);
        $publics = collect($reflection->getMethods(ReflectionMethod::IS_PUBLIC))
            ->map(fn ($m) => $m->getName())
            ->toArray();

        foreach (['listarPaginado', 'totaisPorTipoPeriodo', 'historicoRecente', 'acharPorIdempotencyKey'] as $m) {
            expect(in_array($m, $publics, true))->toBeTrue("BaixaRepository::{$m} sentinel W18 ausente");
        }
    });

    it('D2 W27 — TituloRepository expõe 5 métodos canônicos W18', function () {
        $reflection = new ReflectionClass(TituloRepository::class);
        $publics = collect($reflection->getMethods(ReflectionMethod::IS_PUBLIC))
            ->map(fn ($m) => $m->getName())
            ->toArray();

        foreach (['listarPaginado', 'totaisAbertos', 'vencidosAntigos', 'aging', 'acharPorOrigem'] as $m) {
            expect(in_array($m, $publics, true))->toBeTrue("TituloRepository::{$m} sentinel W18 ausente");
        }
    });

    it('D2 W27 — Repositories aceitam businessId int como 1º param (Tier 0 explícito)', function () {
        foreach ([BaixaRepository::class, TituloRepository::class] as $repo) {
            $r = new ReflectionMethod($repo, 'listarPaginado');
            $params = $r->getParameters();
            expect($params[0]->getName())->toBe('businessId');
            expect((string) $params[0]->getType())->toBe('int');
        }
    });

    // ─── US-FIN sentry tests (API stability) ─────────────────────────────────

    it('US-FIN-013/020 sentry — UnificadoService::kpis assinatura estável', function () {
        $r = new ReflectionMethod(UnificadoService::class, 'kpis');
        expect($r->isPublic())->toBeTrue();

        $params = $r->getParameters();
        expect($params[0]->getName())->toBe('businessId');
        expect((string) $params[0]->getType())->toBe('int');
    });

    it('US-FIN-014 sentry — FluxoCaixaService::projetar assinatura estável', function () {
        $r = new ReflectionMethod(FluxoCaixaService::class, 'projetar');
        expect($r->isPublic())->toBeTrue();

        $params = $r->getParameters();
        expect($params[0]->getName())->toBe('businessId');
        expect((string) $params[0]->getType())->toBe('int');
        // 2º param = $dias com default 35 (Q2 Wagner aprovou 2026-05-14)
        expect($params[1]->getName())->toBe('dias');
        expect($params[1]->isDefaultValueAvailable())->toBeTrue();
        expect($params[1]->getDefaultValue())->toBe(35);
    });

    // ─── US-RB-044 sentinel — NFe-de-boleto-pago preservado ───────────────────

    it('US-RB-044 sentinel — BoletoRemessa.STATUS_PAGO existe (gateway NFe trigger)', function () {
        expect(defined(BoletoRemessa::class . '::STATUS_PAGO'))->toBeTrue();
        expect(BoletoRemessa::STATUS_PAGO)->toBe('pago');
    });

    it('US-RB-044 sentinel — BoletoRemessa usa LogsActivity (audit fiscal CTN Art. 195)', function () {
        $traits = class_uses_recursive(BoletoRemessa::class);
        expect($traits)->toContain(LogsActivity::class);
    });

    it('US-RB-044 sentinel — BoletoRemessa pdf_path preservado (double-write transição)', function () {
        $fillable = (new BoletoRemessa())->getFillable();
        expect(in_array('pdf_path', $fillable, true))->toBeTrue('pdf_path coluna legacy preservada US-RB-044 transição');
    });

    it('US-RB-044 sentinel — BoletoRemessa::getPdfArquivoAttribute accessor Modules/Arquivos', function () {
        $r = new ReflectionMethod(BoletoRemessa::class, 'getPdfArquivoAttribute');
        expect($r->isPublic())->toBeTrue();
    });

    // ─── Audit redaction integration ─────────────────────────────────────────

    it('FinanceiroAuditLogger preserva business_id sem redacionar (chave operacional Tier 0)', function () {
        // Source check — confirma business_id está em KEYS_SKIP_REDACTION
        $source = file_get_contents(__DIR__ . '/../../Services/FinanceiroAuditLogger.php');
        expect($source)->toContain("'business_id'");
        expect($source)->toContain('KEYS_SKIP_REDACTION');
    });
});
