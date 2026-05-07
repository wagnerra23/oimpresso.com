<?php

declare(strict_types=1);

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Modules\Whatsapp\Http\Requests\BusinessSettingsRequest;

uses(Tests\TestCase::class);

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

function runRequest(array $input): \Illuminate\Validation\Validator
{
    $request = BusinessSettingsRequest::create('/whatsapp/settings', 'PUT', $input);
    $request->setContainer(app())->setRedirector(app(\Illuminate\Routing\Redirector::class));

    return Validator::make(
        $request->all(),
        (new BusinessSettingsRequest())->rules(),
        (new BusinessSettingsRequest())->messages(),
    )->after(function ($v) use ($request, $input) {
        // Replay do withValidator (cross-field) — instancia request com input pra closures lerem
        $req = new BusinessSettingsRequest();
        $req->merge($input);
        $req->setContainer(app());
        $req->withValidator($v);
    });
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
