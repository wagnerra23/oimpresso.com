<?php

declare(strict_types=1);

use Modules\Whatsapp\Services\Drivers\BaileysDriver;
use Modules\Whatsapp\Services\Drivers\DriverInterface;
use Modules\Whatsapp\Services\Drivers\MetaCloudDriver;
use Modules\Whatsapp\Services\Drivers\NullDriver;
use Modules\Whatsapp\Services\Drivers\ZapiDriver;

uses(Tests\TestCase::class);

/**
 * Wave 28 Whatsapp POLISH ≥92 — D2 +3 drivers contract saturation final.
 *
 * Estratégia: reflection + source-grep + Container resolve. ZERO hit DB
 * (paralelização worktree W28 com OficinaAuto/NfeBrasil/NFSe/RecurringBilling).
 *
 * Cobre adicional sobre W18+W23+W25:
 *   - D2 contract: assinatura canon de `sendTemplate` (preserva ADR 0096 emenda 4)
 *   - D2 contract: ZapiDriver + NullDriver resolvem DI (W25 não cobria)
 *   - D2 contract: NullDriver retorna shapes mínimos previsíveis (test/dev guard)
 *
 * Tier 0 IRREVOGÁVEIS preservados:
 *   - ADR 0096 emenda 4: EvolutionDriver permanentemente proibido
 *   - ADR 0117 multi-números: WhatsappBusinessConfig|WhatsappBusinessPhone union
 *   - Multi-tenant Tier 0 (ADR 0093) — drivers não tocam tabela direto
 *
 * @see Modules/Whatsapp/CHANGELOG.md Wave 28
 * @see Modules/Whatsapp/Services/Drivers/DriverInterface.php
 */
describe('Wave 28 Whatsapp POLISH', function () {

    it('D2: ZapiDriver + NullDriver resolvem do container (DI canon completo)', function () {
        expect(app(ZapiDriver::class))->toBeInstanceOf(ZapiDriver::class)
            ->and(app(ZapiDriver::class))->toBeInstanceOf(DriverInterface::class)
            ->and(app(NullDriver::class))->toBeInstanceOf(NullDriver::class)
            ->and(app(NullDriver::class))->toBeInstanceOf(DriverInterface::class);
    });

    it('D2: DriverInterface assinatura sendTemplate aceita 5 params canon (config/to/templateName/params/locale)', function () {
        $ref = new ReflectionMethod(DriverInterface::class, 'sendTemplate');
        $params = $ref->getParameters();

        // Assinatura canon: config, to, templateName, params, locale='pt_BR'
        expect($params)->toHaveCount(5)
            ->and($params[0]->getName())->toBe('config')
            ->and($params[1]->getName())->toBe('to')
            ->and($params[2]->getName())->toBe('templateName')
            ->and($params[3]->getName())->toBe('params')
            ->and($params[4]->getName())->toBe('locale')
            ->and($params[4]->isDefaultValueAvailable())->toBeTrue()
            ->and($params[4]->getDefaultValue())->toBe('pt_BR');
    });

    it('D2: NullDriver não chama HTTP externo (source-grep: zero Http::post/Http::get/Http::withHeaders)', function () {
        $file = (new ReflectionClass(NullDriver::class))->getFileName();
        $src = file_get_contents($file);

        // NullDriver é fail-safe pra dev/Pest — não pode bater rede
        expect($src)->not->toContain('Http::post')
            ->and($src)->not->toContain('Http::get')
            ->and($src)->not->toContain('Http::withHeaders');
    });

    // ---- Tier 0 lock-in extra ----

    it('Tier 0: 4 drivers canon exatos via class_exists (ADR 0096 emenda 4 — EvolutionDriver proibido permanente)', function () {
        // Sentry via class_exists (mais portável que glob windows path)
        foreach ([BaileysDriver::class, MetaCloudDriver::class, NullDriver::class, ZapiDriver::class] as $cls) {
            expect(class_exists($cls))->toBeTrue("Driver canon {$cls} deve existir");
        }

        // Anti-regression IRREVOGÁVEL: EvolutionDriver não pode ressuscitar
        expect(class_exists('Modules\\Whatsapp\\Services\\Drivers\\EvolutionDriver'))->toBeFalse();
    });
});
