<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Modules\Connector\Http\Requests\AcceptDelphiTokenHandshakeRequest;
use Modules\Connector\Http\Requests\StoreNotificationDeliveryRequest;
use Modules\Connector\Services\DelphiSyncService;

uses(Tests\TestCase::class);

/**
 * Wave 23 Connector Saturation Test — D8 SECURITY + D9 OBS + customer journey.
 *
 * Cobertura:
 *   1. StoreNotificationDeliveryRequest (D8) — valida channel/status/external_id
 *   2. AcceptDelphiTokenHandshakeRequest (D8) — anti-spoofing + HD upper coerce
 *   3. DelphiSyncService Wave 23 spans (D9) — formatLegacyResponse / logDrift /
 *      detectBodyFormatWithSpan agora wrapped
 *   4. Customer journey end-to-end smoke (FormRequests cadeia) + Delphi handshake
 *
 * Multi-tenant Tier 0: Connector é API external — business_id vem do Passport
 * authenticated user OU explícito na URL Delphi (anti-spoofing).
 *
 * @see Modules\Connector\Http\Requests\StoreNotificationDeliveryRequest
 * @see Modules\Connector\Http\Requests\AcceptDelphiTokenHandshakeRequest
 * @see Modules\Connector\Services\DelphiSyncService
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */

beforeEach(function () {
    config(['otel.enabled' => false]);  // zero-cost path
});

// ---------- D8 SECURITY: StoreNotificationDeliveryRequest ----------

it('StoreNotificationDeliveryRequest valida channel em whitelist canônica', function () {
    $req = new StoreNotificationDeliveryRequest();
    $rules = $req->rules();

    expect($rules)->toHaveKeys(['channel', 'status', 'external_message_id', 'recipient']);

    $channelRule = collect($rules['channel'])->first(fn ($r) => is_string($r) && str_starts_with($r, 'in:'));
    expect($channelRule)->toContain('whatsapp_baileys', 'whatsapp_meta', 'email', 'sms');
});

it('StoreNotificationDeliveryRequest valida status whitelist (queued..banned)', function () {
    $req = new StoreNotificationDeliveryRequest();
    $rules = $req->rules();

    $statusRule = collect($rules['status'])->first(fn ($r) => is_string($r) && str_starts_with($r, 'in:'));
    expect($statusRule)->toContain('queued', 'sent', 'delivered', 'read', 'failed', 'banned');
});

it('StoreNotificationDeliveryRequest exige external_message_id (anti-duplicate)', function () {
    $req = new StoreNotificationDeliveryRequest();
    $rules = $req->rules();

    expect($rules['external_message_id'])->toContain('required', 'string', 'max:255');
});

it('StoreNotificationDeliveryRequest messages PT-BR cobrem campos críticos', function () {
    $req = new StoreNotificationDeliveryRequest();
    $msgs = $req->messages();

    expect($msgs)->toHaveKey('channel.required');
    expect($msgs)->toHaveKey('status.required');
    expect($msgs)->toHaveKey('external_message_id.required');
    expect($msgs['external_message_id.required'])->toContain('anti-duplicate');
});

// ---------- D8 SECURITY: AcceptDelphiTokenHandshakeRequest ----------

it('AcceptDelphiTokenHandshakeRequest exige hd + serial + versao', function () {
    $req = new AcceptDelphiTokenHandshakeRequest();
    $rules = $req->rules();

    expect($rules)->toHaveKeys(['hd', 'serial', 'versao']);
    expect($rules['hd'])->toContain('required', 'string', 'min:6', 'max:64');
    expect($rules['serial'])->toContain('required', 'string', 'min:8', 'max:120');
});

it('AcceptDelphiTokenHandshakeRequest PROIBE business_id no body (anti-spoofing Tier 0)', function () {
    $req = new AcceptDelphiTokenHandshakeRequest();
    $rules = $req->rules();

    expect($rules)->toHaveKey('business_id');
    expect($rules['business_id'])->toContain('prohibited');
});

it('AcceptDelphiTokenHandshakeRequest coerce hd pra UPPER (DelphiSyncService convenção)', function () {
    $req = AcceptDelphiTokenHandshakeRequest::create('/connector/api/handshake/1', 'POST', [
        'hd' => 'abc123def',
        'serial' => 'serial12345',
        'versao' => '1.2.3',
    ]);

    $req->setContainer(app());
    $req->setRedirector(app('redirect'));

    try {
        $req->validateResolved();
    } catch (\Throwable $e) {
        // Ignora outros falhas — interessa só ver o coerce
    }

    expect($req->input('hd'))->toBe('ABC123DEF', 'hd deve ser coerced pra UPPER');
});

it('AcceptDelphiTokenHandshakeRequest authorize() retorna true (sem session Laravel)', function () {
    $req = new AcceptDelphiTokenHandshakeRequest();

    // Delphi cliente não tem session — autorização é no DelphiSyncService.
    expect($req->authorize())->toBeTrue();
});

it('AcceptDelphiTokenHandshakeRequest messages PT-BR cobrem hd/serial/versao/business_id', function () {
    $req = new AcceptDelphiTokenHandshakeRequest();
    $msgs = $req->messages();

    expect($msgs)->toHaveKey('hd.required');
    expect($msgs)->toHaveKey('serial.required');
    expect($msgs)->toHaveKey('versao.required');
    expect($msgs)->toHaveKey('business_id.prohibited');
    expect($msgs['business_id.prohibited'])->toContain('URL');
});

// ---------- D9 OBSERVABILIDADE: spans Wave 23 ----------

it('DelphiSyncService::formatLegacyResponse Wave 23 wrapped em spanBiz', function () {
    $svc = new DelphiSyncService();
    $result = $svc->formatLegacyResponse(true, 'tudo ok');

    expect($result)->toBe('S;tudo ok');

    // Verifica via grep no source que método tem spanBiz
    $source = file_get_contents(base_path('Modules/Connector/Services/DelphiSyncService.php'));
    expect(str_contains($source, "spanBiz('connector.delphi.format_legacy_response'"))->toBeTrue(
        'formatLegacyResponse deve estar wrapped em spanBiz Wave 23 D9'
    );
});

it('DelphiSyncService::logDrift Wave 23 wrapped em spanBiz', function () {
    $svc = new DelphiSyncService();
    $svc->logDrift('test_reason_w23', ['ctx' => 'unit_test']);

    $source = file_get_contents(base_path('Modules/Connector/Services/DelphiSyncService.php'));
    expect(str_contains($source, "spanBiz('connector.delphi.log_drift'"))->toBeTrue(
        'logDrift deve estar wrapped em spanBiz Wave 23 D9'
    );
});

it('DelphiSyncService::detectBodyFormatWithSpan Wave 23 disponível com OTel', function () {
    $svc = new DelphiSyncService();
    $format = $svc->detectBodyFormatWithSpan('{}');

    expect($format)->toBe('unknown');

    // pipe format
    expect($svc->detectBodyFormatWithSpan('ABC123|HOST|1.0|192.168.1.1|12345678000190|RAZAO'))->toBe('pipe');
});

// ---------- Customer journey extended: Delphi handshake + delivery ----------

it('customer journey Wave 23: handshake Delphi + delivery webhook (rules canônicas)', function () {
    // Step 1: handshake Delphi cliente
    $handshakeReq = new AcceptDelphiTokenHandshakeRequest();
    expect($handshakeReq->rules())->toHaveKey('hd');

    // Step 2: notificação delivery (após handshake aprovado)
    $deliveryReq = new StoreNotificationDeliveryRequest();
    expect($deliveryReq->rules())->toHaveKey('external_message_id');

    // Step 3: Service de extração HD/CNPJ disponível pra resolver business_id
    $delphi = new DelphiSyncService();
    $request = Request::create('/connector/api/handshake/1', 'POST', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
    ], json_encode(['serial_hd' => 'abc123def', 'cnpj' => '12345678000190']));

    $hd = $delphi->extractHd($request);
    expect($hd)->toBe('ABC123DEF', 'extractHd deve normalizar pra UPPER');

    // Cadeia completa não levantou exception → smoke OK
    expect(true)->toBeTrue();
});

it('FormRequests Wave 23 (Delivery+Handshake) seguem pattern PT-BR + max chars defensive', function () {
    foreach ([StoreNotificationDeliveryRequest::class, AcceptDelphiTokenHandshakeRequest::class] as $class) {
        $req = new $class();
        $rules = $req->rules();
        $hasMax = false;

        foreach ($rules as $fieldRules) {
            $list = is_array($fieldRules) ? $fieldRules : [$fieldRules];
            foreach ($list as $r) {
                if (is_string($r) && str_starts_with($r, 'max:')) {
                    $hasMax = true;
                    break 2;
                }
            }
        }

        expect($hasMax)->toBeTrue("{$class} deve ter ao menos 1 'max:N' (defensive cap)");
    }
});
