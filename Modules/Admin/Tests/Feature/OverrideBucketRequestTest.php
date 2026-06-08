<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;

uses(Tests\TestCase::class);

/**
 * W29 Agent D — Pest pra OverrideBucketRequest (W29-B FormRequest).
 *
 * Override de bucket é operação crítica — reclassifica módulo do bucket A pro
 * B, alterando meta esperada (80/85/90 pts) e afetando dashboards. Por isso
 * razão obrigatória com min:20 chars (sem "ok", "test", "ajuste") + Wagner-only.
 *
 * Pattern espelhado de Wave25FormRequestsTest (RemediationRequest/AlertAck):
 *   - Validator::make() puro
 *   - Skip graceful se W29-B ainda não publicou a classe
 *
 * Regras validadas (5 cenários):
 *  1. payload válido completo passa
 *  2. razao curta (<20 chars) falha (anti-"ajuste rápido sem registro")
 *  3. bucket_atual fora da whitelist canon falha
 *  4. bucket_novo fora da whitelist canon falha
 *  5. module required (campo obrigatório)
 *
 * Tier 0 IRREVOGÁVEL:
 *  - Razão obrigatória ≥20 chars (rastreabilidade auditoria — sem "ok"/"x")
 *  - Sem PII (módulos sintéticos)
 *
 * @see Modules/Admin/Http/Requests/OverrideBucketRequest.php (W29-B)
 * @see memory/decisions/0160-governance-v4-scoped-scorecards-bucket-meta.md
 */

beforeEach(function () {
    $candidates = [
        'Modules\\Admin\\Http\\Requests\\OverrideBucketRequest',
        'Modules\\Admin\\Http\\Requests\\BucketOverrideRequest',
    ];

    $found = null;
    foreach ($candidates as $fqcn) {
        if (class_exists($fqcn)) {
            $found = $fqcn;
            break;
        }
    }

    if ($found === null) {
        test()->markTestSkipped('OverrideBucketRequest ainda não criado (W29-B pendente).');
    }

    $this->requestClass = $found;
});

// ──────────────────────────────────────────────────────────────────
// 1. Payload válido
// ──────────────────────────────────────────────────────────────────

/**
 * Helper interno — detecta nome real dos campos bucket (pode variar W29-B).
 * Pattern atual W29-B: `old_bucket` / `new_bucket`. Fallback `bucket_atual` / `bucket_novo`.
 */
function detectBucketFields(\Illuminate\Foundation\Http\FormRequest $r): array
{
    $rules = $r->rules();
    $old = isset($rules['old_bucket']) ? 'old_bucket' : 'bucket_atual';
    $new = isset($rules['new_bucket']) ? 'new_bucket' : 'bucket_novo';

    return [$old, $new];
}

it('payload válido completo com razão >20 chars passa', function () {
    /** @var \Illuminate\Foundation\Http\FormRequest $r */
    $r = new ($this->requestClass)();
    [$oldField, $newField] = detectBucketFields($r);

    $v = Validator::make([
        'module' => 'Jana',
        $oldField => 'ai_central',
        $newField => 'cross_cutting_infra',
        'razao' => 'Reclassificação validada com Wagner — IA passou a ser tratada como infra cross-cutting compartilhada.',
    ], $r->rules());

    expect($v->passes())->toBeTrue($v->errors()->toJson());
});

// ──────────────────────────────────────────────────────────────────
// 2. razao curta (<20 chars) falha
// ──────────────────────────────────────────────────────────────────

it('razao com menos de 20 chars falha (anti-ajuste-sem-registro)', function () {
    /** @var \Illuminate\Foundation\Http\FormRequest $r */
    $r = new ($this->requestClass)();
    [$oldField, $newField] = detectBucketFields($r);

    $v = Validator::make([
        'module' => 'Jana',
        $oldField => 'ai_central',
        $newField => 'cross_cutting_infra',
        'razao' => 'ajuste rapido', // 13 chars
    ], $r->rules());

    expect($v->fails())->toBeTrue();
    expect($v->errors()->has('razao'))->toBeTrue();
});

// ──────────────────────────────────────────────────────────────────
// 3. old_bucket fora da whitelist canon
// ──────────────────────────────────────────────────────────────────

it('old_bucket fora da whitelist canon ADR 0160 falha', function () {
    /** @var \Illuminate\Foundation\Http\FormRequest $r */
    $r = new ($this->requestClass)();
    [$oldField, $newField] = detectBucketFields($r);

    $v = Validator::make([
        'module' => 'Jana',
        $oldField => 'inventado_legacy', // não canon
        $newField => 'cross_cutting_infra',
        'razao' => 'Reclassificação validada com Wagner pós-W28 governance v4.',
    ], $r->rules());

    expect($v->fails())->toBeTrue();
    expect($v->errors()->has($oldField))->toBeTrue();
});

// ──────────────────────────────────────────────────────────────────
// 4. new_bucket fora da whitelist canon
// ──────────────────────────────────────────────────────────────────

it('new_bucket fora da whitelist canon ADR 0160 falha', function () {
    /** @var \Illuminate\Foundation\Http\FormRequest $r */
    $r = new ($this->requestClass)();
    [$oldField, $newField] = detectBucketFields($r);

    $v = Validator::make([
        'module' => 'Jana',
        $oldField => 'ai_central',
        $newField => 'super_meta_bucket', // não canon
        'razao' => 'Reclassificação validada com Wagner pós-W28 governance v4.',
    ], $r->rules());

    expect($v->fails())->toBeTrue();
    expect($v->errors()->has($newField))->toBeTrue();
});

// ──────────────────────────────────────────────────────────────────
// 5. module required
// ──────────────────────────────────────────────────────────────────

it('module ausente falha (required)', function () {
    /** @var \Illuminate\Foundation\Http\FormRequest $r */
    $r = new ($this->requestClass)();
    [$oldField, $newField] = detectBucketFields($r);

    $v = Validator::make([
        $oldField => 'ai_central',
        $newField => 'cross_cutting_infra',
        'razao' => 'Reclassificação validada com Wagner pós-W28 governance v4.',
    ], $r->rules());

    expect($v->fails())->toBeTrue();
    expect($v->errors()->has('module'))->toBeTrue();
});
