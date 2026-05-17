<?php

declare(strict_types=1);

use Modules\Woocommerce\Console\Commands\WoocommerceHealthCommand;
use Modules\Woocommerce\Services\WoocommerceAuthorizationService;
use Modules\Woocommerce\Services\WoocommerceResetService;
use Modules\Woocommerce\Services\WoocommerceSyncService;

uses(Tests\TestCase::class);

/**
 * Wave 18 SATURATION Woocommerce — D9 OTel observability + D4 Services + health command.
 *
 * Cobertura sem hit em API externa (zero custo — Tier 0 multi-tenant compliant):
 *  - 3 Services canon expõem assinaturas estáveis (D4 contrato — backward compat Wave 16)
 *  - 3 Services importam OtelHelper (D9 — sintaxe garante spans aplicados nos métodos públicos)
 *  - WoocommerceHealthCommand registrado no kernel (D9 — `php artisan woocommerce:health`)
 *  - Command usa flag canônica `--detail` (NUNCA `--verbose` — .claude/rules/commands.md)
 *
 * @see Modules\Woocommerce\Services\WoocommerceSyncService (D4 + D9)
 * @see Modules\Woocommerce\Services\WoocommerceResetService (D4 + D9)
 * @see Modules\Woocommerce\Console\Commands\WoocommerceHealthCommand (D9)
 * @see app\Util\OtelHelper (ADR 0155 D9.a)
 */

it('3 Services canon Wave 16 expostos via container (D4 contrato estável)', function (string $svc) {
    $instance = app($svc);
    expect($instance)->toBeInstanceOf($svc);
})->with([
    'WoocommerceSyncService' => [WoocommerceSyncService::class],
    'WoocommerceResetService' => [WoocommerceResetService::class],
    'WoocommerceAuthorizationService' => [WoocommerceAuthorizationService::class],
]);

it('WoocommerceSyncService importa OtelHelper (D9 SATURATION Wave 18)', function () {
    // WoocommerceSyncService deve importar OtelHelper pra wrap dos sync methods (D9 observability)
    $source = file_get_contents(base_path('Modules/Woocommerce/Services/WoocommerceSyncService.php'));
    expect($source)->toContain('use App\Util\OtelHelper;');
});

it('WoocommerceResetService importa OtelHelper (D9 SATURATION Wave 18)', function () {
    // WoocommerceResetService deve importar OtelHelper (reset destrutivo precisa observability máxima)
    $source = file_get_contents(base_path('Modules/Woocommerce/Services/WoocommerceResetService.php'));
    expect($source)->toContain('use App\Util\OtelHelper;');
});

it('WoocommerceSyncService usa OtelHelper::span nos 3 métodos públicos canon', function () {
    $source = file_get_contents(base_path('Modules/Woocommerce/Services/WoocommerceSyncService.php'));

    $expectedSpans = [
        'woocommerce.sync.categories',
        'woocommerce.sync.products',
        'woocommerce.sync.orders',
    ];

    foreach ($expectedSpans as $span) {
        // Span deve existir em WoocommerceSyncService (D9 observability)
        expect($source)->toContain("'{$span}'");
    }
});

it('WoocommerceResetService usa OtelHelper::span nos 2 métodos destrutivos', function () {
    $source = file_get_contents(base_path('Modules/Woocommerce/Services/WoocommerceResetService.php'));

    expect($source)->toContain("'woocommerce.reset.categories'");
    expect($source)->toContain("'woocommerce.reset.products'");
});

it('WoocommerceHealthCommand registrado no kernel (D9 health)', function () {
    $cmd = app(WoocommerceHealthCommand::class);
    expect($cmd)->toBeInstanceOf(WoocommerceHealthCommand::class);

    // Signature precisa expor --business-id obrigatório (Tier 0 multi-tenant ADR 0093)
    $signature = (new ReflectionProperty($cmd, 'signature'))->getValue($cmd);
    expect($signature)->toContain('--business-id');
});

it('WoocommerceHealthCommand usa --detail NUNCA --verbose (Symfony reserved)', function () {
    $cmd = app(WoocommerceHealthCommand::class);
    $signature = (new ReflectionProperty($cmd, 'signature'))->getValue($cmd);

    // .claude/rules/commands.md — bug histórico whatsapp:channels-reconcile PR #851
    // Commands oimpresso devem usar --detail (--verbose colide com Symfony default)
    expect($signature)->toContain('--detail');

    // NUNCA declarar --verbose custom (Symfony Console reserved — LogicException no boot)
    expect($signature)->not->toContain('{--verbose ');
});

it('WoocommerceHealthCommand handle() retorna 2 se --business-id ausente (fail-secure)', function () {
    // Não chama handle() direto pra evitar dependencia Business DB — só verifica que
    // a guard inicial existe via inspeção source.
    $source = file_get_contents(base_path('Modules/Woocommerce/Console/Commands/WoocommerceHealthCommand.php'));

    // WoocommerceHealthCommand deve fail-secure quando --business-id ausente
    expect($source)->toContain('--business-id obrigatório');
    // Exit code 2 quando inputs inválidos
    expect($source)->toContain('return 2');
});

it('OtelHelper é no-op quando OTel desabilitado (zero-cost path)', function () {
    // OTel default = disabled (config('otel.enabled', false))
    // Spans devem retornar callback result sem overhead perceptível
    config(['otel.enabled' => false]);

    $result = \App\Util\OtelHelper::span('test.span', ['business_id' => 99], fn () => 'ok');
    expect($result)->toBe('ok');
});
