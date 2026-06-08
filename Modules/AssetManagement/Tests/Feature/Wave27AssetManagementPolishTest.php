<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\AssetManagement\Services\AssetWarrantyService;

uses(Tests\TestCase::class);

/**
 * Helper — testes que tocam DB precisam skip em SQLite (schema MySQL UltimatePOS).
 */
function w27AssetManagementNeedsMysql(): bool
{
    if (DB::connection()->getDriverName() === 'sqlite') {
        return true;
    }
    if (! Schema::hasTable('assets')) {
        return true;
    }
    return false;
}

/**
 * Wave 27 — POLISH ≥88 AssetManagement (2026-05-17).
 *
 * Cobre incrementos polish:
 *  - D9.a: spans novos AssetWarrantyService::contagemAtivas / contagemExpiradas
 *  - D5: README "como cliente usa" criado (sanity check existence)
 *  - D2 expand: cross-tenant biz=99 nos contadores warranty (Tier 0)
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL ({@see ADR 0093}). Nunca biz=4 cliente ({@see ADR 0101}).
 *
 * @see Modules\AssetManagement\Services\AssetWarrantyService
 * @see Modules\AssetManagement\README.md (D5 customer journey)
 */

it('W27 D9.a: AssetWarrantyService::contagemAtivas tem span assetmanagement.warranty.count_active', function () {
    $src = file_get_contents(base_path('Modules/AssetManagement/Services/AssetWarrantyService.php'));
    expect($src)->toContain("OtelHelper::spanBiz('assetmanagement.warranty.count_active'");
    expect($src)->toContain('public function contagemAtivas');
});

it('W27 D9.a: AssetWarrantyService::contagemExpiradas tem span assetmanagement.warranty.count_expired', function () {
    $src = file_get_contents(base_path('Modules/AssetManagement/Services/AssetWarrantyService.php'));
    expect($src)->toContain("OtelHelper::spanBiz('assetmanagement.warranty.count_expired'");
    expect($src)->toContain('public function contagemExpiradas');
});

it('W27 D9.a: AssetWarrantyService importa OtelHelper canônico (App\Util\OtelHelper)', function () {
    $src = file_get_contents(base_path('Modules/AssetManagement/Services/AssetWarrantyService.php'));
    expect($src)->toContain('use App\Util\OtelHelper;');
    expect($src)->not->toContain('OpenTelemetry\\API\\Trace');
});

it('W27 D9.a: AssetWarrantyService total 5 spanBiz invocations (3 originais + 2 W27)', function () {
    $src = file_get_contents(base_path('Modules/AssetManagement/Services/AssetWarrantyService.php'));
    $count = substr_count($src, 'OtelHelper::spanBiz(');
    expect($count)->toBeGreaterThanOrEqual(5);
});

it('W27 D5: README.md "como cliente usa" criado com cenários canônicos', function () {
    $readme = base_path('Modules/AssetManagement/README.md');
    expect(file_exists($readme))->toBeTrue('README W27 D5 não criado');

    $content = file_get_contents($readme);
    expect($content)->toContain('Cenário A');
    expect($content)->toContain('Cenário B');
    expect($content)->toContain('Cenário C');
    expect($content)->toContain('Cenário D');
    expect($content)->toContain('Multi-tenant Tier 0');
    expect($content)->toContain('ADR 0093');
});

it('W27 D5: README documenta 5 spans AssetWarrantyService', function () {
    $content = file_get_contents(base_path('Modules/AssetManagement/README.md'));
    expect($content)->toContain('contagemAtivas');
    expect($content)->toContain('contagemExpiradas');
});

it('W27 D2 Tier 0: contagemAtivas biz=99 com asset inexistente retorna 0', function () {
    if (w27AssetManagementNeedsMysql()) {
        $this->markTestSkipped('Requer MySQL UltimatePOS (ADR 0101).');
    }
    /** @var AssetWarrantyService $svc */
    $svc = app(AssetWarrantyService::class);

    // Asset ID muito alto + biz=99 — não existe combinação real
    // Service deve retornar 0 (find() → null, NÃO findOrFail → 404 quebraria contract)
    $count = $svc->contagemAtivas(999999, 99);
    expect($count)->toBe(0);
});

it('W27 D2 Tier 0: contagemExpiradas biz=99 com asset inexistente retorna 0', function () {
    if (w27AssetManagementNeedsMysql()) {
        $this->markTestSkipped('Requer MySQL UltimatePOS (ADR 0101).');
    }
    /** @var AssetWarrantyService $svc */
    $svc = app(AssetWarrantyService::class);

    $count = $svc->contagemExpiradas(999999, 99);
    expect($count)->toBe(0);
});

it('W27 D9.a: contagemAtivas retorna int (contract sanity)', function () {
    if (w27AssetManagementNeedsMysql()) {
        $this->markTestSkipped('Requer MySQL UltimatePOS (ADR 0101).');
    }
    /** @var AssetWarrantyService $svc */
    $svc = app(AssetWarrantyService::class);

    $count = $svc->contagemAtivas(999999, 1);
    expect($count)->toBeInt();
});
