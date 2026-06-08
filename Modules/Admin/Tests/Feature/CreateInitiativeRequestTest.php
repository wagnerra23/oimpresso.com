<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;

uses(Tests\TestCase::class);

/**
 * W29 Agent D — Pest pra CreateInitiativeRequest (W29-B FormRequest).
 *
 * Pattern espelhado de Wave25FormRequestsTest (RemediationRequest / AlertAck):
 *   - Usa Validator::make() puro em vez de mockar FormRequest completo
 *   - Skip graceful se W29-B ainda não publicou a classe FormRequest
 *
 * Regras validadas (6 cenários):
 *  1. payload válido completo passa
 *  2. module required (campo obrigatório)
 *  3. bucket whitelist (4 buckets canon ADR 0160)
 *  4. deadline_days range 1..90 (Cortex/Port.io pattern: 14 default, max 90)
 *  5. score_before range 0..100
 *  6. score_target range 0..100
 *
 * Tier 0 IRREVOGÁVEL:
 *  - Sem PII (módulos sintéticos: Jana, OficinaAuto). Nenhum CPF/CNPJ
 *  - business_id não aplica (Initiative é cross-tenant repo-wide)
 *
 * @see Modules/Admin/Http/Requests/CreateInitiativeRequest.php (W29-B)
 * @see Modules/Governance/Services/InitiativeService.php
 * @see memory/decisions/0160-governance-v4-scoped-scorecards-bucket-meta.md
 */

beforeEach(function () {
    // Skip graceful se W29-B ainda não criou a classe
    $candidates = [
        'Modules\\Admin\\Http\\Requests\\CreateInitiativeRequest',
        'Modules\\Admin\\Http\\Requests\\InitiativeCreateRequest',
        'Modules\\Admin\\Http\\Requests\\StoreInitiativeRequest',
    ];

    $found = null;
    foreach ($candidates as $fqcn) {
        if (class_exists($fqcn)) {
            $found = $fqcn;
            break;
        }
    }

    if ($found === null) {
        test()->markTestSkipped('CreateInitiativeRequest ainda não criado (W29-B pendente).');
    }

    $this->requestClass = $found;
});

// ──────────────────────────────────────────────────────────────────
// 1. Payload válido
// ──────────────────────────────────────────────────────────────────

it('payload válido completo passa validação', function () {
    /** @var \Illuminate\Foundation\Http\FormRequest $r */
    $r = new ($this->requestClass)();

    $v = Validator::make([
        'module' => 'Jana',
        'bucket' => 'ai_central',
        'rule_id' => 'F1.a',
        'score_before' => 68,
        'score_target' => 85,
        'deadline_days' => 14,
    ], $r->rules());

    expect($v->passes())->toBeTrue($v->errors()->toJson());
});

// ──────────────────────────────────────────────────────────────────
// 2. module required
// ──────────────────────────────────────────────────────────────────

it('module ausente falha (required)', function () {
    /** @var \Illuminate\Foundation\Http\FormRequest $r */
    $r = new ($this->requestClass)();

    $v = Validator::make([
        'bucket' => 'ai_central',
        'rule_id' => 'F1.a',
        'score_before' => 68,
        'score_target' => 85,
        'deadline_days' => 14,
    ], $r->rules());

    expect($v->fails())->toBeTrue();
    expect($v->errors()->has('module'))->toBeTrue();
});

// ──────────────────────────────────────────────────────────────────
// 3. bucket whitelist (ADR 0160 — 4 buckets canon)
// ──────────────────────────────────────────────────────────────────

it('bucket fora da whitelist canon falha', function () {
    /** @var \Illuminate\Foundation\Http\FormRequest $r */
    $r = new ($this->requestClass)();

    $v = Validator::make([
        'module' => 'Jana',
        'bucket' => 'inventado_nao_canon',
        'rule_id' => 'F1.a',
        'score_before' => 68,
        'score_target' => 85,
        'deadline_days' => 14,
    ], $r->rules());

    expect($v->fails())->toBeTrue();
    expect($v->errors()->has('bucket'))->toBeTrue();
});

// ──────────────────────────────────────────────────────────────────
// 4. deadline_days range (1..90)
// ──────────────────────────────────────────────────────────────────

it('deadline_days fora do range 1..90 falha', function () {
    /** @var \Illuminate\Foundation\Http\FormRequest $r */
    $r = new ($this->requestClass)();

    // Acima do máximo
    $v = Validator::make([
        'module' => 'Jana',
        'bucket' => 'ai_central',
        'rule_id' => 'F1.a',
        'score_before' => 68,
        'score_target' => 85,
        'deadline_days' => 999,
    ], $r->rules());

    expect($v->fails())->toBeTrue();
    expect($v->errors()->has('deadline_days'))->toBeTrue();

    // Abaixo do mínimo (0 ou negativo)
    $v2 = Validator::make([
        'module' => 'Jana',
        'bucket' => 'ai_central',
        'rule_id' => 'F1.a',
        'score_before' => 68,
        'score_target' => 85,
        'deadline_days' => 0,
    ], $r->rules());

    expect($v2->fails())->toBeTrue();
    expect($v2->errors()->has('deadline_days'))->toBeTrue();
});

// ──────────────────────────────────────────────────────────────────
// 5. score_before range (0..100)
// ──────────────────────────────────────────────────────────────────

it('score_before fora do range 0..100 falha', function () {
    /** @var \Illuminate\Foundation\Http\FormRequest $r */
    $r = new ($this->requestClass)();

    $v = Validator::make([
        'module' => 'Jana',
        'bucket' => 'ai_central',
        'rule_id' => 'F1.a',
        'score_before' => 150, // > 100
        'score_target' => 85,
        'deadline_days' => 14,
    ], $r->rules());

    expect($v->fails())->toBeTrue();
    expect($v->errors()->has('score_before'))->toBeTrue();
});

// ──────────────────────────────────────────────────────────────────
// 6. score_target range (0..100)
// ──────────────────────────────────────────────────────────────────

it('score_target fora do range 0..100 falha', function () {
    /** @var \Illuminate\Foundation\Http\FormRequest $r */
    $r = new ($this->requestClass)();

    $v = Validator::make([
        'module' => 'Jana',
        'bucket' => 'ai_central',
        'rule_id' => 'F1.a',
        'score_before' => 68,
        'score_target' => -10, // < 0
        'deadline_days' => 14,
    ], $r->rules());

    expect($v->fails())->toBeTrue();
    expect($v->errors()->has('score_target'))->toBeTrue();
});
