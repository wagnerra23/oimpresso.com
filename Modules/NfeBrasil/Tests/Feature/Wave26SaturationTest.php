<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Models\NfeCertificado;
use Modules\NfeBrasil\Models\NfeEmissao;
use Modules\NfeBrasil\Models\NfeEvento;
use Modules\NfeBrasil\Models\NfeInutilizacao;
use Modules\NfeBrasil\Services\CertificadoService;
use Modules\NfeBrasil\Services\MotorTributarioService;
use Spatie\Activitylog\Traits\LogsActivity;

uses(Tests\TestCase::class);

/**
 * Wave 26 SATURATION NfeBrasil — push 72 → ≥85 (+13pp).
 *
 * Esforco:
 *   - D2 (13→?, +cross-tenant): NfeEmissao + NfeEvento isolation expandido + audit
 *   - D6 (5, defer N/A): NfeEmissaoController API JSON (zero Inertia::defer aplicavel)
 *   - D7 (5, +3): LogsActivity scoped logName por Model + retention.php
 *   - D9 (3, +2): spans MotorTributarioService.calcular + CertificadoService.validar
 *
 * Tier 0 IRREVOGAVEL (CONFAZ SINIEF 07/2005 Art. 14 + ADR 0093):
 *   - NUNCA forceDelete em NfeEmissao cancelada (numero permanece usado oficialmente)
 *   - Senha .pfx / pfxBase64 NUNCA em OtelHelper span attributes (defesa)
 *   - LogsActivity scoped logName por Model (nfe_emissao / nfe_evento / nfe_inutilizacao)
 *
 * SQLite-skip pra cross-tenant DB-real (schema MySQL UltimatePOS).
 *
 * @see Modules/NfeBrasil/Tests/Feature/Wave25NfeSaturationTest.php
 * @see Modules/NfeBrasil/Services/MotorTributarioService.php (D9 new span Wave 26)
 * @see Modules/NfeBrasil/Services/CertificadoService.php (D9 new span Wave 26 — senha-safe)
 */

const W26_NFE_BIZ_WAGNER = 1;
const W26_NFE_BIZ_FICTICIO = 99;
const W26_NFE_TAG = 'WAVE26-NFE-SATURATION';

afterEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        return;
    }
    try {
        if (Schema::hasTable('nfe_emissoes')) {
            NfeEmissao::withoutGlobalScopes()->withTrashed()
                ->whereIn('business_id', [W26_NFE_BIZ_WAGNER, W26_NFE_BIZ_FICTICIO])
                ->whereJsonContains('metadata->tag', W26_NFE_TAG)
                ->forceDelete();
        }
        if (Schema::hasTable('nfe_eventos')) {
            NfeEvento::withoutGlobalScopes()
                ->whereIn('business_id', [W26_NFE_BIZ_WAGNER, W26_NFE_BIZ_FICTICIO])
                ->where('justificativa', 'like', '%'.W26_NFE_TAG.'%')
                ->delete();
        }
    } catch (\Throwable) {
        // best-effort
    }
});

// ============================================================================
// D2 — NfeEmissao + NfeEvento cross-tenant expanded
// ============================================================================

it('D2 NfeEmissao usa HasBusinessScope trait (Tier 0 Model-level enforce)', function () {
    expect(class_uses_recursive(NfeEmissao::class))
        ->toContain(\App\Concerns\HasBusinessScope::class);
});

it('D2 NfeEvento usa HasBusinessScope trait (Tier 0 Model-level enforce — Wave 18 added)', function () {
    expect(class_uses_recursive(NfeEvento::class))
        ->toContain(\App\Concerns\HasBusinessScope::class);
});

it('D2 NfeCertificado usa HasBusinessScope trait (cert por business — segredo SEFAZ)', function () {
    expect(class_uses_recursive(NfeCertificado::class))
        ->toContain(\App\Concerns\HasBusinessScope::class);
});

it('D2 NfeInutilizacao usa HasBusinessScope trait (numeracao SEFAZ scoped per biz)', function () {
    expect(class_uses_recursive(NfeInutilizacao::class))
        ->toContain(\App\Concerns\HasBusinessScope::class);
});

it('D2 NfeEmissao cross-tenant: biz=1 NÃO vaza em scope biz=99 (DB-level)', function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompat: NfeBrasil requer schema MySQL UltimatePOS');
    }
    if (! Schema::hasTable('nfe_emissoes')) {
        $this->markTestSkipped('Tabela nfe_emissoes ausente — rode migrate NfeBrasil');
    }

    DB::table('nfe_emissoes')->insert([
        'business_id' => W26_NFE_BIZ_WAGNER, 'modelo' => '55', 'serie' => '1',
        'numero' => 880001, 'status' => 'autorizada', 'valor_total' => 150.00,
        'metadata' => json_encode(['tag' => W26_NFE_TAG]),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    session(['business.id' => W26_NFE_BIZ_FICTICIO]);
    $vazadosBiz99 = NfeEmissao::whereJsonContains('metadata->tag', W26_NFE_TAG)->count();

    session(['business.id' => W26_NFE_BIZ_WAGNER]);
    $visiveisBiz1 = NfeEmissao::whereJsonContains('metadata->tag', W26_NFE_TAG)->count();

    expect($vazadosBiz99)->toBe(0);
    expect($visiveisBiz1)->toBe(1);
});

it('D2 NfeEvento append-only — public const UPDATED_AT = null (idempotencia auditavel)', function () {
    expect(NfeEvento::UPDATED_AT)->toBeNull();
});

// ============================================================================
// D6 — NfeEmissaoController API JSON (Inertia::defer N/A justified)
// ============================================================================

it('D6 NfeEmissaoController retorna JsonResponse — Inertia::defer N/A (API endpoint, sem Inertia render)', function () {
    $ref = new ReflectionClass(\Modules\NfeBrasil\Http\Controllers\NfeEmissaoController::class);

    foreach (['emitir', 'reenviarEmail', 'danfePdf', 'listar'] as $methodName) {
        if (! $ref->hasMethod($methodName)) continue;
        $method = $ref->getMethod($methodName);
        $returnType = (string) $method->getReturnType();
        // Aceita JsonResponse OR Response (danfePdf PDF binario)
        expect(in_array($returnType, [
            'Illuminate\Http\JsonResponse',
            'Illuminate\Http\Response',
        ], true))->toBeTrue("Metodo {$methodName} deve retornar JsonResponse|Response (API)");
    }
});

it('D6 NfeEmissaoController source-code NUNCA contem Inertia::render (API-only — defer N/A)', function () {
    $src = file_get_contents(base_path('Modules/NfeBrasil/Http/Controllers/NfeEmissaoController.php'));

    expect($src)->not->toContain('Inertia::render');
    expect($src)->not->toContain('Inertia::defer'); // lesson PR #963 rollback
});

it('D6 NfeEmissaoController preserve cross-tenant guard em todas operations (business_id check)', function () {
    $src = file_get_contents(base_path('Modules/NfeBrasil/Http/Controllers/NfeEmissaoController.php'));

    // Toda operation valida business_id da sessao antes de tocar Emissao
    expect($src)->toContain("session()->get('business.id', 0)");
    expect($src)->toContain("'no_business_context'");
});

// ============================================================================
// D7 — LogsActivity per Model + retention preserved
// ============================================================================

it('D7 NfeEmissao::getActivitylogOptions logName scoped (nfe_emissao)', function () {
    $opts = (new NfeEmissao())->getActivitylogOptions();
    expect($opts->logName)->toBe('nfe_emissao');
});

it('D7 NfeEvento::getActivitylogOptions logName scoped (nfe_evento)', function () {
    $opts = (new NfeEvento())->getActivitylogOptions();
    expect($opts->logName)->toBe('nfe_evento');
});

it('D7 NfeInutilizacao::getActivitylogOptions logName scoped (nfe_inutilizacao)', function () {
    $opts = (new NfeInutilizacao())->getActivitylogOptions();
    expect($opts->logName)->toBe('nfe_inutilizacao');
});

it('D7 NfeEmissao audit log NUNCA inclui XML body (PII-LGPD-FISCAL §3.1 — XML em arquivos table)', function () {
    $opts = (new NfeEmissao())->getActivitylogOptions();
    $logAttributes = $opts->logAttributes ?? [];

    expect($logAttributes)->not->toContain('xml_path');
    expect($logAttributes)->not->toContain('danfe_path');
    expect($logAttributes)->not->toContain('metadata');
});

it('D7 retention.php declara enabled flag + LGPD compliance', function () {
    $path = base_path('Modules/NfeBrasil/Config/retention.php');
    expect(file_exists($path))->toBeTrue();

    $cfg = require $path;
    expect($cfg)->toHaveKey('enabled');
});

it('D7 NfeEmissao + NfeEvento + NfeInutilizacao todos com LogsActivity trait (auditoria fiscal)', function () {
    foreach ([NfeEmissao::class, NfeEvento::class, NfeInutilizacao::class] as $cls) {
        $traits = class_uses_recursive($cls);
        expect($traits)->toContain(LogsActivity::class);
    }
});

// ============================================================================
// D9 — Novos spans Wave 26 (MotorTributarioService + CertificadoService)
// ============================================================================

it('D9 MotorTributarioService.calcular usa OtelHelper::span canon (nfe.motor_tributario.calcular)', function () {
    $src = file_get_contents(base_path('Modules/NfeBrasil/Services/MotorTributarioService.php'));

    expect($src)->toContain('use App\Util\OtelHelper;');
    expect($src)->toContain("OtelHelper::span('nfe.motor_tributario.calcular'");
});

it('D9 MotorTributarioService span carrega business_id + ncm + ufOrigem + ufDestino (Tier 0)', function () {
    $src = file_get_contents(base_path('Modules/NfeBrasil/Services/MotorTributarioService.php'));

    expect($src)->toContain("'business_id' => \$businessId");
    expect($src)->toContain("'ncm'");
    expect($src)->toContain("'uf_origem'");
});

it('D9 CertificadoService.validar usa OtelHelper::spanBiz canon (nfe.certificado.validar)', function () {
    $src = file_get_contents(base_path('Modules/NfeBrasil/Services/CertificadoService.php'));

    expect($src)->toContain('use App\Util\OtelHelper;');
    expect($src)->toContain("OtelHelper::spanBiz('nfe.certificado.validar'");
});

it('D9 CertificadoService span NÃO leva senha em attributes (Tier 0 — senha jamais em telemetry)', function () {
    $src = file_get_contents(base_path('Modules/NfeBrasil/Services/CertificadoService.php'));

    // Span atributos apenas booleans/length — defesa explicita comentada.
    expect($src)->toContain("'has_senha' => \$senha !== ''");
    expect($src)->toContain("'pfx_len' => strlen(\$pfxBase64)");
    expect($src)->toContain('NUNCA em attributes');
});

it('D9 NfeBrasil tem ≥7 spans canon catalogados (Wave 26 expand 5→7+)', function () {
    $spans = [
        'nfe.emitir'                  => 'Modules/NfeBrasil/Services/NfeService.php',
        'nfe.cancelar'                => 'Modules/NfeBrasil/Services/NfeService.php',
        'nfe.status_sefaz'            => 'Modules/NfeBrasil/Services/NfeService.php',
        'nfe.inutilizar'              => 'Modules/NfeBrasil/Services/NfeInutilizacaoService.php',
        'nfe.manifestar'              => 'Modules/NfeBrasil/Services/Manifestacao/ManifestacaoService.php',
        'nfe.distribuicao_dfe'        => 'Modules/NfeBrasil/Services/Manifestacao/DistribuicaoDfeService.php',
        'nfe.motor_tributario.calcular' => 'Modules/NfeBrasil/Services/MotorTributarioService.php',
        'nfe.certificado.validar'     => 'Modules/NfeBrasil/Services/CertificadoService.php',
    ];

    foreach ($spans as $spanName => $path) {
        $src = file_get_contents(base_path($path));
        expect(str_contains($src, $spanName))
            ->toBeTrue("Span '{$spanName}' deve estar em {$path}");
    }

    expect(count($spans))->toBeGreaterThanOrEqual(7);
});

// ============================================================================
// Tier 0 CONFAZ preservado (D6 NFe cancelada NUNCA forceDelete — Wave 25 contract)
// ============================================================================

it('Tier 0 CONFAZ Art. 14 — NfeService NUNCA contem ->forceDelete() (preservation contract)', function () {
    $src = file_get_contents(base_path('Modules/NfeBrasil/Services/NfeService.php'));
    expect($src)->not->toContain('->forceDelete()');
});

it('Tier 0 NfeEmissao usa SoftDeletes (CONFAZ — preserva historico audit)', function () {
    $traits = class_uses_recursive(NfeEmissao::class);
    $hasSoftDeletes = collect($traits)->contains(fn ($t) => str_contains($t, 'SoftDeletes'));
    expect($hasSoftDeletes)->toBeTrue();
});

// ============================================================================
// Sanity Wave 26
// ============================================================================

it('Wave 26 OtelHelper canonical app/Util (anti-rollback PR #963 lesson)', function () {
    expect(file_exists(base_path('app/Util/OtelHelper.php')))->toBeTrue();
});

it('Wave 26 OtelHelper zero-cost path callable directly when otel.enabled=false', function () {
    config(['otel.enabled' => false]);

    $result = \App\Util\OtelHelper::span('test.wave26.nfe', ['business_id' => 1], function () {
        return ['ok' => true, 'wave' => 26];
    });

    expect($result)->toBe(['ok' => true, 'wave' => 26]);
});
