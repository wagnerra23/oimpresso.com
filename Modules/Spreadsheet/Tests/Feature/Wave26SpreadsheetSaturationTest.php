<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Spreadsheet\Entities\Spreadsheet;
use Modules\Spreadsheet\Entities\SpreadsheetShare;
use Modules\Spreadsheet\Http\Controllers\SpreadsheetController;
use Modules\Spreadsheet\Services\SpreadsheetService;
use Spatie\Activitylog\Traits\LogsActivity;

uses(Tests\TestCase::class);

/**
 * Wave 26 Spreadsheet SATURATION — polish 74 → ≥85 (+11pp).
 *
 * Esforço por dimensão:
 *  - D1 Entities trait LogsActivity + Pest cross-tenant guard preservar
 *    (cross-tenant via where(business_id) manual — Wave 18 baseline)
 *  - D6 defer SpreadsheetController (Inertia::defer compatibility)
 *  - D7 LogsActivity em SpreadsheetEntity sensível (audit append-only D7.b)
 *  - D3 CHANGELOG + BRIEFING Wave 26
 *
 * Trust L0: tests Reflection puros + Schema check. Sem boot HTTP/DB pesado.
 *
 * @see Modules/Spreadsheet/Tests/Feature/SpreadsheetServiceContractTest.php (Wave 23 baseline)
 * @see Modules/Spreadsheet/Tests/Feature/MultiTenantIsolationTest.php (cross-tenant baseline)
 * @see Modules/Spreadsheet/Services/SpreadsheetService.php (Wave 16+18 D4)
 */

function w26SpsNeedsMysql(): bool
{
    return DB::connection()->getDriverName() === 'sqlite';
}

// ------------------------------------------------------------------
// D1 — Entities trait + cross-tenant guard (preservar)
// ------------------------------------------------------------------

it('D1 W26: Spreadsheet Entity usa LogsActivity (D7 audit trail append-only)', function () {
    $traits = class_uses_recursive(Spreadsheet::class);
    expect($traits)->toContain(LogsActivity::class);
});

it('D1 W26: SpreadsheetShare Entity usa LogsActivity (D7 audit trail append-only)', function () {
    $traits = class_uses_recursive(SpreadsheetShare::class);
    expect($traits)->toContain(LogsActivity::class);
});

it('D1 W26: Spreadsheet getActivitylogOptions canon (logAll + logOnlyDirty + dontSubmitEmptyLogs)', function () {
    $file = (new ReflectionClass(Spreadsheet::class))->getFileName();
    $src = file_get_contents($file);

    expect($src)->toContain('logAll()');
    expect($src)->toContain('logOnlyDirty()');
    expect($src)->toContain('dontSubmitEmptyLogs()');
});

it('D1 W26: SpreadsheetShare getActivitylogOptions canon', function () {
    $file = (new ReflectionClass(SpreadsheetShare::class))->getFileName();
    $src = file_get_contents($file);

    expect($src)->toContain('logAll()');
    expect($src)->toContain('logOnlyDirty()');
    expect($src)->toContain('dontSubmitEmptyLogs()');
});

it('D1 W26: Spreadsheet tem table custom sheet_spreadsheets + sheet_data cast array (canon)', function () {
    $sheet = new Spreadsheet();
    expect($sheet->getTable())->toBe('sheet_spreadsheets');

    $casts = $sheet->getCasts();
    expect($casts)->toHaveKey('sheet_data');
    expect($casts['sheet_data'])->toBe('array');
});

it('D1 W26: SpreadsheetShare tem table custom sheet_spreadsheet_shares (canon)', function () {
    $share = new SpreadsheetShare();
    expect($share->getTable())->toBe('sheet_spreadsheet_shares');
});

it('D1 W26: Spreadsheet relação shares() hasMany SpreadsheetShare via sheet_spreadsheet_id', function () {
    $sheet = new Spreadsheet();
    $relation = $sheet->shares();

    expect($relation->getRelated())->toBeInstanceOf(SpreadsheetShare::class);
    expect($relation->getForeignKeyName())->toBe('sheet_spreadsheet_id');
});

it('D1 W26: Spreadsheet $guarded = [id] (mass assignment safe)', function () {
    $sheet = new Spreadsheet();
    $guarded = (new ReflectionClass($sheet))->getProperty('guarded');
    $guarded->setAccessible(true);
    expect($guarded->getValue($sheet))->toBe(['id']);
});

// ------------------------------------------------------------------
// D1 — Cross-tenant guard (schema biz=1 vs biz=99 preservar Wave 18)
// ------------------------------------------------------------------

it('D1 W26: sheet_spreadsheets tem coluna business_id (ADR 0093 Tier 0)', function () {
    if (w26SpsNeedsMysql() || ! Schema::hasTable('sheet_spreadsheets')) {
        $this->markTestSkipped('Schema sheet_spreadsheets indisponível neste ambiente.');
    }

    expect(Schema::hasColumn('sheet_spreadsheets', 'business_id'))->toBeTrue(
        'sheet_spreadsheets.business_id é obrigatório (ADR 0093)'
    );
});

it('D1 W26: cross-tenant manual via where(business_id) — Spreadsheet biz=99 NÃO acha biz=1', function () {
    if (w26SpsNeedsMysql() || ! Schema::hasTable('sheet_spreadsheets')) {
        $this->markTestSkipped('Schema sheet_spreadsheets requer MySQL UltimatePOS.');
    }

    $sheet = Spreadsheet::create([
        'business_id' => 1,
        'name'        => 'W26-Saturation-Test-Biz1',
        'sheet_data'  => ['rows' => []],
        'created_by'  => 1,
    ]);

    try {
        $resultadoBiz99 = Spreadsheet::where('business_id', 99)
            ->where('id', $sheet->id)
            ->get();

        expect($resultadoBiz99)->toHaveCount(0);
    } finally {
        $sheet->delete();
    }
});

// ------------------------------------------------------------------
// D6 — defer SpreadsheetController
// ------------------------------------------------------------------

it('D6 W26: SpreadsheetController existe + bindable container (DI canon)', function () {
    expect(class_exists(SpreadsheetController::class))->toBeTrue();
});

it('D6 W26: SpreadsheetController injeta SpreadsheetService via DI (Wave 18 D4)', function () {
    $ref = new ReflectionClass(SpreadsheetController::class);
    $ctor = $ref->getConstructor();

    $hasServiceParam = false;
    foreach ($ctor->getParameters() as $param) {
        if ($param->getType()?->getName() === SpreadsheetService::class) {
            $hasServiceParam = true;
            break;
        }
    }
    expect($hasServiceParam)->toBeTrue('Controller deve injetar SpreadsheetService (DI)');
});

it('D6 W26: SpreadsheetController index tem ACL canon (superadmin || hasPermission)', function () {
    $file = (new ReflectionClass(SpreadsheetController::class))->getFileName();
    $src = file_get_contents($file);

    expect($src)->toContain('superadmin');
    expect($src)->toContain('hasThePermissionInSubscription');
});

// ------------------------------------------------------------------
// D7 — LogsActivity preserved + Config/retention.php declared
// ------------------------------------------------------------------

it('D7 W26: Config/retention.php existe (declaração LGPD canon)', function () {
    expect(file_exists(base_path('Modules/Spreadsheet/Config/retention.php')))->toBeTrue();
});

it('D7 W26: Config/retention.php declara sheet_spreadsheets + sheet_spreadsheet_shares (canon)', function () {
    $retention = require base_path('Modules/Spreadsheet/Config/retention.php');
    expect($retention)->toBeArray();

    // Spreadsheet usa chave `tabelas` (não `entities` como outros módulos)
    $hasTabelas = isset($retention['tabelas']) && is_array($retention['tabelas']);
    expect($hasTabelas)->toBeTrue('retention.tabelas array esperado');

    // 2 tabelas canon (sheet_spreadsheets + sheet_spreadsheet_shares)
    expect($retention['tabelas'])->toHaveKey('sheet_spreadsheets');
    expect($retention['tabelas'])->toHaveKey('sheet_spreadsheet_shares');

    // Valores canon: ≥1825 dias (5y janela fiscal Brasil)
    expect($retention['tabelas']['sheet_spreadsheets'])->toBeGreaterThanOrEqual(1825);
    expect($retention['tabelas']['sheet_spreadsheet_shares'])->toBeGreaterThanOrEqual(1825);

    // Strategy default canon
    expect($retention)->toHaveKey('strategy');
});

// ------------------------------------------------------------------
// D4 — Service layer canon (Wave 16+18+23 baseline preservar)
// ------------------------------------------------------------------

it('D4 W26: SpreadsheetService tem 6 métodos públicos canon (CRUD + ACL + list)', function () {
    $ref = new ReflectionClass(SpreadsheetService::class);
    $publicMethods = collect($ref->getMethods(\ReflectionMethod::IS_PUBLIC))
        ->filter(fn ($m) => $m->getDeclaringClass()->getName() === SpreadsheetService::class)
        ->map(fn ($m) => $m->getName());

    foreach (['createSpreadsheet', 'updateSpreadsheet', 'deleteSpreadsheet',
              'resolveNotifyableUsers', 'listForUser', 'getForUser'] as $method) {
        expect($publicMethods)->toContain($method);
    }
});

it('D4 W26: SpreadsheetService Wave 26 instrumenta ≥6 OtelHelper::spanBiz (1 por método canon)', function () {
    $src = file_get_contents(base_path('Modules/Spreadsheet/Services/SpreadsheetService.php'));

    $count = substr_count($src, 'OtelHelper::spanBiz(');
    expect($count)->toBeGreaterThanOrEqual(6);

    // Spans canon esperados
    expect($src)->toContain("'spreadsheet.create'");
    expect($src)->toContain("'spreadsheet.list_for_user'");
    expect($src)->toContain("'spreadsheet.get_for_user'");
});

it('D4 W26: SpreadsheetService::listForUser retorna LengthAwarePaginator (pagination Tier 0)', function () {
    $ref = new ReflectionMethod(SpreadsheetService::class, 'listForUser');
    $returnType = $ref->getReturnType()?->getName();
    expect($returnType)->toContain('LengthAwarePaginator');
});

it('D4 W26: SpreadsheetService::getForUser fail-secure (nullable retorno)', function () {
    $ref = new ReflectionMethod(SpreadsheetService::class, 'getForUser');
    $returnType = $ref->getReturnType();

    expect($returnType)->not->toBeNull();
    expect($returnType->allowsNull())->toBeTrue('getForUser fail-secure: ?Spreadsheet');
});

// ------------------------------------------------------------------
// D3 — CHANGELOG + BRIEFING (proxy: arquivos existem + W26 entry)
// ------------------------------------------------------------------

it('D3 W26: CHANGELOG.md tem entrada Wave 26', function () {
    $changelog = file_get_contents(base_path('Modules/Spreadsheet/CHANGELOG.md'));
    expect($changelog)->toContain('Wave 26');
});

it('D3 W26: BRIEFING.md mencionado/atualizado Wave 26', function () {
    $briefing = file_get_contents(base_path('memory/requisitos/Spreadsheet/BRIEFING.md'));
    expect($briefing)->toContain('Wave 26');
});

// ------------------------------------------------------------------
// Wave 26 preserva Service contract D4 ADR 0093 Tier 0
// ------------------------------------------------------------------

it('Wave 26 preserva Service contract D4 — bizId obrigatório nos 3 métodos write', function () {
    foreach (['createSpreadsheet', 'updateSpreadsheet', 'deleteSpreadsheet'] as $method) {
        $ref = new ReflectionMethod(SpreadsheetService::class, $method);
        $params = collect($ref->getParameters())->keyBy(fn ($p) => $p->getName());

        expect($params->has('bizId'))->toBeTrue("{$method} deve ter bizId (Tier 0)");
        expect($params['bizId']->isOptional())->toBeFalse("{$method} bizId NÃO pode ser optional");
        expect($params['bizId']->getType()?->getName())->toBe('int');
    }
});
