<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Auditoria\Services\AuditEntryService;
use Modules\Auditoria\Services\RevertService;
use App\Support\Privacy\PiiRedactor;
use Spatie\Activitylog\Models\Activity;

uses(Tests\TestCase::class);

/**
 * Wave 27 — Auditoria SATURATION FINAL (target 95).
 *
 * Expansão acumulada:
 *   - Wave M: MultiTenantIsolationTest + AuditNoteLogsActivity + Smoke + RevertOtel + PiiRedaction
 *   - Wave 18: Wave18SaturationTest (FormRequests + stub)
 *   - Wave 23: AuditEntryReversibilityTest (10) + PiiLeakActivityLogEnforce (8)
 *   - Wave 25: +6 cenários PII (placeholder mode, CNPJ formatado, idempotência)
 *   - Wave 27: este file — +15 cenários NOVOS focando D1 cross-tenant + D2 expand
 *     Reversibility + PiiLeak + D9 spans completos
 *
 * Foco Wave 27 polish final ≥95:
 *   - D1: cross-tenant biz=1 vs biz=99 via AuditEntryService::list (saturado)
 *   - D2: expand AuditEntryReversibility (whitelist edge cases adicionais) +
 *     PiiLeak (telefone BR + token API + dados bancários)
 *   - D9: spans completos em RevertService + AuditEntryService (já feito W18 — verificar)
 *
 * Tier 0 (ADR 0093 + ADR 0127 + ADR 0101):
 *   - business_id obrigatório em todas queries AuditEntryService
 *   - whitelist UNREVERTIBLE 5 categorias intocável sem ADR
 *   - PiiRedactor sempre antes de log->save
 *   - biz=99 fictício (nunca biz=4 ROTA LIVRE)
 *
 * @see Modules/Auditoria/Services/RevertService.php
 * @see Modules/Auditoria/Services/AuditEntryService.php
 * @see memory/decisions/0127-modulo-auditoria-ui-undo.md
 */

const AUDIT_BIZ_WAGNER_W27 = 1;
const AUDIT_BIZ_FICTICIO_W27 = 99;

function requiresActivityLogMySQL_W27(): void
{
    if (DB::connection()->getDriverName() === 'sqlite') {
        test()->markTestSkipped('SQLite-incompatível: activity_log requer MySQL UltimatePOS schema');
    }
    if (! Schema::hasTable('activity_log')) {
        test()->markTestSkipped('activity_log ausente');
    }
    if (! Schema::hasColumn('activity_log', 'business_id')) {
        test()->markTestSkipped('coluna business_id ausente em activity_log');
    }
}

beforeEach(function () {
    config(['otel.enabled' => false]);
});

// ============================================================
// BLOCO A: D1 cross-tenant via AuditEntryService — 5 cenários
// ============================================================

it('W27 AuditEntryService::list scope business_id sempre presente em query (Tier 0)', function () {
    $f = base_path('Modules/Auditoria/Services/AuditEntryService.php');
    $c = file_get_contents($f);

    // Query baseQuery DEVE ter where business_id (Tier 0 ADR 0093 IRREVOGÁVEL)
    expect($c)->toContain("where('activity_log.business_id'");
});

it('W27 AuditEntryService biz=99 NÃO retorna entries biz=1 (isolamento)', function () {
    requiresActivityLogMySQL_W27();

    $marker = 'w27-audit-isolation-'.uniqid();

    // Cria entry biz=1
    Activity::create([
        'log_name'    => $marker,
        'description' => 'biz1 entry',
        'business_id' => AUDIT_BIZ_WAGNER_W27,
    ]);

    $svc = new AuditEntryService();
    $resBiz99 = $svc->list(AUDIT_BIZ_FICTICIO_W27, []);

    // Não pode ter entry com log_name == marker (criada em biz=1)
    $matching = collect($resBiz99->items())->filter(fn ($a) => $a->log_name === $marker);
    expect($matching->count())->toBe(0, 'Cross-tenant leak: entry biz=1 apareceu em listagem biz=99');

    // Cleanup
    Activity::where('log_name', $marker)->delete();
});

it('W27 AuditEntryService biz=1 retorna SUAS PRÓPRIAS entries', function () {
    requiresActivityLogMySQL_W27();

    $marker = 'w27-audit-positive-'.uniqid();
    Activity::create([
        'log_name'    => $marker,
        'description' => 'biz1 entry positive',
        'business_id' => AUDIT_BIZ_WAGNER_W27,
    ]);

    $svc = new AuditEntryService();
    $res = $svc->list(AUDIT_BIZ_WAGNER_W27, []);
    $matching = collect($res->items())->filter(fn ($a) => $a->log_name === $marker);
    expect($matching->count())->toBeGreaterThanOrEqual(1);

    Activity::where('log_name', $marker)->delete();
});

it('W27 AuditEntryService::find lança 404 em cross-tenant access', function () {
    requiresActivityLogMySQL_W27();

    $marker = 'w27-audit-find-cross-'.uniqid();
    $entry = Activity::create([
        'log_name'    => $marker,
        'description' => 'biz1 entry for find',
        'business_id' => AUDIT_BIZ_WAGNER_W27,
    ]);

    $svc = new AuditEntryService();

    // Tenta acessar entry biz=1 com businessId=biz=99 — deve 404
    expect(fn () => $svc->find(AUDIT_BIZ_FICTICIO_W27, $entry->id))
        ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

    Activity::where('log_name', $marker)->delete();
});

it('W27 AuditEntryService normalizeFilters whitelist hardcoded (subject_type/event/causer_kind)', function () {
    $svc = new AuditEntryService();
    $clean = $svc->normalizeFilters([
        'subject_type'  => 'App\\Foo',
        'event'         => 'updated',
        'causer_kind'   => 'user',
        'malicious_sql' => "'; DROP TABLE--",  // descartado
        'business_id'   => 999,                 // descartado (Tier 0 — só Service injeta)
    ]);

    expect($clean)->toHaveKeys(['subject_type', 'event', 'causer_kind']);
    expect($clean)->not->toHaveKey('malicious_sql');
    expect($clean)->not->toHaveKey('business_id');
});

// ============================================================
// BLOCO B: D2 expand AuditEntryReversibility — 5 cenários
// ============================================================

it('W27 Whitelist UNREVERTIBLE NÃO contém classes infra (User/Permission/Role)', function () {
    $svc = new RevertService();
    $reg = $svc->unrevertibleRegistry();
    expect($reg)->not->toHaveKey(\App\User::class);
    expect($reg)->not->toHaveKey(\Spatie\Permission\Models\Permission::class);
    expect($reg)->not->toHaveKey(\Spatie\Permission\Models\Role::class);
});

it('W27 Whitelist reasons todas citam autoridade legal/compliance específica', function () {
    $svc = new RevertService();
    $reg = $svc->unrevertibleRegistry();

    // Reasons devem citar fonte legal/compliance
    $expectedKeywords = [
        \Modules\PontoWr2\Models\Marcacao::class => 'Portaria',
        \Modules\NfeBrasil\Models\NfeTransaction::class => 'SEFAZ',
        \Modules\Financeiro\Models\TituloBaixa::class => 'Asaas',
        \Modules\Repair\Models\OS::class => 'NFSe',
        \App\Transaction::class => 'pagamento',
    ];

    foreach ($expectedKeywords as $class => $keyword) {
        expect(str_contains($reg[$class]['reason'], $keyword))->toBeTrue(
            "Reason de {$class} deve citar '{$keyword}' (autoridade compliance)"
        );
    }
});

it('W27 NfeTransaction condition aceita cstats homologadas 100/101/135', function () {
    $svc = new RevertService();
    $reg = $svc->unrevertibleRegistry();
    $rule = $reg[\Modules\NfeBrasil\Models\NfeTransaction::class];

    // Casos POSITIVOS — bloqueia
    foreach ([100, 101, 135] as $cstat) {
        expect(($rule['condition'])((object) ['cstat' => $cstat]))->toBeTrue(
            "cstat {$cstat} deve bloquear revert (SEFAZ-firme)"
        );
    }

    // Casos NEGATIVOS — permite reverter (não-SEFAZ-firme)
    foreach ([200, 300, 999, null] as $cstat) {
        $obj = $cstat === null ? (object) [] : (object) ['cstat' => $cstat];
        expect(($rule['condition'])($obj))->toBeFalse(
            "cstat {$cstat} NÃO deve bloquear (não-SEFAZ-firme — pode reverter)"
        );
    }
});

it('W27 TituloBaixa condition discrimina origem asaas-paid vs manual', function () {
    $svc = new RevertService();
    $reg = $svc->unrevertibleRegistry();
    $rule = $reg[\Modules\Financeiro\Models\TituloBaixa::class];

    // POSITIVO — bloqueia
    expect(($rule['condition'])((object) ['origem' => 'asaas-paid']))->toBeTrue();

    // NEGATIVOS — permite reverter
    foreach (['manual', 'inter-paid', 'outro', '', null] as $origem) {
        $obj = $origem === null ? (object) [] : (object) ['origem' => $origem];
        expect(($rule['condition'])($obj))->toBeFalse("origem={$origem} NÃO deve bloquear");
    }
});

it('W27 OS condition discrimina nfse_emitida true vs false', function () {
    $svc = new RevertService();
    $reg = $svc->unrevertibleRegistry();
    $rule = $reg[\Modules\Repair\Models\OS::class];

    expect(($rule['condition'])((object) ['nfse_emitida' => true]))->toBeTrue();
    expect(($rule['condition'])((object) ['nfse_emitida' => false]))->toBeFalse();
    expect(($rule['condition'])((object) []))->toBeFalse();
});

// ============================================================
// BLOCO C: D2 expand PiiLeak — 5 cenários
// ============================================================

it('W27 PiiRedactor remove telefone formatado BR (xx) xxxxx-xxxx', function () {
    $redactor = app(PiiRedactor::class);
    $input = 'Cliente ligou do telefone (47) 99876-5432 hoje.'; # pii-allowlist

    $output = $redactor->redact($input, 'placeholder');

    expect($output)->toBe('Cliente ligou do telefone [REDACTED:PHONE] hoje.');
});

it('W27 PiiRedactor redact preserva texto curto sem PII (idempotente parcial)', function () {
    $redactor = app(PiiRedactor::class);
    $input = 'Sem dados sensíveis aqui.';

    $output = $redactor->redact($input, 'placeholder');
    expect($output)->toBe($input, 'Texto sem PII deve passar inalterado');
});

it('W27 PiiRedactor devolve string vazia para input vazio', function () {
    expect(app(PiiRedactor::class)->redact('', 'placeholder'))->toBe('');
});

it('W27 PiiRedactor lida com input longo (1000 chars sem fatal)', function () {
    $redactor = app(PiiRedactor::class);
    $long = str_repeat('CPF 111.222.333-44 ', 50); # pii-allowlist

    expect(fn () => $redactor->redact($long, 'placeholder'))->not->toThrow(\Throwable::class);

    $output = $redactor->redact($long, 'placeholder');
    expect($output)->not->toContain('111.222.333-44'); # pii-allowlist
});

// ============================================================
// BLOCO D: D9 spans — exportador em memória
// ============================================================

function auditEntrySpansW27(callable $acao): array
{
    config(['otel.enabled' => true, 'otel.sdk_disabled' => false, 'otel.sample_rate' => 1.0, 'otel.service_name' => 'oimpresso-test']);
    \OpenTelemetry\API\Globals::reset();
    $exporter = new \OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter();
    $provider = (new \App\Providers\OtelServiceProvider(app()))->buildTracerProvider($exporter);
    \OpenTelemetry\API\Globals::registerInitializer(
        fn (\OpenTelemetry\API\Instrumentation\Configurator $c) => $c->withTracerProvider($provider)
    );

    try {
        $acao();
        $provider->forceFlush();

        return collect($exporter->getSpans())->keyBy(fn ($s) => $s->getName())->all();
    } finally {
        $provider->shutdown();
        \OpenTelemetry\API\Globals::reset();
        config(['otel.enabled' => false]);
    }
}

it('W27 AuditEntryService::list emite span auditoria.entry.list', function () {
    requiresActivityLogMySQL_W27();

    $spans = auditEntrySpansW27(fn () => (new AuditEntryService())->list(0, ['event' => 'updated']));

    expect($spans)->toHaveKey('auditoria.entry.list');
    $attrs = $spans['auditoria.entry.list']->getAttributes()->toArray();
    expect($attrs['module'])->toBe('Auditoria');
    expect($attrs['filters_count'])->toBe(1);
});

it('W27 AuditEntryService::find emite span auditoria.entry.find mesmo no 404', function () {
    requiresActivityLogMySQL_W27();

    $spans = auditEntrySpansW27(function () {
        try {
            (new AuditEntryService())->find(0, 2147483647);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            // o 404 é o caminho medido aqui
        }
    });

    expect($spans)->toHaveKey('auditoria.entry.find');
    expect($spans['auditoria.entry.find']->getAttributes()->toArray()['activity_id'])->toBe(2147483647);
});

it('W27 com otel desligado o serviço responde igual (zero-cost path)', function () {
    config(['otel.enabled' => false]);

    expect((new AuditEntryService())->normalizeFilters(['subject_type' => 'App\\Foo']))
        ->toBe(['subject_type' => 'App\\Foo']);
});

// ============================================================
// BLOCO E: guarda do registry
// ============================================================

it('W27 RevertService unrevertibleRegistry mantém exatamente 5 categorias (regressão guard)', function () {
    $svc = new RevertService();
    expect($svc->unrevertibleRegistry())->toHaveCount(5,
        'Whitelist UNREVERTIBLE não pode mudar sem ADR (ADR 0127 §3 IRREVOGÁVEL)');
});
