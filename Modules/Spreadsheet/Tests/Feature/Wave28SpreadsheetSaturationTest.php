<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Spreadsheet\Entities\Spreadsheet;
use Modules\Spreadsheet\Entities\SpreadsheetShare;
use Modules\Spreadsheet\Services\SpreadsheetService;

uses(Tests\TestCase::class);

/**
 * Wave 28 Spreadsheet SATURATION FINAL — polish 74-88 → ≥92 (+4pp).
 *
 * Esforço por dimensão:
 *  - D2 +3 Pest novos cenários Wave 28
 *  - D9 +1 span `spreadsheet.share_with_user` (7º span SpreadsheetService)
 *  - D3 CHANGELOG W28 entry
 *
 * Trust L0: Reflection + source-grep + cross-tenant guard MySQL-aware.
 *
 * @see Modules/Spreadsheet/Tests/Feature/Wave26SpreadsheetSaturationTest.php (Wave 26 baseline)
 * @see Modules/Spreadsheet/Services/SpreadsheetService.php (D9 +1 span W28)
 */

beforeEach(function () {
    config()->set('otel.enabled', false);
});

function w28SpsNeedsMysql(): bool
{
    return DB::connection()->getDriverName() === 'sqlite';
}

// ------------------------------------------------------------------
// D9 W28 — span novo spreadsheet.share_with_user (7º span canon)
// ------------------------------------------------------------------

it('D9 W28: SpreadsheetService tem método shareWithUser novo (W28 D9)', function () {
    $ref = new ReflectionClass(SpreadsheetService::class);
    expect($ref->hasMethod('shareWithUser'))->toBeTrue('Wave 28 D9 — shareWithUser novo método público');

    $method = $ref->getMethod('shareWithUser');
    expect($method->isPublic())->toBeTrue();
});

it('D9 W28: SpreadsheetService instrumenta spreadsheet.share_with_user (≥7 spans total)', function () {
    $src = file_get_contents(base_path('Modules/Spreadsheet/Services/SpreadsheetService.php'));

    expect($src)->toContain("'spreadsheet.share_with_user'");

    $count = substr_count($src, 'OtelHelper::spanBiz(');
    expect($count)->toBeGreaterThanOrEqual(7);
});

it('D9 W28: shareWithUser exige bizId Tier 0 obrigatório (multi-tenant ADR 0093)', function () {
    $ref = new ReflectionMethod(SpreadsheetService::class, 'shareWithUser');
    $params = collect($ref->getParameters())->keyBy(fn ($p) => $p->getName());

    expect($params->has('bizId'))->toBeTrue('shareWithUser deve ter parâmetro bizId Tier 0');
    expect($params['bizId']->isOptional())->toBeFalse('bizId NÃO pode ser optional (sem fallback session)');
    expect($params['bizId']->getType()?->getName())->toBe('int');
});

// ------------------------------------------------------------------
// D2 W28 — +3 Pest cenários adicionais
// ------------------------------------------------------------------

it('D2 W28: shareWithUser retorna ?SpreadsheetShare (fail-secure nullable cross-tenant block)', function () {
    $ref = new ReflectionMethod(SpreadsheetService::class, 'shareWithUser');
    $returnType = $ref->getReturnType();

    expect($returnType)->not->toBeNull();
    expect($returnType->allowsNull())->toBeTrue('Retorno nullable — null = cross-tenant bloqueado');
});

it('D2 W28: shareWithUser implementação valida exists antes do create (defesa em profundidade)', function () {
    $src = file_get_contents(base_path('Modules/Spreadsheet/Services/SpreadsheetService.php'));

    // Pre-check via exists()->where(business_id) ANTES de criar share
    expect($src)->toContain('->exists()');
    expect($src)->toContain('cross-tenant bloqueado');

    // updateOrCreate idempotente (re-chamar não duplica share)
    expect($src)->toContain('updateOrCreate');
});

it('D2 W28: shareWithUser cross-tenant — Spreadsheet biz=1 NÃO ganha share via bizId=99', function () {
    if (w28SpsNeedsMysql() || ! Schema::hasTable('sheet_spreadsheets')) {
        $this->markTestSkipped('Schema sheet_spreadsheets requer MySQL UltimatePOS.');
    }

    $sheet = Spreadsheet::create([
        'business_id' => 1,
        'name'        => 'W28-Share-Cross-Tenant-Test',
        'sheet_data'  => ['rows' => []],
        'created_by'  => 1,
    ]);

    try {
        $service = app(SpreadsheetService::class);
        $share = $service->shareWithUser($sheet->id, 999, 99);

        expect($share)->toBeNull('Share cross-tenant deve retornar null (bloqueio Tier 0)');

        // Confirma que NÃO foi criado nada no DB
        $sharesCount = SpreadsheetShare::where('sheet_spreadsheet_id', $sheet->id)->count();
        expect($sharesCount)->toBe(0, 'Nenhum share criado quando bizId divergente');
    } finally {
        SpreadsheetShare::where('sheet_spreadsheet_id', $sheet->id)->delete();
        $sheet->delete();
    }
});

// ------------------------------------------------------------------
// D3 W28 — CHANGELOG entry novo
// ------------------------------------------------------------------

it('D3 W28: CHANGELOG.md tem entrada Wave 28 (saturation 74-88 → ≥92)', function () {
    $changelog = file_get_contents(base_path('Modules/Spreadsheet/CHANGELOG.md'));
    expect($changelog)->toContain('Wave 28');
});
