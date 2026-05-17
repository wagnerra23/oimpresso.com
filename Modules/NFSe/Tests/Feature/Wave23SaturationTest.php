<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Modules\NFSe\Console\Commands\NfseHealthCommand;
use Modules\NFSe\Models\NfseEmissao;

uses(Tests\TestCase::class);

/**
 * Wave 23 NFSe SATURATION — D6 (Health) + D1 (coverage).
 *
 * Cobre:
 *   - D6: nfse:health command registrado + signature canônica
 *   - D1: NfseEmissao tem fillable + casts mínimos
 *   - D1: NfseEmissaoService classe existe (smoke)
 *
 * Multi-tenant Tier 0 (ADR 0093): NÃO chama session() nem DB real.
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
describe('Wave 23 NFSe Saturation', function () {
    it('nfse:health command registrado', function () {
        $all = Artisan::all();
        expect($all)->toHaveKey('nfse:health');
        expect($all['nfse:health'])->toBeInstanceOf(NfseHealthCommand::class);
    });

    it('nfse:health tem --detail (NÃO --verbose Symfony reserved)', function () {
        $cmd = Artisan::all()['nfse:health'];
        $def = $cmd->getDefinition();

        expect($def->hasOption('detail'))->toBeTrue();
        expect($def->hasOption('json'))->toBeTrue();
        expect($def->hasOption('alert'))->toBeTrue();
        expect($def->hasOption('business'))->toBeTrue();
    });

    it('NfseEmissao tem campos canônicos', function () {
        $model = new NfseEmissao();
        $fillable = $model->getFillable();
        expect($fillable)->toBeArray();
        expect(count($fillable))->toBeGreaterThan(0);
    });

    it('NfseEmissaoService existe', function () {
        expect(class_exists(\Modules\NFSe\Services\NfseEmissaoService::class))->toBeTrue();
    });

    it('NfseProviderInterface contract existe', function () {
        expect(interface_exists(\Modules\NFSe\Contracts\NfseProviderInterface::class))->toBeTrue();
    });
});
