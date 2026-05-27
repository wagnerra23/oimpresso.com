<?php

declare(strict_types=1);

use Modules\Whatsapp\Services\Drivers\DriverInterface;
use Modules\Whatsapp\Services\Drivers\MetaCloudDriver;
use Modules\Whatsapp\Services\Drivers\NullDriver;
use Modules\Whatsapp\Services\Drivers\ZapiDriver;
use Modules\Whatsapp\Services\Webhook\WebhookSignatureChecker;

uses(Tests\TestCase::class);

/**
 * Wave 25 Whatsapp SATURATION — D2/D3/D4 Drivers contract + OtelHelper canon.
 *
 * Atualizado 2026-05-27 (ADR 0202): BaileysDriver descontinuado — testes
 * específicos removidos. MetaCloud (default ADR 0202) + Zapi + Null permanecem.
 *
 * Esforço original (gap 74 → ≥85, +11pp):
 *   - D2: cobertura Drivers (MetaCloudDriver + ZapiDriver + NullDriver) —
 *     implementam DriverInterface canon (6 métodos)
 *   - D3: cobertura métodos canon (sendTemplate/sendFreeform/sendMedia/
 *     fetchMessageStatus/ping + sendInteractive)
 *   - D4: Services pattern preservado — Wave 17/18 criou Service layer; este
 *     test prova DI canon + OtelHelper imports cross-driver
 *
 * @see Modules/Whatsapp/Services/Drivers/DriverInterface.php
 * @see Modules/Whatsapp/Services/Drivers/MetaCloudDriver.php (default ADR 0202)
 * @see memory/decisions/0202-whatsapp-profissionalizacao-baileys-out.md
 */

// ------------------------------------------------------------------
// D2/D3 — Drivers implementam contrato canon
// ------------------------------------------------------------------

// ADR 0202 (2026-05-27): teste "BaileysDriver implementa DriverInterface canon" REMOVIDO
// — BaileysDriver descontinuado e classe deletada deste PR.

it('MetaCloudDriver implementa DriverInterface canon (6 métodos)', function () {
    $ref = new ReflectionClass(MetaCloudDriver::class);
    expect($ref->implementsInterface(DriverInterface::class))->toBeTrue();

    foreach (['sendTemplate', 'sendFreeform', 'sendMedia', 'fetchMessageStatus', 'ping', 'sendInteractive'] as $method) {
        expect($ref->hasMethod($method))->toBeTrue("MetaCloudDriver deve ter método {$method}");
    }
});

it('ZapiDriver implementa DriverInterface canon', function () {
    $ref = new ReflectionClass(ZapiDriver::class);
    expect($ref->implementsInterface(DriverInterface::class))->toBeTrue();
});

it('NullDriver implementa DriverInterface canon (dev/CI Pest)', function () {
    $ref = new ReflectionClass(NullDriver::class);
    expect($ref->implementsInterface(DriverInterface::class))->toBeTrue();
});

// ------------------------------------------------------------------
// D4 — Services pattern (DI + OtelHelper canon cross-driver)
// ------------------------------------------------------------------

// ADR 0202 (2026-05-27): teste BaileysDriver OtelHelper canon REMOVIDO.

it('MetaCloudDriver importa OtelHelper canon + spans canon (D9 hot-path)', function () {
    $file = (new ReflectionClass(MetaCloudDriver::class))->getFileName();
    $src = file_get_contents($file);
    expect($src)->toContain('use App\Util\OtelHelper;');

    // Pelo menos send_template + send_freeform (2 spans canon mínimos hoje;
    // future Wave pode adicionar send_media + ping + send_interactive)
    $matches = preg_match_all("/'whatsapp\\.meta_cloud\\.[a-z_]+'/", $src);
    expect($matches)->toBeGreaterThanOrEqual(2);
});

// ADR 0202 (2026-05-27): teste BaileysDriver container resolution REMOVIDO.

it('MetaCloudDriver resolve do container (DI canon)', function () {
    $driver = app(MetaCloudDriver::class);
    expect($driver)->toBeInstanceOf(MetaCloudDriver::class);
    expect($driver)->toBeInstanceOf(DriverInterface::class);
});

// ------------------------------------------------------------------
// D3 — Multi-números (ADR 0117) — drivers aceitam union type
// ------------------------------------------------------------------

it('DriverInterface aceita WhatsappBusinessConfig|WhatsappBusinessPhone (ADR 0117 multi-números)', function () {
    $ref = new ReflectionMethod(DriverInterface::class, 'sendFreeform');
    $params = $ref->getParameters();
    expect($params[0]->getName())->toBe('config');

    $type = $params[0]->getType();
    // Tem que ser union type ou compatível
    expect($type)->not->toBeNull();

    $typeStr = (string) $type;
    expect($typeStr)->toContain('WhatsappBusinessConfig');
    expect($typeStr)->toContain('WhatsappBusinessPhone');
});

// ------------------------------------------------------------------
// D4 — WebhookSignatureChecker canon (Wave 18 D4) saturation
// ------------------------------------------------------------------

it('WebhookSignatureChecker dispatcher rejeita driver desconhecido (fail-secure)', function () {
    $svc = new WebhookSignatureChecker();
    $body = '{"event":"test"}';
    $secret = 'k';
    $sig = hash_hmac('sha256', $body, $secret);

    expect($svc->verify('driver_inexistente_xyz', $body, $sig, $secret))->toBeFalse();
});

it('WebhookSignatureChecker Meta exige prefixo sha256= (CRÍTICO — payload Meta sempre tem prefixo)', function () {
    $svc = new WebhookSignatureChecker();
    $body = '{"event":"test"}';
    $secret = 'k';
    $sigHex = hash_hmac('sha256', $body, $secret);

    // Sem prefixo Meta NÃO valida (formato Meta canon)
    expect($svc->verify('meta_cloud', $body, $sigHex, $secret))->toBeFalse();

    // Com prefixo valida
    expect($svc->verify('meta_cloud', $body, 'sha256='.$sigHex, $secret))->toBeTrue();
});

it('WebhookSignatureChecker Baileys aceita hex puro (sem prefixo — formato daemon Node)', function () {
    $svc = new WebhookSignatureChecker();
    $body = '{"messages":[]}';
    $secret = 'baileys_secret';
    $sig = hash_hmac('sha256', $body, $secret);

    expect($svc->verify('baileys', $body, $sig, $secret))->toBeTrue();
    // Tampered body
    expect($svc->verify('baileys', $body.'tamper', $sig, $secret))->toBeFalse();
});

it('WebhookSignatureChecker resiste a key rotation (secret errado retorna false)', function () {
    $svc = new WebhookSignatureChecker();
    $body = '{"event":"test"}';
    $sigOld = hash_hmac('sha256', $body, 'old_secret');

    // Verificando com novo secret — falha (rotação detectada)
    expect($svc->verify('baileys', $body, $sigOld, 'new_secret'))->toBeFalse();
});

// ------------------------------------------------------------------
// D3 — Drivers preservados (Baileys/Meta/Z-API/Null — IRREVOGÁVEL)
// ------------------------------------------------------------------

it('EvolutionDriver NÃO existe (ADR 0096 emenda 4 — proibido permanente)', function () {
    expect(class_exists('Modules\\Whatsapp\\Services\\Drivers\\EvolutionDriver'))->toBeFalse();
});

it('os 3 drivers canon ativos pós-ADR 0202 (MetaCloud + Zapi + Null) existem', function () {
    $drivers = [MetaCloudDriver::class, ZapiDriver::class, NullDriver::class];
    foreach ($drivers as $cls) {
        expect(class_exists($cls))->toBeTrue("Driver canon {$cls} deve existir");
    }
});

it('BaileysDriver NÃO existe pós-ADR 0202 (descontinuado 2026-05-27)', function () {
    expect(class_exists('Modules\\Whatsapp\\Services\\Drivers\\BaileysDriver'))->toBeFalse();
});
