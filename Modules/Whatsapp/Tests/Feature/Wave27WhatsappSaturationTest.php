<?php

declare(strict_types=1);

use Modules\Whatsapp\Services\Drivers\BaileysDriver;
use Modules\Whatsapp\Services\Drivers\DriverInterface;
use Modules\Whatsapp\Services\Drivers\MetaCloudDriver;
use Modules\Whatsapp\Services\Drivers\NullDriver;
use Modules\Whatsapp\Services\Drivers\ZapiDriver;

uses(Tests\TestCase::class);

/**
 * Wave 27 Whatsapp POLISH — D2 Drivers spans expandidos + D4 services pattern.
 *
 * Esforço (gap 74-88 → ≥90, +2pp polish final):
 *   - D2: confirma MetaCloudDriver cobre 5 spans canon (template/freeform/media/
 *     interactive/ping) — Wave 27 adicionou +3 spans (media/interactive/ping)
 *   - D2: BaileysDriver ≥3 spans hot-path (já validado W25; reforça contrato)
 *   - D4: Services pattern preservado (Wave 17/18/25) — Webhook + Metrics +
 *     Macros + Csat + Notes + Sla + Centrifugo + CustomerMemory + EmployeePerformance
 *     subdir presentes; cross-driver OtelHelper canon
 *   - Tier 0: EvolutionDriver permanece proibido (ADR 0096 emenda 4)
 *
 * Trust L0 — Reflection + source-grep, ZERO hit daemon Node / Meta API.
 *
 * @see Modules/Whatsapp/Services/Drivers/MetaCloudDriver.php
 * @see Modules/Whatsapp/Services/Drivers/BaileysDriver.php
 * @see memory/decisions/0096-modulo-whatsapp-meta-cloud-api-direto.md
 */

// ------------------------------------------------------------------
// D2/D9 — MetaCloudDriver 5 spans canon (W27 expandiu de 2 pra 5)
// ------------------------------------------------------------------

it('MetaCloudDriver expõe 5 spans canon (template/freeform/media/interactive/ping)', function () {
    $file = (new ReflectionClass(MetaCloudDriver::class))->getFileName();
    $src = file_get_contents($file);

    expect($src)->toContain('use App\Util\OtelHelper;');

    $spans = [
        'whatsapp.meta_cloud.send_template',
        'whatsapp.meta_cloud.send_freeform',
        'whatsapp.meta_cloud.send_media',
        'whatsapp.meta_cloud.send_interactive',
        'whatsapp.meta_cloud.ping',
    ];
    foreach ($spans as $span) {
        expect($src)->toContain("'{$span}'");
    }
});

it('BaileysDriver mantém ≥3 spans hot-path canon (W25 baseline preservado)', function () {
    $file = (new ReflectionClass(BaileysDriver::class))->getFileName();
    $src = file_get_contents($file);

    $matches = preg_match_all("/'whatsapp\\.baileys\\.[a-z_]+'/", $src);
    expect($matches)->toBeGreaterThanOrEqual(3);
    expect($src)->toContain('use App\Util\OtelHelper;');
});

// ------------------------------------------------------------------
// D4 — Services pattern saturado (preserva W17/W18/W25)
// ------------------------------------------------------------------

it('Services subdir canon todos presentes (D4 services pattern saturação)', function () {
    $base = __DIR__.'/../../Services';
    $expected = [
        'Drivers', 'Webhook', 'Metrics', 'Macros', 'Csat', 'Notes',
        'Sla', 'Centrifugo', 'CustomerMemory', 'EmployeePerformance',
        'Contacts', 'Audio',
    ];
    foreach ($expected as $sub) {
        expect(is_dir($base.'/'.$sub))->toBeTrue();
    }
});

it('Services top-level canon (InboxQueryService stateless multi-tenant)', function () {
    $base = __DIR__.'/../../Services';
    expect(is_file($base.'/InboxQueryService.php'))->toBeTrue();
});

// ------------------------------------------------------------------
// D2 — DI canon cross-driver (Container resolve)
// ------------------------------------------------------------------

it('4 drivers canon (Baileys + MetaCloud + Zapi + Null) resolvem do container', function () {
    foreach ([BaileysDriver::class, MetaCloudDriver::class, ZapiDriver::class, NullDriver::class] as $cls) {
        $instance = app($cls);
        expect($instance)->toBeInstanceOf($cls);
        expect($instance)->toBeInstanceOf(DriverInterface::class);
    }
});

// ------------------------------------------------------------------
// Tier 0 IRREVOGÁVEL — EvolutionDriver proibido permanente
// ------------------------------------------------------------------

it('EvolutionDriver permanece proibido (ADR 0096 emenda 4 — Tier 0)', function () {
    expect(class_exists('Modules\\Whatsapp\\Services\\Drivers\\EvolutionDriver'))->toBeFalse();
});

// ------------------------------------------------------------------
// D9 — OtelHelper fail-soft (otel.enabled=false zero-cost)
// ------------------------------------------------------------------

it('OtelHelper canon usado por ambos drivers (BaileysDriver + MetaCloudDriver)', function () {
    foreach ([BaileysDriver::class, MetaCloudDriver::class] as $cls) {
        $file = (new ReflectionClass($cls))->getFileName();
        $src = file_get_contents($file);
        expect($src)->toContain('use App\Util\OtelHelper;');
    }
});
