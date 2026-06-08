<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;

uses(Tests\TestCase::class);

/**
 * W30 Agent C — Pest pra UpdateReviewStatusRequest (W30-B FormRequest).
 *
 * Pattern espelhado de CreateInitiativeRequestTest (Wave 29) — usa Validator::make()
 * puro em vez de mockar FormRequest completo. Skip graceful se W30-B ainda não
 * publicou a classe FormRequest.
 *
 * Regras validadas (5 cenários — alinhadas ao UpdateReviewStatusRequest real W30-B):
 *  1. Payload válido completo passa (status + notes + desvios + create_initiative)
 *  2. status whitelist (approved / rejected / iterate / pending-wagner) — fora falha
 *  3. notes opcional (nullable) + limite 2000 chars
 *  4. desvios opcional (array) + limite 50 entries + cada string max 500 chars
 *  5. status required — ausente falha
 *
 * Tier 0 IRREVOGÁVEL:
 *  - Sem PII (paths sintéticos: Admin/GovernanceV4). Nenhum CPF/CNPJ
 *  - business_id não aplica (governance é cross-tenant repo-wide intencional)
 *
 * @see Modules/Admin/Http/Requests/UpdateReviewStatusRequest.php (W30-B)
 * @see Modules/Admin/Http/Controllers/ScreenReviewController.php (W30-B)
 * @see memory/decisions/0164-skill-tela-smoke-pos-merge.md (W30-A proposta)
 */

beforeEach(function () {
    // Skip graceful se W30-B ainda não criou a classe
    $candidates = [
        'Modules\\Admin\\Http\\Requests\\UpdateReviewStatusRequest',
        'Modules\\Admin\\Http\\Requests\\UpdateScreenReviewRequest',
        'Modules\\Admin\\Http\\Requests\\ScreenReviewStatusRequest',
    ];

    $found = null;
    foreach ($candidates as $fqcn) {
        if (class_exists($fqcn)) {
            $found = $fqcn;
            break;
        }
    }

    if ($found === null) {
        test()->markTestSkipped('UpdateReviewStatusRequest ainda não criado (W30-B pendente).');
    }

    $this->requestClass = $found;
});

// ──────────────────────────────────────────────────────────────────
// 1. Payload válido completo
// ──────────────────────────────────────────────────────────────────

it('payload válido completo passa validação', function () {
    /** @var \Illuminate\Foundation\Http\FormRequest $r */
    $r = new ($this->requestClass)();

    $v = Validator::make([
        'status' => 'approved',
        'notes' => 'Round 1 aprovado — UX targets bateram.',
        'desvios' => ['Botão alinhamento 2px à direita', 'Sparkline cor errada'],
        'create_initiative' => false,
    ], $r->rules());

    expect($v->passes())->toBeTrue($v->errors()->toJson());
});

// ──────────────────────────────────────────────────────────────────
// 2. status whitelist (approved/rejected/iterate)
// ──────────────────────────────────────────────────────────────────

it('status fora da whitelist canon falha', function () {
    /** @var \Illuminate\Foundation\Http\FormRequest $r */
    $r = new ($this->requestClass)();

    // 'inventado' não está em whitelist
    $v = Validator::make([
        'status' => 'inventado_nao_canon',
        'notes' => null,
    ], $r->rules());

    expect($v->fails())->toBeTrue();
    expect($v->errors()->has('status'))->toBeTrue();

    // Whitelist canon W30-B — todos passam (approved, rejected, iterate, pending-wagner)
    foreach (['approved', 'rejected', 'iterate', 'pending-wagner'] as $statusOk) {
        $vv = Validator::make([
            'status' => $statusOk,
            'notes' => 'Teste ' . $statusOk,
        ], $r->rules());
        expect($vv->passes())->toBeTrue("status={$statusOk} deveria passar: ".$vv->errors()->toJson());
    }
});

// ──────────────────────────────────────────────────────────────────
// 3. notes opcional (nullable)
// ──────────────────────────────────────────────────────────────────

it('notes pode ser ausente/null (campo opcional) + limite 2000 chars', function () {
    /** @var \Illuminate\Foundation\Http\FormRequest $r */
    $r = new ($this->requestClass)();

    // Sem notes
    $v = Validator::make([
        'status' => 'approved',
    ], $r->rules());

    expect($v->passes())->toBeTrue($v->errors()->toJson());

    // notes=null explícito
    $v2 = Validator::make([
        'status' => 'approved',
        'notes' => null,
    ], $r->rules());

    expect($v2->passes())->toBeTrue($v2->errors()->toJson());

    // notes acima de 2000 chars deve falhar
    $v3 = Validator::make([
        'status' => 'approved',
        'notes' => str_repeat('x', 2001),
    ], $r->rules());

    expect($v3->fails())->toBeTrue('notes >2000 chars deveria falhar');
    expect($v3->errors()->has('notes'))->toBeTrue();
});

// ──────────────────────────────────────────────────────────────────
// 4. desvios opcional (array) + limite 50 entries + cada string max 500
// ──────────────────────────────────────────────────────────────────

it('desvios é array opcional com limites canon (50 entries, cada max 500 chars)', function () {
    /** @var \Illuminate\Foundation\Http\FormRequest $r */
    $r = new ($this->requestClass)();

    // Array vazio é válido (campo nullable)
    $v = Validator::make([
        'status' => 'rejected',
        'desvios' => [],
    ], $r->rules());

    expect($v->passes())->toBeTrue($v->errors()->toJson());

    // Acima de 50 desvios deve falhar
    $v2 = Validator::make([
        'status' => 'rejected',
        'desvios' => array_fill(0, 51, 'desvio teste'),
    ], $r->rules());

    expect($v2->fails())->toBeTrue('desvios >50 deveria falhar');
    expect($v2->errors()->has('desvios'))->toBeTrue();

    // Cada desvio string >500 chars deve falhar
    $v3 = Validator::make([
        'status' => 'rejected',
        'desvios' => [str_repeat('x', 501)],
    ], $r->rules());

    expect($v3->fails())->toBeTrue('desvio item >500 chars deveria falhar');
});

// ──────────────────────────────────────────────────────────────────
// 5. status required — ausente falha
// ──────────────────────────────────────────────────────────────────

it('status ausente falha (required, campo crítico ao append do round)', function () {
    /** @var \Illuminate\Foundation\Http\FormRequest $r */
    $r = new ($this->requestClass)();

    // Sem status
    $v = Validator::make([
        'notes' => 'Teste sem status',
    ], $r->rules());

    expect($v->fails())->toBeTrue();
    expect($v->errors()->has('status'))->toBeTrue();
});
