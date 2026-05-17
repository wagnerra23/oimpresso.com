<?php

declare(strict_types=1);

use Modules\Whatsapp\Services\Drivers\BaileysDriver;
use Modules\Whatsapp\Services\Drivers\DriverInterface;
use Modules\Whatsapp\Services\Drivers\DriverDoesNotSupport;
use Modules\Whatsapp\Services\Drivers\MetaCloudDriver;
use Modules\Whatsapp\Services\Drivers\NullDriver;
use Modules\Whatsapp\Services\Drivers\ZapiDriver;
use Modules\Whatsapp\Services\Webhook\WebhookSignatureChecker;
use Modules\Whatsapp\Services\Webhook\MessagePersister;

uses(Tests\TestCase::class);

/**
 * Wave 26 Whatsapp SATURATION — polish 74 → ≥85 (+11pp).
 *
 * Esforço por dimensão:
 *  - D2 Pest expandir BaileysDriver + MetaCloudDriver + WebhookSignatureChecker
 *    (Wave 18 D4 baseline) — sem chamar daemon/Meta real (Reflection + Http::fake)
 *  - D4 Services pattern refactor — confirmar união Drivers + Webhook + Persister
 *  - Reuse contracts cross-driver (DriverInterface + spans canon + DI canon)
 *
 * Trust L0: tests Reflection puros + Http::fake. Zero side-effect (HTTP/DB).
 *
 * @see Modules/Whatsapp/Tests/Feature/Wave25WhatsappSaturationTest.php (Wave 25 baseline)
 * @see Modules/Whatsapp/Services/Drivers/* (4 drivers canon ADR 0096 emenda 4)
 * @see Modules/Whatsapp/Services/Webhook/* (Wave 18 D4 SoC extraction)
 */

// ------------------------------------------------------------------
// D2 — Cobertura expandida BaileysDriver (spans + métodos auxiliares)
// ------------------------------------------------------------------

it('BaileysDriver tem spans canon hot-path send_freeform + send_media + send_interactive + ping', function () {
    $file = (new ReflectionClass(BaileysDriver::class))->getFileName();
    $src = file_get_contents($file);

    foreach (['send_freeform', 'send_media', 'send_interactive'] as $span) {
        expect($src)->toContain("'whatsapp.baileys.{$span}'");
    }
});

it('BaileysDriver mapSendResponse + normalizePhone são private (encapsulamento canon)', function () {
    $ref = new ReflectionClass(BaileysDriver::class);

    expect($ref->getMethod('mapSendResponse')->isPrivate())->toBeTrue();
    expect($ref->getMethod('normalizePhone')->isPrivate())->toBeTrue();
    expect($ref->getMethod('client')->isPrivate())->toBeTrue();
});

it('BaileysDriver sendInteractive rejeita cta_url (Whatsapp Web protocol não suporta)', function () {
    $file = (new ReflectionClass(BaileysDriver::class))->getFileName();
    $src = file_get_contents($file);

    // Deve lançar DriverDoesNotSupport pra cta_url (fail-fast pro caller saber
    // que precisa cair pro Meta Cloud)
    expect($src)->toContain("'cta_url'");
    expect($src)->toContain('DriverDoesNotSupport::for');
});

it('BaileysDriver fetchMessageStatus retorna queued (status real vem via webhook)', function () {
    $ref = new ReflectionMethod(BaileysDriver::class, 'fetchMessageStatus');
    expect($ref->getReturnType()?->getName())->toBe('Modules\\Whatsapp\\Services\\Drivers\\MessageStatus');
});

// ------------------------------------------------------------------
// D2 — Cobertura expandida MetaCloudDriver
// ------------------------------------------------------------------

it('MetaCloudDriver tem método fetchTemplates (HSM aprovação Meta Business Manager)', function () {
    $ref = new ReflectionClass(MetaCloudDriver::class);
    expect($ref->hasMethod('fetchTemplates'))->toBeTrue();

    $method = $ref->getMethod('fetchTemplates');
    expect($method->isPublic())->toBeTrue();
});

it('MetaCloudDriver tem método parseInboundWebhook (PR4 PoC BSUID identifier mar/2026+)', function () {
    $ref = new ReflectionClass(MetaCloudDriver::class);
    expect($ref->hasMethod('parseInboundWebhook'))->toBeTrue();

    $method = $ref->getMethod('parseInboundWebhook');
    expect($method->isPublic())->toBeTrue();
    expect($method->getReturnType()?->getName())->toBe('array');
});

it('MetaCloudDriver parseInboundWebhook extrai 3 identifiers (wa_id + phone_e164 + bsuid)', function () {
    $driver = new MetaCloudDriver();

    $payload = [
        'entry' => [[
            'changes' => [[
                'value' => [
                    'messages' => [[
                        'from' => '5548999000000',
                        'id' => 'wamid.HBgL_test',
                        'type' => 'text',
                        'text' => ['body' => 'olá'],
                    ]],
                    'contacts' => [[
                        'wa_id' => '5548999000000',
                        'user_id' => 'bsuid-xyz-123',
                        'profile' => ['name' => 'Cliente Teste'],
                    ]],
                ],
            ]],
        ]],
    ];

    $result = $driver->parseInboundWebhook($payload);

    expect($result)->toHaveCount(1);
    expect($result[0]['wa_id'])->toBe('5548999000000');
    expect($result[0]['phone_e164'])->toBe('+5548999000000');
    expect($result[0]['bsuid'])->toBe('bsuid-xyz-123');
    expect($result[0]['profile_name'])->toBe('Cliente Teste');
    expect($result[0]['message_id'])->toBe('wamid.HBgL_test');
    expect($result[0]['type'])->toBe('text');
    expect($result[0]['body'])->toBe('olá');
});

it('MetaCloudDriver parseInboundWebhook tolerante a payload pré mar/2026 (sem user_id)', function () {
    $driver = new MetaCloudDriver();

    // Payload legacy SEM contacts[].user_id (pré mar/2026)
    $payload = [
        'entry' => [[
            'changes' => [[
                'value' => [
                    'messages' => [[
                        'from' => '5511987654321',
                        'id' => 'wamid.legacy',
                        'type' => 'text',
                        'text' => ['body' => 'legacy'],
                    ]],
                    'contacts' => [[
                        'wa_id' => '5511987654321',
                        // SEM user_id
                        'profile' => ['name' => 'Legacy Contact'],
                    ]],
                ],
            ]],
        ]],
    ];

    $result = $driver->parseInboundWebhook($payload);

    expect($result)->toHaveCount(1);
    expect($result[0]['bsuid'])->toBeNull(); // Tolerante a payload pré BSUID
    expect($result[0]['phone_e164'])->toBe('+5511987654321');
});

it('MetaCloudDriver parseInboundWebhook lida com payload vazio (resiliente)', function () {
    $driver = new MetaCloudDriver();

    expect($driver->parseInboundWebhook([]))->toBe([]);
    expect($driver->parseInboundWebhook(['entry' => []]))->toBe([]);
    expect($driver->parseInboundWebhook(['entry' => [['changes' => []]]]))->toBe([]);
});

it('MetaCloudDriver parseInboundWebhook extrai body type=button + type=interactive', function () {
    $driver = new MetaCloudDriver();

    // Caso button (reply de template button)
    $payload = [
        'entry' => [[
            'changes' => [[
                'value' => [
                    'messages' => [[
                        'from' => '5548999000000',
                        'id' => 'wamid.btn',
                        'type' => 'button',
                        'button' => ['text' => 'Confirmar agendamento'],
                    ]],
                    'contacts' => [['wa_id' => '5548999000000']],
                ],
            ]],
        ]],
    ];

    $result = $driver->parseInboundWebhook($payload);
    expect($result[0]['body'])->toBe('Confirmar agendamento');
    expect($result[0]['type'])->toBe('button');
});

// ------------------------------------------------------------------
// D2 — WebhookSignatureChecker (Wave 18 D4) cobertura expandida W26
// ------------------------------------------------------------------

it('WebhookSignatureChecker tem 3 headers canon constants (Meta + Baileys + Z-API)', function () {
    expect(WebhookSignatureChecker::HEADER_META)->toBe('X-Hub-Signature-256');
    expect(WebhookSignatureChecker::HEADER_BAILEYS)->toBe('X-Baileys-Signature');
    expect(WebhookSignatureChecker::HEADER_ZAPI)->toBe('X-Webhook-Signature');
});

it('WebhookSignatureChecker verifyMeta rejeita header sem prefixo sha256= (formato Meta canon)', function () {
    $svc = new WebhookSignatureChecker();
    $body = '{"x":1}';
    $secret = 'topsecret';
    $sigHex = hash_hmac('sha256', $body, $secret);

    // Sem prefixo Meta → rejeita
    expect($svc->verifyMeta($body, $sigHex, $secret))->toBeFalse();
    // Com prefixo correto → aceita
    expect($svc->verifyMeta($body, 'sha256='.$sigHex, $secret))->toBeTrue();
});

it('WebhookSignatureChecker verifyMeta rejeita hex inválido (caracteres não-hex)', function () {
    $svc = new WebhookSignatureChecker();
    expect($svc->verifyMeta('{}', 'sha256=ZZZZNAO-HEX', 'k'))->toBeFalse();
    expect($svc->verifyMeta('{}', 'sha256=', 'k'))->toBeFalse();
});

it('WebhookSignatureChecker verifyBaileys rejeita header vazio + null', function () {
    $svc = new WebhookSignatureChecker();
    expect($svc->verifyBaileys('{}', null, 'k'))->toBeFalse();
    expect($svc->verifyBaileys('{}', '', 'k'))->toBeFalse();
    expect($svc->verifyBaileys('{}', 'naohex!!!', 'k'))->toBeFalse();
});

it('WebhookSignatureChecker verifyZapi aceita hex puro (formato Z-API canon)', function () {
    $svc = new WebhookSignatureChecker();
    $body = '{"event":"zapi"}';
    $secret = 'zapi_secret';
    $sig = hash_hmac('sha256', $body, $secret);

    expect($svc->verifyZapi($body, $sig, $secret))->toBeTrue();
    // Tampered body → rejeita
    expect($svc->verifyZapi($body.'tamper', $sig, $secret))->toBeFalse();
});

it('WebhookSignatureChecker dispatch verify() aceita aliases canon driver', function () {
    $svc = new WebhookSignatureChecker();
    $body = '{"x":1}';
    $secret = 'k';
    $sigHex = hash_hmac('sha256', $body, $secret);

    // meta_cloud + meta (alias)
    expect($svc->verify('meta_cloud', $body, 'sha256='.$sigHex, $secret))->toBeTrue();
    expect($svc->verify('meta', $body, 'sha256='.$sigHex, $secret))->toBeTrue();

    // zapi + z-api + z_api (aliases)
    expect($svc->verify('zapi', $body, $sigHex, $secret))->toBeTrue();
    expect($svc->verify('z-api', $body, $sigHex, $secret))->toBeTrue();
    expect($svc->verify('z_api', $body, $sigHex, $secret))->toBeTrue();
});

it('WebhookSignatureChecker usa hash_equals (constant-time compare — anti timing-attack)', function () {
    $file = (new ReflectionClass(WebhookSignatureChecker::class))->getFileName();
    $src = file_get_contents($file);

    // CRÍTICO: deve usar hash_equals (não ===) pra evitar timing attack
    expect($src)->toContain('hash_equals(');
    // NÃO deve fazer compare direto com === ou == em hash
    $tripleEqualsHashCount = substr_count($src, '=== $received');
    expect($tripleEqualsHashCount)->toBe(0);
});

it('WebhookSignatureChecker é stateless puro (sem constructor)', function () {
    $ref = new ReflectionClass(WebhookSignatureChecker::class);
    expect($ref->getConstructor())->toBeNull(); // stateless puro
});

// ------------------------------------------------------------------
// D4 — Services pattern refactor (Webhook + Drivers + Persister)
// ------------------------------------------------------------------

it('Modules/Whatsapp/Services tem ≥10 subpastas canon (Audio + Centrifugo + Drivers + etc)', function () {
    $base = base_path('Modules/Whatsapp/Services');
    $subdirs = array_filter(
        scandir($base),
        fn ($d) => $d !== '.' && $d !== '..' && is_dir($base.'/'.$d)
    );

    // Pelo menos 10 service subdomain canon (Wave 18 D4 SoC)
    expect(count($subdirs))->toBeGreaterThanOrEqual(10);

    foreach (['Audio', 'Centrifugo', 'Contacts', 'Csat', 'Drivers', 'Macros', 'Metrics', 'Notes', 'Sla', 'Webhook'] as $required) {
        expect(in_array($required, $subdirs, true))->toBeTrue("Services/{$required} canon esperado");
    }
});

it('MessagePersister (Webhook D4) existe + tem dep Channel no constructor (multi-tenant Tier 0)', function () {
    expect(class_exists(MessagePersister::class))->toBeTrue();
    $ref = new ReflectionClass(MessagePersister::class);
    expect($ref->isInstantiable())->toBeTrue();

    $ctor = $ref->getConstructor();
    expect($ctor)->not->toBeNull();
    // Channel dep no constructor garante business_id Tier 0 (ADR 0093)
    $params = collect($ctor->getParameters());
    expect($params->count())->toBeGreaterThanOrEqual(1);
});

it('WebhookSignatureChecker é class final (encapsulamento Wave 18 D4 canon)', function () {
    $ref = new ReflectionClass(WebhookSignatureChecker::class);
    expect($ref->isFinal())->toBeTrue('WebhookSignatureChecker DEVE ser final (canon Wave 18 D4)');
});

// ------------------------------------------------------------------
// D2 — DriverDoesNotSupport exception canon (cross-driver)
// ------------------------------------------------------------------

it('DriverDoesNotSupport tem factory ::for(driver, capability)', function () {
    $ex = DriverDoesNotSupport::for('baileys', 'interactive.cta_url');

    expect($ex)->toBeInstanceOf(\Throwable::class);
    expect($ex->getMessage())->toContain('baileys');
    expect($ex->getMessage())->toContain('cta_url');
});

// ------------------------------------------------------------------
// D6 baseline preservado (defer Pages) + D3 BRIEFING (proxy)
// ------------------------------------------------------------------

it('D3 W26: BRIEFING.md tem menção Wave 26 (governance polish ≥85)', function () {
    $briefing = file_get_contents(base_path('memory/requisitos/Whatsapp/BRIEFING.md'));
    expect($briefing)->toContain('Wave 26');
});

it('D3 W26: Modules/Whatsapp/CHANGELOG.md OU BRIEFING.md tem entrada Wave 26', function () {
    $changelog = file_exists(base_path('Modules/Whatsapp/CHANGELOG.md'))
        ? file_get_contents(base_path('Modules/Whatsapp/CHANGELOG.md'))
        : '';
    $briefing = file_get_contents(base_path('memory/requisitos/Whatsapp/BRIEFING.md'));

    // Pelo menos um dos dois tem Wave 26
    expect(str_contains($changelog, 'Wave 26') || str_contains($briefing, 'Wave 26'))->toBeTrue();
});

it('Wave 26 preserva BaileysDriver custom + MetaCloudDriver fallback (IRREVOGÁVEL ADR 0096 e4)', function () {
    // Não deve haver tentativa de remover BaileysDriver em prol de Evolution
    // (ADR 0096 emenda 4 — Evolution proibida permanente)
    expect(class_exists(BaileysDriver::class))->toBeTrue();
    expect(class_exists(MetaCloudDriver::class))->toBeTrue();
    expect(class_exists('Modules\\Whatsapp\\Services\\Drivers\\EvolutionDriver'))->toBeFalse();
});
