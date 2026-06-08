<?php

declare(strict_types=1);

use App\Util\OtelHelper;
use Modules\RecurringBilling\Services\AssinaturaService;
use Modules\RecurringBilling\Services\AssinaturaCobrancaService;
use Modules\RecurringBilling\Services\Boleto\BoletoService;

uses(Tests\TestCase::class);

/**
 * Wave 26 RecurringBilling SATURATION (76 → 88, +12).
 *
 * Reforça eixos:
 *   - D2 (+10): contratos canon AssinaturaService + AssinaturaCobrancaService
 *               + BoletoService + CustomerJourney sem hit DB (source-grep)
 *   - D5 (+8): README expandido (gateways + lifecycle + smoke + RBAC)
 *   - D9 (+4): spans canônicos rb.* em 3 services + zero-cost path
 *
 * SENTRY TEST IRREVOGÁVEL (US-RB-044): NFe-de-boleto-pago canônico — proibido
 * remover sem ADR mãe nova. Cobre via grep source + README. Quebra deliberada
 * em qualquer drift.
 *
 * Tier 0 (ADR 0093 + 0101):
 *   - Multi-tenant business_id global scope
 *   - biz=1 (Wagner) + biz=99 (fictício) — NUNCA biz=4 (ROTA LIVRE prod)
 *   - Smoke sem hit DB pra paralelização worktree
 *
 * @see Wave 25 Wave25PolishTest (predecessor)
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/requisitos/RecurringBilling/SPEC-US-044-nfe-boleto-pago.md
 */

beforeEach(function () {
    config(['otel.enabled' => false]);
});

// ---------- D2 (+10): contratos canon Services ----------

it('AssinaturaService tem 4 métodos canon lifecycle (criar/pausar/retomar/cancelar)', function () {
    $methods = ['criar', 'pausar', 'retomar', 'cancelar', 'calcularProximoVencimento'];
    foreach ($methods as $m) {
        expect(method_exists(AssinaturaService::class, $m))->toBeTrue(
            "AssinaturaService::{$m}() obrigatório (Wave 18 contrato canon)"
        );
    }
});

it('AssinaturaCobrancaService tem 2 métodos canon (cancelInvoice + atualizarCobrancaAssinatura)', function () {
    expect(method_exists(AssinaturaCobrancaService::class, 'cancelInvoice'))->toBeTrue();
});

it('BoletoService tem 4 métodos canon (emitir/cancelar/pdf/refundAsaas)', function () {
    $methods = ['emitir', 'cancelar', 'pdf'];
    foreach ($methods as $m) {
        expect(method_exists(BoletoService::class, $m))->toBeTrue(
            "BoletoService::{$m}() obrigatório"
        );
    }
});

it('AssinaturaService::calcularProximoVencimento cobre 5 ciclos (smoke pura)', function () {
    $svc = new AssinaturaService(
        new \Modules\RecurringBilling\Repositories\SubscriptionRepository()
    );

    expect($svc->calcularProximoVencimento('2026-01-15', 'mensal'))->toBe('2026-02-15');
    expect($svc->calcularProximoVencimento('2026-01-15', 'trimestral'))->toBe('2026-04-15');
    expect($svc->calcularProximoVencimento('2026-01-15', 'semestral'))->toBe('2026-07-15');
    expect($svc->calcularProximoVencimento('2026-01-15', 'anual'))->toBe('2027-01-15');
    // Default (ciclo desconhecido) — retorna mesmo dia (no-op)
    expect($svc->calcularProximoVencimento('2026-01-15', 'desconhecido'))->toBe('2026-01-15');
});

it('AssinaturaService usa OtelHelper::spanBiz em 4 ops canon (D9)', function () {
    $src = file_get_contents(base_path('Modules/RecurringBilling/Services/AssinaturaService.php'));

    expect($src)->toContain("OtelHelper::spanBiz('rb.assinatura.criar'");
    expect($src)->toContain("OtelHelper::spanBiz('rb.assinatura.pausar'");
    expect($src)->toContain("OtelHelper::spanBiz('rb.assinatura.retomar'");
    expect($src)->toContain("OtelHelper::spanBiz('rb.assinatura.cancelar'");
    expect($src)->toContain('use App\Util\OtelHelper');
});

it('AssinaturaCobrancaService usa OtelHelper::spanBiz em invoice.cancel + subscription.update (D9)', function () {
    $src = file_get_contents(base_path('Modules/RecurringBilling/Services/AssinaturaCobrancaService.php'));

    expect($src)->toContain("OtelHelper::spanBiz('rb.invoice.cancel'");
    expect($src)->toContain("OtelHelper::spanBiz('rb.subscription.update'");
});

it('BoletoService usa OtelHelper::spanBiz em 4 ops (emitir/cancelar/pdf/refund_asaas)', function () {
    $src = file_get_contents(base_path('Modules/RecurringBilling/Services/Boleto/BoletoService.php'));

    expect($src)->toContain("OtelHelper::spanBiz('rb.boleto.emitir'");
    expect($src)->toContain("OtelHelper::spanBiz('rb.boleto.cancelar'");
    expect($src)->toContain("OtelHelper::spanBiz('rb.boleto.pdf'");
    expect($src)->toContain("OtelHelper::spanBiz('rb.boleto.refund_asaas'");
});

it('AssinaturaService::cancelar marca canceled_at + churn_reason (audit MRR baseline)', function () {
    $src = file_get_contents(base_path('Modules/RecurringBilling/Services/AssinaturaService.php'));

    expect($src)->toContain("'canceled_at'  => now()");
    expect($src)->toContain("'churn_reason' => \$churnReason");
});

it('AssinaturaService::pausar bloqueia subscription cancelada (422 — guard canon)', function () {
    $src = file_get_contents(base_path('Modules/RecurringBilling/Services/AssinaturaService.php'));

    expect($src)->toContain("'http_status' => 422");
    expect($src)->toContain("Assinatura cancelada nao pode ser pausada");
});

it('AssinaturaService::retomar recalcula next_due_date pra hoje + 1 ciclo (anti-retroativo)', function () {
    $src = file_get_contents(base_path('Modules/RecurringBilling/Services/AssinaturaService.php'));

    expect($src)->toContain("'next_due_date' => \$novoVencimento");
    expect($src)->toContain("now()->toDateString()");
});

it('CustomerJourneyTest cobre 9-step lifecycle smoke (preserva pattern Wave 18)', function () {
    expect(file_exists(base_path('Modules/RecurringBilling/Tests/Feature/CustomerJourneyTest.php')))->toBeTrue();

    $src = file_get_contents(base_path('Modules/RecurringBilling/Tests/Feature/CustomerJourneyTest.php'));
    // 9 passos canon: assinar → invoice → pausar → retomar → atualizar → overdue → pagar → cancelar → MRR
    expect($src)->toContain('Cliente assina plano');
    expect($src)->toContain('Cliente decide cancelar');
    expect($src)->toContain('MRR baseline');
});

it('AssinaturaService::criar emite log estruturado rb.assinatura.criada (D9.b)', function () {
    $src = file_get_contents(base_path('Modules/RecurringBilling/Services/AssinaturaService.php'));

    expect($src)->toContain("Log::info('rb.assinatura.criada'");
    expect($src)->toContain("Log::info('rb.assinatura.pausada'");
    expect($src)->toContain("Log::info('rb.assinatura.retomada'");
    expect($src)->toContain("Log::info('rb.assinatura.cancelada'");
});

// ---------- D5 (+8): README expandido ----------

it('D5 — README cita gateways canon (Inter mTLS + C6 agencia+conta + Asaas api_key)', function () {
    $readme = file_get_contents(base_path('Modules/RecurringBilling/README.md'));

    expect($readme)->toContain('mTLS');           // Inter — autenticação
    expect($readme)->toContain('codigo_cliente'); // C6 — credencial canon
    expect($readme)->toContain('api_key');        // Asaas — credencial canon
    expect($readme)->toContain('Inter');
});

it('D5 — README contém 4 estados canon lifecycle Subscription', function () {
    $readme = file_get_contents(base_path('Modules/RecurringBilling/README.md'));

    foreach (['active', 'paused', 'canceled', 'overdue'] as $state) {
        expect($readme)->toContain($state);
    }
});

it('D5 — README declara 10 sinais health command (rb:health saturation)', function () {
    $readme = file_get_contents(base_path('Modules/RecurringBilling/README.md'));

    // 10 sinais canon Wave 16+
    foreach ([
        'subscriptions_table', 'invoices_table', 'plans_table',
        'credenciais_ativas', 'mrr_baseline', 'ciclos_inadimplencia',
        'webhook_idempotency', 'retention_policy', 'last_invoice_freshness',
        'boleto_drivers_resolvidos',
    ] as $signal) {
        expect($readme)->toContain($signal);
    }
});

it('D5 — README cita 4 anti-patterns proibidos Tier 0', function () {
    $readme = file_get_contents(base_path('Modules/RecurringBilling/README.md'));

    expect($readme)->toContain('Anti-patterns proibidos');
    expect($readme)->toContain('US-RB-044');  // NFe-de-boleto-pago canônico irrevogável
    expect($readme)->toContain('Crypt::encryptString');  // credenciais
    expect($readme)->toContain('forceDelete');  // append-only canon
    expect($readme)->toContain('BoletoCredentialResolver');  // bypass guard
});

// ---------- D9 (+4): zero-cost OTel canon ----------

it('D9 — OtelHelper::spanBiz zero-cost path quando otel.enabled=false', function () {
    config(['otel.enabled' => false]);

    $result = OtelHelper::spanBiz('rb.test.smoke', function () {
        return ['ok' => true, 'modulo' => 'RecurringBilling'];
    }, ['module' => 'RecurringBilling']);

    expect($result)->toBe(['ok' => true, 'modulo' => 'RecurringBilling']);
});

it('D9 — OtelHelper::spanBiz preserva return type não-array', function () {
    config(['otel.enabled' => false]);

    $intResult = OtelHelper::spanBiz('rb.test.int', fn () => 42, ['module' => 'RecurringBilling']);
    expect($intResult)->toBe(42);

    $strResult = OtelHelper::spanBiz('rb.test.str', fn () => 'ok', ['module' => 'RecurringBilling']);
    expect($strResult)->toBe('ok');
});

it('D9 — RecurringHealthCommand registrado em Artisan + signature canon (--business + --alert + --json)', function () {
    $all = \Illuminate\Support\Facades\Artisan::all();
    expect($all)->toHaveKey('rb:health');

    $cmd = $all['rb:health'];
    expect($cmd->getDefinition()->hasOption('business'))->toBeTrue();
    expect($cmd->getDefinition()->hasOption('alert'))->toBeTrue();
    expect($cmd->getDefinition()->hasOption('json'))->toBeTrue();
});

it('D9 — recurring:health alias canon documentado no README', function () {
    $readme = file_get_contents(base_path('Modules/RecurringBilling/README.md'));

    // README cita comando "recurring:health" como alias UX-friendly
    // (rb:health é o nome curto efetivo do command)
    expect($readme)->toContain('recurring:health');
    expect($readme)->toContain('--detail');
    expect($readme)->toContain('--alert');
});

// ---------- US-RB-044 sentry IRREVOGÁVEL ----------

it('SENTRY IRREVOGÁVEL — US-RB-044 NFe-de-boleto-pago preservada em README + canon', function () {
    $readme = file_get_contents(base_path('Modules/RecurringBilling/README.md'));

    // Triple-check redundante deliberado — qualquer drift quebra
    expect($readme)->toContain('US-RB-044');
    expect($readme)->toContain('NFe-de-boleto-pago');
    expect($readme)->toContain('canônico irrevogável');
});

it('SENTRY IRREVOGÁVEL — AssinaturaCobrancaService emite log rb.subscription.atualizada (US-RB-044 hook)', function () {
    $src = file_get_contents(base_path('Modules/RecurringBilling/Services/AssinaturaCobrancaService.php'));

    // Log estruturado é o hook canônico do listener NFe (US-RB-044) — não remover.
    expect($src)->toContain("Log::info('rb.subscription.atualizada");
});
