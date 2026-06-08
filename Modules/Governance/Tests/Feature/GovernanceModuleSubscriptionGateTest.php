<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

/**
 * Governance: gate subscription canon — `governance_module` configurável via
 * UI Superadmin/Packages.
 *
 * Wagner regra IRREVOGÁVEL 2026-05-18: visibilidade de módulos é via
 * subscription package — NUNCA hardcode `if ($business_id === N) return`.
 *
 * Cobre:
 *   - superadmin_package() declara 'governance_module' permission
 *   - user_permissions() declara 'governance.dashboard.view'
 *   - modifyAdminMenu() consulta hasThePermissionInSubscription (não hardcode)
 *
 * Multi-tenant Tier 0 (ADR 0093) preservado.
 *
 * Refs:
 *   - memory/proibicoes.md §"Multi-tenant Tier 0 IRREVOGÁVEL"
 *   - memory/reference/feedback-habilitar-modulo-por-business.md
 *   - tests/Feature/Sidebar/Biz4RotaLivreSidebarTest.php (anti-regressão geral)
 */

const GOV_DC = __DIR__ . '/../../Http/Controllers/DataController.php';

describe('Governance — subscription package canon', function () {
    it('superadmin_package() declara governance_module permission', function () {
        $src = file_get_contents(GOV_DC);
        expect($src)->toContain("'name'    => 'governance_module'");
        expect($src)->toContain('superadmin_package');
    });

    it('user_permissions() declara governance.dashboard.view', function () {
        $src = file_get_contents(GOV_DC);
        expect($src)->toContain("'value'   => 'governance.dashboard.view'");
        expect($src)->toContain('user_permissions');
    });

    it('modifyAdminMenu() consulta hasThePermissionInSubscription (gate canon)', function () {
        $src = file_get_contents(GOV_DC);
        expect($src)->toContain('hasThePermissionInSubscription');
        expect($src)->toContain("'governance_module'");
        expect($src)->toContain("'superadmin_package'");
        // Superadmin bypass canon
        expect($src)->toContain("isModuleInstalled('Governance')");
    });

    it('modifyAdminMenu() preserva permission user Spatie (gate 2)', function () {
        $src = file_get_contents(GOV_DC);
        expect($src)->toContain("can('governance.dashboard.view')");
    });
});

describe('Governance — anti-regressão hardcode biz=N', function () {
    it('NÃO contém hardcode `$business_id === N` ou similar (Wagner regra Tier 0)', function () {
        $src = file_get_contents(GOV_DC);
        expect($src)->not->toMatch('/business_id\s*[!=]==\s*\d+/');
        expect($src)->not->toContain('$piloto_rotalivre');
        expect($src)->not->toContain('=== 4');
        expect($src)->not->toContain('!== 4');
    });

    it('Cita regra Tier 0 IRREVOGÁVEL no docblock (rastreabilidade)', function () {
        $src = file_get_contents(GOV_DC);
        expect($src)->toContain('IRREVOGÁVEL');
        expect($src)->toContain('2026-05-18');
    });
});
