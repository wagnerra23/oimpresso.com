<?php

declare(strict_types=1);

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Modules\Whatsapp\Http\Requests\BusinessSettingsRequest;

uses(Tests\TestCase::class);

beforeEach(function () {
    // QUARENTENA (snapshot superseded): a validação de driver/LGPD/fallback migrou
    // de BusinessSettingsRequest (hoje stub — US-WA-067 + ADR 0135) para
    // ChannelRequest::withValidator() com contrato incompatível (config.* nesting,
    // type=whatsapp_zapi/baileys/whatsmeow, lgpd via required_if/accepted_if). As
    // chaves bypass_business_ids / forbidden_drivers não existem mais.
    // Cobertura viva: ChannelRequestUniqueIdentifierTest.
    test()->markTestSkipped('Superseded por ADR 0135 — validação de drivers migrou p/ ChannelRequest; BusinessSettingsRequest é stub (US-WA-067).');

    // Reset bypass list — testes Tier 0 dependem de default vazio (gate ativo).
    // Testes de bypass setam [1] explicitamente.
    config()->set('whatsapp.fallback.bypass_business_ids', []);
});

/**
 * R-WA-001 + R-WA-002 · BusinessSettingsRequest gating duro Tier 0 (ADR 0096).
 *
 * Cobre:
 * - driver=zapi sem meta_* preenchido → 422 (gating fallback obrigatório)
 * - driver=zapi sem lgpd_acknowledged → 422 (termo LGPD)
 * - driver=zapi com meta_* + lgpd_acknowledged=true → válido
 * - driver=evolution → 422 (PROIBIDO permanente forbidden_drivers)
 * - driver=baileys com fallback Meta cadastrado → válido (Sprint 3)
 *
 * Padrão: instancia FormRequest direto sem rota — valida regras isoladas.
 */

function runRequest(array $input, int $businessId = 1): \Illuminate\Validation\Validator
{
    // Session driver com user.business_id seedado — withValidator() lê
    // session('user.business_id') pra checar bypass list (ADR 0111).
    $sessionDriver = app('session.store');
    $sessionDriver->put('user.business_id', $businessId);

    $req = new BusinessSettingsRequest();
    $req->merge($input);
    $req->setContainer(app());
    $req->setLaravelSession($sessionDriver);

    $v = Validator::make($input, $req->rules(), $req->messages());

    // withValidator() registra closures via $v->after() — chamar ANTES de
    // $v->fails() pra que rodem na 1ª execução (chamar dentro de outra
    // ->after() não dispara, pois iteration já rodou).
    $req->withValidator($v);

    return $v;
}

it('aceita driver=meta_cloud com meta_* preenchido', function () {
    $v = runRequest([
        'driver' => 'meta_cloud',
        'meta_phone_number_id' => '123456789',
        'meta_access_token' => str_repeat('A', 60),
        'meta_app_secret' => 'secret-value-' . str_repeat('x', 20),
        'meta_webhook_verify_token' => 'verify-token-12345',
    ]);

    expect($v->fails())->toBeFalse();
});

it('rejeita driver=zapi sem meta_* preenchido (gating fallback obrigatório)', function () {
    $v = runRequest([
        'driver' => 'zapi',
        'zapi_instance_id' => 'inst-1',
        'zapi_instance_token' => 'token-1',
        'zapi_client_token' => 'client-1',
        'lgpd_acknowledged' => true,
        // meta_* AUSENTE
    ]);

    expect($v->fails())->toBeTrue();
    expect($v->errors()->first('meta_phone_number_id'))->toContain('fallback Meta Cloud');
});

it('rejeita driver=zapi sem lgpd_acknowledged (termo LGPD obrigatório)', function () {
    $v = runRequest([
        'driver' => 'zapi',
        'zapi_instance_id' => 'inst-1',
        'zapi_instance_token' => 'token-1',
        'zapi_client_token' => 'client-1',
        'meta_phone_number_id' => '123456789',
        'meta_access_token' => str_repeat('A', 60),
        'meta_app_secret' => 'secret-' . str_repeat('x', 20),
        'meta_webhook_verify_token' => 'verify-12345',
        // lgpd_acknowledged AUSENTE
    ]);

    expect($v->fails())->toBeTrue();
    expect($v->errors()->first('lgpd_acknowledged'))->toContain('LGPD');
});

it('aceita driver=zapi com meta_* + lgpd_acknowledged=true', function () {
    $v = runRequest([
        'driver' => 'zapi',
        'zapi_instance_id' => 'inst-1',
        'zapi_instance_token' => 'token-1',
        'zapi_client_token' => 'client-1',
        'meta_phone_number_id' => '123456789',
        'meta_access_token' => str_repeat('A', 60),
        'meta_app_secret' => 'secret-' . str_repeat('x', 20),
        'meta_webhook_verify_token' => 'verify-12345',
        'lgpd_acknowledged' => true,
    ]);

    expect($v->fails())->toBeFalse();
});

it('rejeita driver=evolution com 422 (PROIBIDO permanente — ADR 0096 emenda 4)', function () {
    config()->set('whatsapp.forbidden_drivers', ['evolution', 'whatsapp_web_js']);

    $v = runRequest([
        'driver' => 'evolution',
    ]);

    expect($v->fails())->toBeTrue();
    expect($v->errors()->has('driver'))->toBeTrue();
});

it('rejeita driver=whatsapp_web_js (PROIBIDO permanente — sobreposição com BaileysDriver)', function () {
    config()->set('whatsapp.forbidden_drivers', ['evolution', 'whatsapp_web_js']);

    $v = runRequest([
        'driver' => 'whatsapp_web_js',
    ]);

    expect($v->fails())->toBeTrue();
});

it('aceita driver=zapi sem meta_* quando business_id está em bypass list (ADR 0111 emenda 5)', function () {
    config()->set('whatsapp.fallback.bypass_business_ids', [1]);

    $v = runRequest([
        'driver' => 'zapi',
        'zapi_instance_id' => 'inst-bypass',
        'zapi_instance_token' => 'tok-bypass',
        'zapi_client_token' => 'client-bypass',
        'lgpd_acknowledged' => true,
        // meta_* AUSENTE de propósito
    ], businessId: 1);

    expect($v->fails())->toBeFalse();
});

it('rejeita driver=zapi sem meta_* quando business_id NÃO está em bypass list (Tier 0 IRREVOGÁVEL outros businesses)', function () {
    config()->set('whatsapp.fallback.bypass_business_ids', [1]);

    // biz=99 (cross-tenant adversário) NÃO está na bypass list — Tier 0 mantido
    $v = runRequest([
        'driver' => 'zapi',
        'zapi_instance_id' => 'inst-1',
        'zapi_instance_token' => 'tok-1',
        'zapi_client_token' => 'client-1',
        'lgpd_acknowledged' => true,
    ], businessId: 99);

    expect($v->fails())->toBeTrue();
    expect($v->errors()->first('meta_phone_number_id'))->toContain('fallback Meta Cloud');
});

it('rejeita driver=zapi sem lgpd_acknowledged mesmo com bypass (LGPD imune ao bypass)', function () {
    config()->set('whatsapp.fallback.bypass_business_ids', [1]);

    $v = runRequest([
        'driver' => 'zapi',
        'zapi_instance_id' => 'inst-1',
        'zapi_instance_token' => 'tok-1',
        'zapi_client_token' => 'client-1',
        // lgpd_acknowledged AUSENTE de propósito
    ], businessId: 1);

    expect($v->fails())->toBeTrue();
    expect($v->errors()->first('lgpd_acknowledged'))->toContain('LGPD');
});

it('aceita driver=baileys (Sprint 3) com baileys_* + meta fallback + lgpd', function () {
    $v = runRequest([
        'driver' => 'baileys',
        'baileys_instance_id' => 'inst-baileys-1',
        'baileys_daemon_url' => 'https://whatsapp-baileys.oimpresso.local',
        'baileys_api_key' => str_repeat('K', 40),
        'meta_phone_number_id' => '123456789',
        'meta_access_token' => str_repeat('A', 60),
        'meta_app_secret' => 'secret-' . str_repeat('x', 20),
        'meta_webhook_verify_token' => 'verify-12345',
        'lgpd_acknowledged' => true,
    ]);

    expect($v->fails())->toBeFalse();
});
