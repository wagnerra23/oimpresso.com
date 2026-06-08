<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Spreadsheet\Entities\Spreadsheet;
use Modules\Spreadsheet\Entities\SpreadsheetShare;

uses(Tests\TestCase::class);

/**
 * Testa isolamento multi-tenant das Entities do Modules/Spreadsheet.
 *
 * Observação: Spreadsheet/SpreadsheetShare NÃO tem BusinessScope global — o
 * isolamento é feito manualmente via where('business_id', ...) no Controller
 * (ver SpreadsheetController::index linha ~56). Estes testes garantem que:
 *   1. A coluna business_id existe e segrega o dado (Spreadsheet).
 *   2. SpreadsheetShare herda o tenant via FK sheet_spreadsheet_id (cascade).
 *
 * ADR 0093: multi-tenant isolation Tier 0 IRREVOGÁVEL.
 * ADR 0101: usar biz=1 (Wagner WR2), NUNCA biz=4 (ROTA LIVRE — cliente).
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 */

// Guard SQLite: schema usa FK ON DELETE CASCADE pra tabela `business` do UltimatePOS.
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema requer tabela `business` UltimatePOS (FK cascade).');
    }
    if (! Schema::hasTable('sheet_spreadsheets')) {
        $this->markTestSkipped('Tabela sheet_spreadsheets ausente — rode migrate do Modules/Spreadsheet primeiro.');
    }
    if (! Schema::hasTable('sheet_spreadsheet_shares')) {
        $this->markTestSkipped('Tabela sheet_spreadsheet_shares ausente — rode migrate do Modules/Spreadsheet primeiro.');
    }
});

// IDs usados nos testes — biz=1 (Wagner) e biz=99 (fictício isolamento)
const BIZ_WAGNER_SPS = 1;
const BIZ_FICTICIO_SPS = 99;

// ------------------------------------------------------------------
// Spreadsheet — isolamento via where('business_id', ...) manual
// ------------------------------------------------------------------

it('Spreadsheet biz=1 não aparece em query filtrada por biz=99', function () {
    $sheet = Spreadsheet::create([
        'business_id' => BIZ_WAGNER_SPS,
        'name'        => 'Planilha Teste Wagner Isolamento',
        'sheet_data'  => ['rows' => [['A1' => 'teste']]],
        'created_by'  => 1,
    ]);

    // Mesma query que o Controller faz: where('business_id', $biz)
    $resultado = Spreadsheet::where('business_id', BIZ_FICTICIO_SPS)
        ->where('id', $sheet->id)
        ->get();

    expect($resultado)->toHaveCount(0);
})->afterEach(function () {
    Spreadsheet::where('name', 'Planilha Teste Wagner Isolamento')->delete();
});

it('Spreadsheet biz=1 aparece em query filtrada por biz=1', function () {
    $sheet = Spreadsheet::create([
        'business_id' => BIZ_WAGNER_SPS,
        'name'        => 'Planilha Teste Wagner Visivel',
        'sheet_data'  => ['rows' => [['A1' => 'visivel']]],
        'created_by'  => 1,
    ]);

    $resultado = Spreadsheet::where('business_id', BIZ_WAGNER_SPS)
        ->where('id', $sheet->id)
        ->get();

    expect($resultado)->toHaveCount(1);
    expect($resultado->first()->name)->toBe('Planilha Teste Wagner Visivel');
})->afterEach(function () {
    Spreadsheet::where('name', 'Planilha Teste Wagner Visivel')->delete();
});

it('Spreadsheet tem coluna business_id na tabela', function () {
    expect(Schema::hasColumn('sheet_spreadsheets', 'business_id'))->toBeTrue(
        'sheet_spreadsheets.business_id obrigatório (ADR 0093 — multi-tenant Tier 0)'
    );
});

// ------------------------------------------------------------------
// SpreadsheetShare — isolamento via FK sheet_spreadsheet_id (filho)
// ------------------------------------------------------------------

it('SpreadsheetShare herda tenant via FK sheet_spreadsheet_id', function () {
    // Spreadsheet pai do biz=1
    $sheet = Spreadsheet::create([
        'business_id' => BIZ_WAGNER_SPS,
        'name'        => 'Planilha Pai Share Teste',
        'sheet_data'  => [],
        'created_by'  => 1,
    ]);

    $share = SpreadsheetShare::create([
        'sheet_spreadsheet_id' => $sheet->id,
        'shared_with'          => 'user',
        'shared_id'            => 1,
    ]);

    // Filtrar shares cujo pai pertence ao biz=99 — não aparece
    $resultadoBiz99 = SpreadsheetShare::whereHas('sheet_spreadsheet_id', function () {
    })->whereIn('sheet_spreadsheet_id', function ($q) {
        $q->select('id')->from('sheet_spreadsheets')->where('business_id', BIZ_FICTICIO_SPS);
    })->where('id', $share->id)->get();

    expect($resultadoBiz99)->toHaveCount(0);

    // Filtrar shares cujo pai pertence ao biz=1 — aparece
    $resultadoBiz1 = SpreadsheetShare::whereIn('sheet_spreadsheet_id', function ($q) {
        $q->select('id')->from('sheet_spreadsheets')->where('business_id', BIZ_WAGNER_SPS);
    })->where('id', $share->id)->get();

    expect($resultadoBiz1)->toHaveCount(1);
})->afterEach(function () {
    $ids = Spreadsheet::where('name', 'Planilha Pai Share Teste')->pluck('id');
    SpreadsheetShare::whereIn('sheet_spreadsheet_id', $ids)->delete();
    Spreadsheet::whereIn('id', $ids)->delete();
});

it('SpreadsheetShare cascade delete quando spreadsheet pai é apagado', function () {
    $sheet = Spreadsheet::create([
        'business_id' => BIZ_WAGNER_SPS,
        'name'        => 'Planilha Cascade Teste',
        'sheet_data'  => [],
        'created_by'  => 1,
    ]);

    $share = SpreadsheetShare::create([
        'sheet_spreadsheet_id' => $sheet->id,
        'shared_with'          => 'user',
        'shared_id'            => 1,
    ]);

    $shareId = $share->id;
    $sheet->delete();

    // FK ON DELETE CASCADE remove o share automaticamente
    expect(SpreadsheetShare::find($shareId))->toBeNull(
        'SpreadsheetShare deveria ser removido em cascade quando pai é apagado'
    );
});
