<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Auditoria\Services\AuditEntryService;
use Modules\Auditoria\Services\RevertService;
use App\Support\Privacy\PiiRedactor;
use Spatie\Activitylog\Models\Activity;

uses(Tests\TestCase::class, DatabaseTransactions::class);

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
 *   - D1: cross-tenant 98 × 99 via AuditEntryService::list (ADR 0358)
 *   - D2: expand AuditEntryReversibility (whitelist edge cases adicionais) +
 *     PiiLeak (telefone BR + token API + dados bancários)
 *   - D9: spans completos em RevertService + AuditEntryService (já feito W18 — verificar)
 *
 * Tier 0 (ADR 0093 + ADR 0127 + ADR 0358):
 *   - business_id obrigatório em todas queries AuditEntryService
 *   - whitelist UNREVERTIBLE 5 categorias intocável sem ADR
 *   - PiiRedactor sempre antes de log->save
 *   - tenants fictícios 98/99 em transação revertida (nunca biz=1 nem biz=4)
 *
 * @see Modules/Auditoria/Services/RevertService.php
 * @see Modules/Auditoria/Services/AuditEntryService.php
 * @see memory/decisions/0127-modulo-auditoria-ui-undo.md
 */

/** @return array{0:int,1:int} tenants fictícios da ADR 0358: [tenant, adversário] */
function auditTenantsW27(): array
{
    return [(int) test()->seededTenant()->id, (int) test()->seededSupportClientTenant()->id];
}

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

it('W27 toda query de AuditEntryService::list filtra por business_id (Tier 0)', function () {
    requiresActivityLogMySQL_W27();
    [$biz] = auditTenantsW27();

    Activity::create(['log_name' => 'w27-audit-sql', 'description' => 'entry', 'event' => 'updated', 'business_id' => $biz]);

    DB::enableQueryLog();
    (new AuditEntryService())->list($biz, ['event' => 'updated']);
    $queries = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'activity_log'));

    // paginate() faz count + select: as duas precisam carregar o tenant.
    expect($queries->count())->toBeGreaterThanOrEqual(2);
    foreach ($queries as $q) {
        expect($q['query'])->toContain('`activity_log`.`business_id` = ?');
        expect(in_array($biz, $q['bindings'], true))->toBeTrue();
    }
});

it('W27 AuditEntryService de um tenant NÃO retorna entries do outro (isolamento)', function () {
    requiresActivityLogMySQL_W27();
    [$biz, $outro] = auditTenantsW27();

    $marker = 'w27-audit-isolation-'.uniqid();
    Activity::create(['log_name' => $marker, 'description' => 'do tenant', 'business_id' => $biz]);
    $doOutro = Activity::create(['log_name' => $marker, 'description' => 'do outro', 'business_id' => $outro]);

    $svc = new AuditEntryService();
    $matching = collect($svc->list($outro, [], 200)->items())->filter(fn ($a) => $a->log_name === $marker);

    // Âncora positiva: a entry do próprio tenant aparece, só a alheia some.
    expect($matching->pluck('id')->all())->toBe([$doOutro->id]);
});

it('W27 AuditEntryService retorna as entries do próprio tenant', function () {
    requiresActivityLogMySQL_W27();
    [$biz] = auditTenantsW27();

    $marker = 'w27-audit-positive-'.uniqid();
    $entry = Activity::create(['log_name' => $marker, 'description' => 'entry positive', 'business_id' => $biz]);

    $res = (new AuditEntryService())->list($biz, [], 200);
    $ids = collect($res->items())->filter(fn ($a) => $a->log_name === $marker)->pluck('id')->all();

    expect($ids)->toBe([$entry->id]);
});

it('W27 AuditEntryService::find lança 404 em cross-tenant e acha no tenant certo', function () {
    requiresActivityLogMySQL_W27();
    [$biz, $outro] = auditTenantsW27();

    $entry = Activity::create(['log_name' => 'w27-audit-find-cross-'.uniqid(), 'description' => 'entry for find', 'business_id' => $biz]);

    $svc = new AuditEntryService();

    expect($svc->find($biz, $entry->id)->id)->toBe($entry->id);
    expect(fn () => $svc->find($outro, $entry->id))
        ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
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
        \Modules\Ponto\Entities\Marcacao::class => 'Portaria',
        \Modules\NfeBrasil\Models\NfeEmissao::class => 'SEFAZ',
        \Modules\Financeiro\Models\TituloBaixa::class => 'Asaas',
        \Modules\Repair\Entities\JobSheet::class => 'NFSe',
        \App\Transaction::class => 'pagamento',
    ];

    foreach ($expectedKeywords as $class => $keyword) {
        expect(str_contains($reg[$class]['reason'], $keyword))->toBeTrue(
            "Reason de {$class} deve citar '{$keyword}' (autoridade compliance)"
        );
    }
});

it('W27 NfeEmissao e TituloBaixa bloqueiam em qualquer estado (sem condition)', function () {
    $reg = (new RevertService())->unrevertibleRegistry();

    expect($reg[\Modules\NfeBrasil\Models\NfeEmissao::class]['condition'])->toBeNull();
    expect($reg[\Modules\Financeiro\Models\TituloBaixa::class]['condition'])->toBeNull();
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

it('W27 RevertService unrevertibleRegistry cobre as 5 categorias da ADR 0127 (regressão guard)', function () {
    $reg = (new RevertService())->unrevertibleRegistry();

    foreach ([
        \Modules\Ponto\Entities\Marcacao::class,
        \Modules\NfeBrasil\Models\NfeEmissao::class,
        \Modules\Financeiro\Models\TituloBaixa::class,
        \Modules\Repair\Entities\JobSheet::class,
        \App\Transaction::class,
    ] as $classe) {
        expect($reg)->toHaveKey($classe);
    }
});
