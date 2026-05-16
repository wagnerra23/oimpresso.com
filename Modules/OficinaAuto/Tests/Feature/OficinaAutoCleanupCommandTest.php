<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Modules\OficinaAuto\Entities\ServiceOrder;
use Modules\OficinaAuto\Entities\Vehicle;

uses(Tests\TestCase::class);

/**
 * Cleanup commands pós-migração cliente legacy OficinaAuto (CYCLE-06 — PR #555 follow-up).
 *
 * 4 cenários:
 *  1. --dry-run não modifica DB
 *  2. biz argumento obrigatório
 *  3. sanity-check detecta FK orphan
 *  4. migration-report gera arquivo em storage/reports/
 *
 * Tests biz=1 (nunca biz=4 cliente real — ADR 0101).
 *
 * @see Modules/OficinaAuto/Console/Commands/OficinaAutoCleanupMigratedClientCommand.php
 * @see Modules/OficinaAuto/Console/Commands/OficinaAutoSanityCheckCommand.php
 * @see Modules/OficinaAuto/Console/Commands/OficinaAutoMigrationReportCommand.php
 */

const BIZ_WAGNER_CLEANUP = 1;

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: requer schema MySQL UltimatePOS (ADR 0101)');
    }
    if (! Schema::hasTable('service_orders') || ! Schema::hasTable('vehicles')) {
        $this->markTestSkipped('service_orders/vehicles tables missing — rode OficinaAuto migrate primeiro');
    }
});

it('cenário 1: --dry-run não modifica DB', function () {
    session(['user.business_id' => BIZ_WAGNER_CLEANUP]);

    // Criar vehicle fixture pra detectar
    $vehicle = Vehicle::withoutGlobalScopes()->create([ // SUPERADMIN: setup teste
        'business_id'  => BIZ_WAGNER_CLEANUP,
        'plate'        => 'FIXTURECLN1',
        'vehicle_type' => 'automovel',
    ]);

    $countBefore = Vehicle::withoutGlobalScopes()
        ->where('business_id', BIZ_WAGNER_CLEANUP)
        ->where('plate', 'FIXTURECLN1')
        ->count();

    expect($countBefore)->toBe(1);

    // Dry-run default ON
    $exitCode = Artisan::call('oficina:cleanup-migrated', [
        'biz' => BIZ_WAGNER_CLEANUP,
    ]);

    expect($exitCode)->toBe(0);

    $countAfter = Vehicle::withoutGlobalScopes()
        ->where('business_id', BIZ_WAGNER_CLEANUP)
        ->where('plate', 'FIXTURECLN1')
        ->count();

    // Dry-run NÃO removeu
    expect($countAfter)->toBe(1);
})->afterEach(function () {
    Vehicle::withoutGlobalScopes()->where('plate', 'FIXTURECLN1')->forceDelete();
});

it('cenário 2: biz argument obrigatório', function () {
    // sem biz → Artisan retorna code != 0 (Symfony validation lança RuntimeException)
    try {
        $exitCode = Artisan::call('oficina:cleanup-migrated');
        expect($exitCode)->not->toBe(0);
    } catch (\Throwable $e) {
        // Symfony Console: "Not enough arguments (missing: 'biz')" — esperado
        expect($e->getMessage())->toContain('biz');
    }
});

it('cenário 3: sanity-check detecta FK orphan', function () {
    session(['user.business_id' => BIZ_WAGNER_CLEANUP]);

    // Criar vehicle real
    $vehicle = Vehicle::withoutGlobalScopes()->create([ // SUPERADMIN
        'business_id'  => BIZ_WAGNER_CLEANUP,
        'plate'        => 'ORPH001',
        'vehicle_type' => 'automovel',
    ]);

    // Criar OS apontando pro vehicle, depois force-delete vehicle pra criar orphan
    $os = ServiceOrder::withoutGlobalScopes()->create([ // SUPERADMIN
        'business_id' => BIZ_WAGNER_CLEANUP,
        'vehicle_id'  => $vehicle->id,
        'status'      => 'aberta',
    ]);

    // Force delete vehicle (FK CASCADE pode pegar — então deletamos só por DB raw bypass)
    $orphanedVehicleId = $vehicle->id;
    $os->vehicle_id = 99999999; // aponta pra vehicle inexistente
    ServiceOrder::withoutGlobalScopes()->where('id', $os->id)->update(['vehicle_id' => 99999999]);

    // Roda sanity-check
    $exitCode = Artisan::call('oficina:sanity-check', [
        'biz' => BIZ_WAGNER_CLEANUP,
    ]);

    $output = Artisan::output();

    // Esperado: detectou orphan e retornou FAILURE
    expect($exitCode)->toBe(1);
    expect($output)->toContain('FK orphans');
})->afterEach(function () {
    ServiceOrder::withoutGlobalScopes()
        ->where('business_id', BIZ_WAGNER_CLEANUP)
        ->where('vehicle_id', 99999999)
        ->forceDelete();
    Vehicle::withoutGlobalScopes()->where('plate', 'ORPH001')->forceDelete();
});

it('cenário 4: migration-report gera arquivo', function () {
    session(['user.business_id' => BIZ_WAGNER_CLEANUP]);

    $reportsDirBefore = File::isDirectory(storage_path('reports'))
        ? File::files(storage_path('reports'))
        : [];
    $countBefore = count($reportsDirBefore);

    $exitCode = Artisan::call('oficina:migration-report', [
        'biz' => BIZ_WAGNER_CLEANUP,
    ]);

    expect($exitCode)->toBe(0);

    $reportsDirAfter = File::files(storage_path('reports'));
    $countAfter = count($reportsDirAfter);

    // Pelo menos 1 arquivo novo
    expect($countAfter)->toBeGreaterThan($countBefore);

    // Verifica nome do arquivo (oficina-migration-1-*.md)
    $matchingFiles = collect($reportsDirAfter)
        ->filter(fn ($f) => str_starts_with($f->getFilename(), 'oficina-migration-1-')
            && str_ends_with($f->getFilename(), '.md'));

    expect($matchingFiles)->not->toBeEmpty();

    // Cleanup: remove arquivos de teste
    $matchingFiles->each(fn ($f) => File::delete($f->getPathname()));
});
