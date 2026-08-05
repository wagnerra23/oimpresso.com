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

/**
 * Entry de sidebar REATIVADA em 2026-08-05 (decisão [W]).
 *
 * POR QUE ESTES DOIS TESTES EXISTEM — os 4 testes de string acima passaram
 * VERDES de 2026-05-25 a 2026-08-05 enquanto um `return;` incondicional no topo
 * de `modifyAdminMenu()` tornava os dois gates INALCANÇÁVEIS. Eles afirmavam
 * "o gate é consultado" medindo a PRESENÇA da string no fonte, não a execução.
 * É a classe LC-11 (presence-gate) de `memory/LICOES_CODE.md`.
 *
 * O primeiro teste abaixo é o que teria pegado o defeito; o segundo exerce o
 * caminho real (chama o método e lê o menu publicado) em vez de ler o arquivo.
 */
describe('Governance — entry de sidebar viva (não dead code)', function () {
    it('modifyAdminMenu NÃO começa com return incondicional (anti-regressão do dead code)', function () {
        $src = file_get_contents(GOV_DC);

        // Casa APENAS quando o 1º statement executável do método é `return;`
        // (comentários no meio são tolerados) — exatamente a forma que desligou
        // a entry por ~2 meses sem nenhum teste avermelhar.
        expect($src)->not->toMatch(
            '/public function modifyAdminMenu\(\)[^{]*\{(\s*\/\/[^\n]*\n)*\s*return\s*;/'
        );
    });

    it('guest não publica entry — o gate de auth EXECUTA (controle negativo)', function () {
        \Menu::create('admin-sidebar-menu', fn ($menu) => $menu);

        // Sem usuário autenticado: o `if (!auth()->check()) return;` deve cortar
        // antes de qualquer Menu::modify. Se alguém reintroduzir um return
        // incondicional este teste segue verde — por isso ele vem PAREADO com o
        // de cima, que é quem mede a forma.
        (new \Modules\Governance\Http\Controllers\DataController())->modifyAdminMenu();

        $built = (new \App\Services\LegacyMenuAdapter())->build();

        expect(array_column($built, 'label'))->not->toContain('Governança');
    });
});
