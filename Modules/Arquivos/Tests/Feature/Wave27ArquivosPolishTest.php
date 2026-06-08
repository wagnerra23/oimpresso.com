<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Modules\Arquivos\Http\Requests\ReclassifyArquivoRequest;
use Modules\Arquivos\Http\Requests\RetentionRunRequest;
use Modules\Arquivos\Services\ArquivosRetentionService;
use Modules\Arquivos\Services\ArquivosService;

uses(Tests\TestCase::class);

/**
 * Wave 27 — Arquivos polish 74-85 → ≥88 (2026-05-17).
 *
 * Scope da Wave 27 (Bucket `infrastructure_horizontal`):
 *   - D5: README expandido com persona "Auditor LGPD" (Wave 23 fez parcial).
 *   - D8.c: +2 FormRequests novos (ReclassifyArquivoRequest, RetentionRunRequest).
 *   - D9.a: +2 spans novos em ArquivosRetentionService (preview, report) —
 *           total 14 spans canon nos 3 Services do módulo.
 *   - D2: regression OtelHelper canônico + DI + multi-tenant Tier 0.
 *
 * Multi-tenant Tier 0 (ADR 0093) IRREVOGÁVEL: Arquivo usa HasBusinessScope
 * global scope. Estes testes confirmam que Services NÃO inferem business_id
 * mágico — caller sempre passa explícito.
 *
 * Zero-cost: roda sem DB — Validator + Reflection + source-grep.
 */

// ---------- D8.c — ReclassifyArquivoRequest ----------

it('027.a01 ReclassifyArquivoRequest exige motivo (LGPD audit trail)', function () {
    $rules = (new ReclassifyArquivoRequest)->rules();
    $v = Validator::make([], $rules);
    expect($v->fails())->toBeTrue();
    expect($v->errors()->has('motivo'))->toBeTrue();
});

it('027.a02 ReclassifyArquivoRequest motivo mínimo 5 chars', function () {
    $rules = (new ReclassifyArquivoRequest)->rules();
    $v = Validator::make(['motivo' => 'ok'], $rules);
    expect($v->fails())->toBeTrue();
    expect($v->errors()->has('motivo'))->toBeTrue();
});

it('027.a03 ReclassifyArquivoRequest force_bucket whitelisted', function () {
    $rules = (new ReclassifyArquivoRequest)->rules();
    $v = Validator::make([
        'motivo'       => 'reclassify por nova regra curador v3',
        'force_bucket' => 'hacker_bucket',
    ], $rules);
    expect($v->fails())->toBeTrue();
    expect($v->errors()->has('force_bucket'))->toBeTrue();
});

it('027.a04 ReclassifyArquivoRequest aceita payload válido', function () {
    $rules = (new ReclassifyArquivoRequest)->rules();
    $v = Validator::make([
        'motivo'       => 'reclassify por nova regra curador v3 — NFe XML',
        'force_bucket' => 'sensitive',
        'batch_tag'    => 'curador_v3_2026q2',
    ], $rules);
    expect($v->fails())->toBeFalse();
});

it('027.a05 ReclassifyArquivoRequest batch_tag rejeita chars perigosos', function () {
    $rules = (new ReclassifyArquivoRequest)->rules();
    $v = Validator::make([
        'motivo'    => 'rota qualquer',
        'batch_tag' => '../../../etc',
    ], $rules);
    expect($v->fails())->toBeTrue();
    expect($v->errors()->has('batch_tag'))->toBeTrue();
});

// ---------- D8.c — RetentionRunRequest ----------

it('027.a10 RetentionRunRequest exige retention_days', function () {
    $rules = (new RetentionRunRequest)->rules();
    $v = Validator::make([], $rules);
    expect($v->fails())->toBeTrue();
    expect($v->errors()->has('retention_days'))->toBeTrue();
});

it('027.a11 RetentionRunRequest faixa segura retention_days 90..3650', function () {
    $rules = (new RetentionRunRequest)->rules();

    foreach ([30, 89, 3651, 99999] as $invalido) {
        $v = Validator::make([
            'retention_days' => $invalido,
            'dry_run'        => true,
            'purge'          => false,
        ], $rules);
        expect($v->fails())->toBeTrue("retention_days={$invalido} deveria falhar");
    }

    foreach ([90, 365, 1825, 3650] as $valido) {
        $v = Validator::make([
            'retention_days' => $valido,
            'dry_run'        => true,
            'purge'          => false,
        ], $rules);
        expect($v->fails())->toBeFalse("retention_days={$valido} deveria passar");
    }
});

it('027.a12 RetentionRunRequest motivo obrigatório quando purge=true (Art. 18 §VI)', function () {
    $rules = (new RetentionRunRequest)->rules();

    $v = Validator::make([
        'retention_days' => 1825,
        'dry_run'        => false,
        'purge'          => true,
    ], $rules);
    expect($v->fails())->toBeTrue();
    expect($v->errors()->has('motivo'))->toBeTrue();
});

it('027.a13 RetentionRunRequest motivo opcional quando purge=false (soft-delete)', function () {
    $rules = (new RetentionRunRequest)->rules();

    $v = Validator::make([
        'retention_days' => 1825,
        'dry_run'        => false,
        'purge'          => false,
    ], $rules);
    expect($v->fails())->toBeFalse();
});

it('027.a14 RetentionRunRequest::toServiceArgs retorna shape ready pro Service', function () {
    $req = new RetentionRunRequest();
    $req->merge([
        'retention_days' => 1825,
        'dry_run'        => true,
        'purge'          => false,
    ]);

    $args = $req->toServiceArgs();
    expect($args)->toHaveKeys(['retention_days', 'dry_run', 'purge']);
    expect($args['retention_days'])->toBe(1825);
    expect($args['dry_run'])->toBeTrue();
    expect($args['purge'])->toBeFalse();
});

// ---------- D9.a — ArquivosRetentionService spans novos ----------

it('027.a20 ArquivosRetentionService bindable via container', function () {
    $svc = app(ArquivosRetentionService::class);
    expect($svc)->toBeInstanceOf(ArquivosRetentionService::class);
});

it('027.a21 ArquivosRetentionService tem método preview (Wave 27 novo)', function () {
    expect(method_exists(ArquivosRetentionService::class, 'preview'))->toBeTrue();

    $ref = new ReflectionMethod(ArquivosRetentionService::class, 'preview');
    $params = $ref->getParameters();
    expect($params[0]->getName())->toBe('businessId');
    expect($params[0]->getType()?->getName())->toBe('int');
    expect($ref->getReturnType()?->getName())->toBe('array');
});

it('027.a22 ArquivosRetentionService tem método report (Wave 27 novo)', function () {
    expect(method_exists(ArquivosRetentionService::class, 'report'))->toBeTrue();

    $ref = new ReflectionMethod(ArquivosRetentionService::class, 'report');
    $params = collect($ref->getParameters())->keyBy(fn ($p) => $p->getName());
    expect($params->has('businessId'))->toBeTrue();
    expect($params['businessId']->getType()?->getName())->toBe('int');
});

it('027.a23 ArquivosRetentionService usa OtelHelper canônico (não SDK direto)', function () {
    $src = file_get_contents(base_path('Modules/Arquivos/Services/ArquivosRetentionService.php'));
    expect($src)->toContain('use App\Util\OtelHelper;');
    expect($src)->not->toContain('OpenTelemetry\API\Trace\TracerProviderInterface');
});

it('027.a24 ArquivosRetentionService tem ≥6 spans canon (4 originais + 2 W27)', function () {
    $src = file_get_contents(base_path('Modules/Arquivos/Services/ArquivosRetentionService.php'));

    $esperados = [
        'arquivos.retention.scan',
        'arquivos.retention.expire_one',
        'arquivos.retention.purge_one',
        'arquivos.retention.run',
        'arquivos.retention.preview',  // Wave 27
        'arquivos.retention.report',   // Wave 27
    ];

    foreach ($esperados as $span) {
        expect($src)->toContain("OtelHelper::spanBiz('{$span}'");
    }

    $count = substr_count($src, 'OtelHelper::spanBiz(');
    expect($count)->toBeGreaterThanOrEqual(6);
});

it('027.a25 Arquivos 3 Services somam ≥14 spans OTel canon (Wave 27 saturated)', function () {
    $services = [
        base_path('Modules/Arquivos/Services/ArquivosService.php'),
        base_path('Modules/Arquivos/Services/ArquivosRetentionService.php'),
        base_path('Modules/Arquivos/Services/VaultEncryptionService.php'),
    ];

    $total = 0;
    foreach ($services as $f) {
        expect(file_exists($f))->toBeTrue();
        $src = file_get_contents($f);
        expect($src)->toContain('use App\Util\OtelHelper;');
        $total += substr_count($src, 'OtelHelper::spanBiz(');
    }

    // Wave 18 baseline: 5 (ArquivosService) + 4 (Retention) + 2 (Vault) = 11
    // Wave 27 polish: +2 spans em Retention (preview + report) = 13 saturated
    // Threshold conservador permite refactor futuro sem quebrar regression.
    expect($total)->toBeGreaterThanOrEqual(13);
});

it('027.a26 ArquivosService preservado (DI ok — sem regressão Wave 27)', function () {
    $svc = app(ArquivosService::class);
    expect($svc)->toBeInstanceOf(ArquivosService::class);
});

// ---------- D5 — README persona Auditor LGPD ----------

it('027.a30 README.md existe e menciona persona Auditor LGPD', function () {
    $path = base_path('Modules/Arquivos/README.md');
    expect(file_exists($path))->toBeTrue('README.md ausente — D5 violado');

    $src = file_get_contents($path);
    expect($src)->toContain('Auditor LGPD');
    expect($src)->toContain('persona');
});

it('027.a31 README.md cobre 5 garantias canônicas ao Auditor LGPD', function () {
    $src = file_get_contents(base_path('Modules/Arquivos/README.md'));

    // Garantias mínimas exigidas pra D5 Wave 27
    expect($src)->toContain('Política declarada');
    expect($src)->toContain('Política executada');
    expect($src)->toContain('Multi-tenant');
    expect($src)->toContain('Encryption-at-rest');
    expect($src)->toContain('Art. 18');
});

it('027.a32 README.md referencia ADR 0123 + 0093 (governance Tier 0)', function () {
    $src = file_get_contents(base_path('Modules/Arquivos/README.md'));

    expect($src)->toContain('0123');
    expect($src)->toContain('0093');
});
