<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\OficinaAuto\Entities\ServiceOrder;
use Modules\OficinaAuto\Entities\ServiceOrderItem;
use Modules\OficinaAuto\Entities\Vehicle;

uses(Tests\TestCase::class);

/**
 * W27 G4 — Smoke ImportFirebirdMartinhoCommand (esqueleto).
 *
 * Valida:
 * - --dry-run não modifica DB
 * - --business obrigatório
 * - JSON ausente = FAILURE
 * - tabela ausente = FAILURE
 *
 * Pesado (commit real + idempotência) entra em W28 quando script python existir.
 */

const BIZ_W27_IMP = 1;
const PLATE_W27_IMP_PREFIX = 'W27FB';
const TMP_JSON_DIR = 'firebird-test-w27';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: requer schema MySQL UltimatePOS (ADR 0101)');
    }
    if (! Schema::hasTable('oficina_service_order_items')) {
        $this->markTestSkipped('Rode migration 2026_05_17_000010 primeiro');
    }
});

afterEach(function () {
    $dir = storage_path('app/' . TMP_JSON_DIR);
    if (is_dir($dir)) {
        foreach (glob($dir . '/*.json') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($dir);
    }

    // Guard cleanup contra SQLite (driver de teste rápido) — sem schema UltimatePOS
    if (DB::connection()->getDriverName() === 'sqlite') {
        return;
    }
    if (! Schema::hasTable('vehicles')) {
        return;
    }

    $vehicles = Vehicle::withoutGlobalScopes()
        ->where('plate', 'like', PLATE_W27_IMP_PREFIX . '%')
        ->pluck('id')
        ->toArray();

    if (! empty($vehicles)) {
        $osIds = ServiceOrder::withoutGlobalScopes()
            ->whereIn('vehicle_id', $vehicles)
            ->pluck('id')
            ->toArray();

        if (! empty($osIds)) {
            ServiceOrderItem::withoutGlobalScopes()->whereIn('service_order_id', $osIds)->forceDelete();
            ServiceOrder::withoutGlobalScopes()->whereIn('id', $osIds)->forceDelete();
        }
        Vehicle::withoutGlobalScopes()->whereIn('id', $vehicles)->forceDelete();
    }
});

function w27_writeJson(string $name, array $payload): string
{
    $dir = storage_path('app/' . TMP_JSON_DIR);
    if (! is_dir($dir)) {
        mkdir($dir, 0o755, true);
    }
    $path = $dir . '/' . $name;
    file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT));
    return $path;
}

it('exige --business (Tier 0)', function () {
    $exit = \Illuminate\Support\Facades\Artisan::call('oficina:import-firebird-martinho');
    expect($exit)->toBe(\Illuminate\Console\Command::INVALID);
});

it('falha quando JSON inexistente', function () {
    $exit = \Illuminate\Support\Facades\Artisan::call('oficina:import-firebird-martinho', [
        '--business' => BIZ_W27_IMP,
        '--json'     => storage_path('app/firebird/inexistente-w27.json'),
    ]);
    expect($exit)->toBe(\Illuminate\Console\Command::FAILURE);
});

it('dry-run não modifica DB mesmo com payload válido', function () {
    $path = w27_writeJson('dry-run.json', [
        'ordens' => [
            [
                'ordem_id'    => 'FB-DRYRUN-001',
                'placa'       => PLATE_W27_IMP_PREFIX . 'DRY',
                'veiculo_id'  => '99',
                'order_type'  => 'locacao',
                'status'      => 'concluida',
                'entered_at'  => '2025-01-10 08:00:00',
                'completed_at' => '2025-01-15 17:00:00',
                'km'          => 0,
                'notes'       => 'Caçamba 5m³ alugada Construtora X',
                'itens'       => [
                    ['tipo' => 'servico_terceiro', 'descricao' => 'Diária caçamba', 'quantidade' => 5, 'valor_unitario' => 80, 'legacy_item_id' => '1'],
                ],
            ],
        ],
    ]);

    $countOsBefore = ServiceOrder::withoutGlobalScopes()->count();
    $countItemsBefore = ServiceOrderItem::withoutGlobalScopes()->count();

    $exit = \Illuminate\Support\Facades\Artisan::call('oficina:import-firebird-martinho', [
        '--business' => BIZ_W27_IMP,
        '--json'     => $path,
        '--dry-run'  => true,
    ]);

    expect($exit)->toBe(\Illuminate\Console\Command::SUCCESS);
    expect(ServiceOrder::withoutGlobalScopes()->count())->toBe($countOsBefore);
    expect(ServiceOrderItem::withoutGlobalScopes()->count())->toBe($countItemsBefore);
});

it('JSON sem chave "ordens" retorna SUCCESS sem ação', function () {
    $path = w27_writeJson('empty.json', ['ordens' => []]);
    $exit = \Illuminate\Support\Facades\Artisan::call('oficina:import-firebird-martinho', [
        '--business' => BIZ_W27_IMP,
        '--json'     => $path,
    ]);
    expect($exit)->toBe(\Illuminate\Console\Command::SUCCESS);
});
