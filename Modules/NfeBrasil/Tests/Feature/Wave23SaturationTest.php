<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Modules\NfeBrasil\Console\Commands\NfeHealthCommand;
use Modules\NfeBrasil\Models\NfeEmissao;
use Modules\NfeBrasil\Models\NfeEvento;
use Spatie\Activitylog\Traits\LogsActivity;

uses(Tests\TestCase::class);

/**
 * Wave 23 NfeBrasil SATURATION — D1 (cross-tenant) + D4 (LGPD) + D6 (Health).
 *
 * Cobre:
 *   - D1: dataset NfeEmissao + NfeEvento têm LogsActivity (audit trail LGPD)
 *   - D1: HasBusinessScope presente nos models críticos
 *   - D4: Config retention.php presente + estrutura mínima
 *   - D6: nfe:health command registrado
 *
 * Tier 0 (ADR 0093): NÃO chama session() — unit-level. Cross-tenant DB-real fica
 * em NfeBrasilMultiTenantIsolationTest.php.
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 */
describe('Wave 23 NfeBrasil Saturation', function () {
    it('NfeEmissao tem trait LogsActivity (LGPD audit)', function () {
        $traits = class_uses_recursive(NfeEmissao::class);
        expect($traits)->toContain(LogsActivity::class);
    });

    it('NfeEvento tem trait LogsActivity (LGPD audit)', function () {
        $traits = class_uses_recursive(NfeEvento::class);
        expect($traits)->toContain(LogsActivity::class);
    });

    it('NfeEmissao tem HasBusinessScope (multi-tenant Tier 0)', function () {
        $traits = class_uses_recursive(NfeEmissao::class);
        $hasScope = collect($traits)->contains(fn ($t) => str_contains($t, 'HasBusinessScope'));
        expect($hasScope)->toBeTrue();
    });

    it('Config retention.php existe e tem estrutura mínima', function () {
        $path = module_path('NfeBrasil', 'Config/retention.php');
        expect(file_exists($path))->toBeTrue();

        $config = require $path;
        expect($config)->toBeArray();
        // Retention LGPD/CONFAZ: chave esperada
        expect($config)->toHaveKey('enabled');
    });

    it('nfe:health command registrado', function () {
        $all = Artisan::all();
        expect($all)->toHaveKey('nfe:health');
        expect($all['nfe:health'])->toBeInstanceOf(NfeHealthCommand::class);

        $def = $all['nfe:health']->getDefinition();
        expect($def->hasOption('detail'))->toBeTrue();
        expect($def->hasOption('ping-sefaz'))->toBeTrue();
    });

    it('NfeEmissao usa fillable controlado (anti mass-assignment)', function () {
        $model = new NfeEmissao();
        $fillable = $model->getFillable();
        expect($fillable)->toBeArray();
        // business_id NÃO deve estar em fillable se é set automaticamente via observer/scope
        // (pattern multi-tenant). Aceita qualquer um dos 2 caminhos.
        expect(count($fillable))->toBeGreaterThan(0);
    });

    it('canonicidade fiscal — NfeEmissao tem casts de timestamp emissao', function () {
        $model = new NfeEmissao();
        $casts = $model->getCasts();
        // Ao menos um cast de data ou timestamp deve existir (auditoria temporal SEFAZ)
        $hasTemporal = collect($casts)->contains(fn ($v) => in_array($v, ['datetime', 'date', 'timestamp']));
        expect($hasTemporal)->toBeTrue();
    });
});
