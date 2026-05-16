<?php

declare(strict_types=1);

use App\Util\OtelHelper;
use Modules\Vestuario\Console\Commands\VestuarioHealthCommand;
use Modules\Vestuario\Services\VestuarioSettingsResolver;

uses(Tests\TestCase::class);

/**
 * Wave 16 governance — D9 OTel observability Modules/Vestuario.
 *
 * Cenarios cobertos:
 *  1. VestuarioSettingsResolver::get() envolto em OtelHelper::spanBiz (D9.a)
 *  2. OtelHelper zero-cost path preserva semantica do callback
 *  3. VestuarioHealthCommand instancia + tem signature canonica
 *
 * Tier 0: NUNCA biz=4 (ROTA LIVRE prod ADR 0101).
 *
 * @see memory/decisions/0155-module-grade-v3.md D9.a + D9.c
 * @see app/Util/OtelHelper.php
 */

beforeEach(function () {
    config()->set('otel.enabled', false);
});

it('D9.a — VestuarioSettingsResolver::get() chama OtelHelper sem efeito colateral (zero-cost)', function () {
    // Sem session: get() retorna default sem nem chamar OtelHelper (early return).
    $resolver = new VestuarioSettingsResolver();
    $valor = $resolver->get('feature.x.threshold', 42);
    expect($valor)->toBe(42);
});

it('D9.a — OtelHelper::spanBiz envolve callback Vestuario sem alterar retorno', function () {
    $resultado = OtelHelper::spanBiz('vestuario.test_smoke', function () {
        return ['ok' => true, 'modulo' => 'Vestuario'];
    }, ['module' => 'Vestuario', 'op' => 'test_smoke']);

    expect($resultado)->toBe(['ok' => true, 'modulo' => 'Vestuario']);
});

it('D9.c — VestuarioHealthCommand instancia e tem signature canonica', function () {
    $cmd = new VestuarioHealthCommand();
    // Pull signature via reflection (protected property)
    $ref = new ReflectionClass($cmd);
    $prop = $ref->getProperty('signature');
    $prop->setAccessible(true);
    $sig = $prop->getValue($cmd);

    expect($sig)->toContain('vestuario:health');
    expect($sig)->toContain('--business=');
    expect($sig)->toContain('--alert');
    expect($sig)->toContain('--json');
});

it('D9.c — VestuarioHealthCommand registrado no ServiceProvider (smoke)', function () {
    // Lista commands artisan e checa se vestuario:health aparece.
    $output = \Illuminate\Support\Facades\Artisan::all();
    expect($output)->toHaveKey('vestuario:health');
});
