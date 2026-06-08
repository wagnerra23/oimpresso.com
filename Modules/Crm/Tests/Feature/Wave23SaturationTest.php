<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Modules\Crm\Console\Commands\CrmHealthCommand;
use Modules\Crm\Contracts\CrmLeadRepositoryInterface;
use Modules\Crm\Http\Requests\DeleteProposalRequest;
use Modules\Crm\Http\Requests\IndexLeadRequest;
use Modules\Crm\Repositories\CrmLeadRepository;

uses(Tests\TestCase::class);

/**
 * Wave 23 Crm SATURATION — D2 (reuse) + D6 (Health) + D8 (FormRequests adicionais).
 *
 * Cobre:
 *   - D2: CrmLeadRepositoryInterface existe e CrmLeadRepository implementa
 *   - D2: Container resolve interface => concrete (bind no Provider)
 *   - D6: CrmHealthCommand registrado em artisan
 *   - D8: IndexLeadRequest + DeleteProposalRequest carregam rules() sem hit DB
 *
 * Multi-tenant Tier 0 (ADR 0093): NÃO toca DB nem session.
 * Pattern irmão de Wave18SaturationTest.php.
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
describe('Wave 23 Crm Saturation', function () {
    it('CrmLeadRepositoryInterface contract existe', function () {
        expect(interface_exists(CrmLeadRepositoryInterface::class))->toBeTrue();

        $reflection = new ReflectionClass(CrmLeadRepositoryInterface::class);
        $methods = collect($reflection->getMethods())->pluck('name')->toArray();

        expect($methods)->toContain('baseQuery', 'findOrFail', 'paginate', 'count');
    });

    it('CrmLeadRepository implementa o contract', function () {
        $repo = new CrmLeadRepository();
        expect($repo)->toBeInstanceOf(CrmLeadRepositoryInterface::class);
    });

    it('container resolve interface => concrete (reuse cross-module)', function () {
        $resolved = app(CrmLeadRepositoryInterface::class);
        expect($resolved)->toBeInstanceOf(CrmLeadRepository::class);
    });

    it('CrmHealthCommand registrado em artisan', function () {
        $all = Artisan::all();
        expect($all)->toHaveKey('crm:health');

        $command = $all['crm:health'];
        expect($command)->toBeInstanceOf(CrmHealthCommand::class);

        // Signature inclui --detail (NÃO --verbose Symfony reserved).
        $definition = $command->getDefinition();
        expect($definition->hasOption('detail'))->toBeTrue();
        expect($definition->hasOption('json'))->toBeTrue();
        expect($definition->hasOption('alert'))->toBeTrue();
    });

    it('IndexLeadRequest rules whitelist seguros (anti-SQLi)', function () {
        $req = new IndexLeadRequest();
        $rules = $req->rules();

        // order_by whitelist (anti-SQLi)
        expect($rules['order_by'][2])->toContain('in:');
        expect($rules['order_dir'][2])->toBe('in:asc,desc');

        // Bounds page/per_page (anti-DoS)
        expect($rules['per_page'])->toContain('min:5', 'max:200');
        expect($rules['page'])->toContain('min:1', 'max:10000');
    });

    it('DeleteProposalRequest tem rules de motivo', function () {
        $req = new DeleteProposalRequest();
        $rules = $req->rules();

        expect($rules)->toHaveKey('motivo');
        expect($rules['motivo'])->toContain('nullable', 'string', 'max:500');

        $messages = $req->messages();
        expect($messages)->toHaveKey('motivo.max');
    });
});
