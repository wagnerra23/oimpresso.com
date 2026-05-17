<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use Modules\Admin\Http\Requests\AlertAcknowledgeRequest;
use Modules\Admin\Http\Requests\RemediationRequest;

uses(Tests\TestCase::class);

/**
 * Wave25FormRequestsTest — Admin D8 SATURATION (+4 boost).
 *
 * Cobre validação dos 2 FormRequests novos Wave 25:
 *   - RemediationRequest: whitelist ADRs Tier 0 + double-confirm + remediation_kind
 *   - AlertAcknowledgeRequest: snooze cap + whitelist ADRs
 *
 * Pattern: Validator::make() puro em vez de mock FormRequest (mais leve).
 *
 * Tier 0 IRREVOGÁVEL: PII NUNCA aparece em reason. Tests usam strings
 * fictícias ("maintenance window", "ack sprint") — nenhum CPF/CNPJ real.
 *
 * @see Modules\Admin\Http\Requests\RemediationRequest
 * @see Modules\Admin\Http\Requests\AlertAcknowledgeRequest
 * @see memory/decisions/0122-admin-center-ct100.md
 */

// ---------- RemediationRequest ----------

it('RemediationRequest: payload válido passa', function () {
    $r = new RemediationRequest();
    $data = [
        'adr_id'           => '0093',
        'check_name'       => 'multi_tenant_isolation',
        'remediation_kind' => 'retry_health_check',
        'reason'           => 'Falso positivo cron 14h',
        'confirm'          => true,
    ];

    $v = Validator::make($data, $r->rules());
    expect($v->passes())->toBeTrue($v->errors()->toJson());
});

it('RemediationRequest: adr_id fora da whitelist falha', function () {
    $r = new RemediationRequest();
    $v = Validator::make([
        'adr_id'           => '9999',
        'check_name'       => 'multi_tenant_isolation',
        'remediation_kind' => 'retry_health_check',
        'reason'           => 'qualquer',
        'confirm'          => true,
    ], $r->rules());

    expect($v->fails())->toBeTrue();
    expect($v->errors()->has('adr_id'))->toBeTrue();
});

it('RemediationRequest: remediation_kind inválido falha', function () {
    $r = new RemediationRequest();
    $v = Validator::make([
        'adr_id'           => '0093',
        'check_name'       => 'multi_tenant_isolation',
        'remediation_kind' => 'delete_database',  // não whitelisted
        'reason'           => 'algum motivo válido',
        'confirm'          => true,
    ], $r->rules());

    expect($v->fails())->toBeTrue();
    expect($v->errors()->has('remediation_kind'))->toBeTrue();
});

it('RemediationRequest: reason curto (<5 chars) falha', function () {
    $r = new RemediationRequest();
    $v = Validator::make([
        'adr_id'           => '0093',
        'check_name'       => 'multi_tenant_isolation',
        'remediation_kind' => 'retry_health_check',
        'reason'           => 'oi',
        'confirm'          => true,
    ], $r->rules());

    expect($v->fails())->toBeTrue();
    expect($v->errors()->has('reason'))->toBeTrue();
});

it('RemediationRequest: confirm=false falha (double-confirmation obrigatório)', function () {
    $r = new RemediationRequest();
    $v = Validator::make([
        'adr_id'           => '0093',
        'check_name'       => 'multi_tenant_isolation',
        'remediation_kind' => 'retry_health_check',
        'reason'           => 'razão válida',
        'confirm'          => false,
    ], $r->rules());

    expect($v->fails())->toBeTrue();
    expect($v->errors()->has('confirm'))->toBeTrue();
});

it('RemediationRequest: TIER_0_ADRS contém pelo menos 4 ADRs canônicas', function () {
    expect(count(RemediationRequest::TIER_0_ADRS))->toBeGreaterThanOrEqual(4);
    expect(RemediationRequest::TIER_0_ADRS)->toContain('0093');  // multi-tenant
    expect(RemediationRequest::TIER_0_ADRS)->toContain('0094');  // Constituição v2
});

it('RemediationRequest: payload opcional aceita array', function () {
    $r = new RemediationRequest();
    $v = Validator::make([
        'adr_id'           => '0093',
        'check_name'       => 'multi_tenant_isolation',
        'remediation_kind' => 'invalidate_cache',
        'reason'           => 'invalidação manual cache stale',
        'confirm'          => true,
        'payload'          => ['cache_key' => 'admin.widget.brief'],
    ], $r->rules());

    expect($v->passes())->toBeTrue($v->errors()->toJson());
});

// ---------- AlertAcknowledgeRequest ----------

it('AlertAcknowledgeRequest: payload válido passa', function () {
    $r = new AlertAcknowledgeRequest();
    $v = Validator::make([
        'check_name'     => 'multi_tenant_isolation',
        'adr_id'         => '0093',
        'snooze_minutes' => 30,
        'reason'         => 'Sabido, em maintenance Hostinger 14h',
        'confirm'        => true,
    ], $r->rules());

    expect($v->passes())->toBeTrue($v->errors()->toJson());
});

it('AlertAcknowledgeRequest: snooze > MAX (60) falha', function () {
    $r = new AlertAcknowledgeRequest();
    $v = Validator::make([
        'check_name'     => 'multi_tenant_isolation',
        'adr_id'         => '0093',
        'snooze_minutes' => 999,  // tenta silenciar por horas
        'reason'         => 'razão',
        'confirm'        => true,
    ], $r->rules());

    expect($v->fails())->toBeTrue();
    expect($v->errors()->has('snooze_minutes'))->toBeTrue();
});

it('AlertAcknowledgeRequest: snooze < 5 falha', function () {
    $r = new AlertAcknowledgeRequest();
    $v = Validator::make([
        'check_name'     => 'multi_tenant_isolation',
        'adr_id'         => '0093',
        'snooze_minutes' => 1,
        'reason'         => 'razão válida',
        'confirm'        => true,
    ], $r->rules());

    expect($v->fails())->toBeTrue();
    expect($v->errors()->has('snooze_minutes'))->toBeTrue();
});

it('AlertAcknowledgeRequest: adr_id fora da whitelist falha', function () {
    $r = new AlertAcknowledgeRequest();
    $v = Validator::make([
        'check_name'     => 'qualquer',
        'adr_id'         => '0001',  // não Tier 0
        'snooze_minutes' => 30,
        'reason'         => 'razão',
        'confirm'        => true,
    ], $r->rules());

    expect($v->fails())->toBeTrue();
    expect($v->errors()->has('adr_id'))->toBeTrue();
});

it('AlertAcknowledgeRequest: MAX_SNOOZE_MINUTES é 60 (Tier 0 não silencia por dias)', function () {
    expect(AlertAcknowledgeRequest::MAX_SNOOZE_MINUTES)->toBe(60);
});

it('AlertAcknowledgeRequest: reuse de TIER_0_ADRS whitelist do RemediationRequest', function () {
    // Ambos FormRequests usam mesma whitelist — coerente.
    $r = new AlertAcknowledgeRequest();
    foreach (RemediationRequest::TIER_0_ADRS as $adr) {
        $v = Validator::make([
            'check_name'     => 'multi_tenant_isolation',
            'adr_id'         => $adr,
            'snooze_minutes' => 10,
            'reason'         => 'razão válida pra ack',
            'confirm'        => true,
        ], $r->rules());

        expect($v->passes())->toBeTrue("ADR {$adr} deve estar na whitelist");
    }
});
