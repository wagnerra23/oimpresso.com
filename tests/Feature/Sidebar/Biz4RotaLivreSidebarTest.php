<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

/**
 * Anti-regressão hardcode biz=N — Wagner regra 2026-05-18 IRREVOGÁVEL.
 *
 * Visibilidade de módulos POR business é via subscription package (UI
 * Modules/Superadmin/PackagesController) — NUNCA `if ($business_id === N) return`.
 *
 * Este test substitui Biz4RotaLivreSidebarTest original (PRs #1073-#1076) que
 * validava hardcode `=== 4`. Após revert (PR #1077), o test inverte: garante
 * que NÃO existe hardcode biz=4 em nenhum dos 5 arquivos tocados.
 *
 * Validações OK que sobrevivem do trabalho anterior:
 *   - 4 entradas top-level no FINANCEIRO (não dropdown popover) — PR #1075
 *   - Lang keys cashflow/dre/gateway de pagamento — PR #1076
 *   - SIDEBAR_GROUPS items novos + MENU_ICON_MAP entries
 *
 * Refs:
 *   - memory/reference/feedback-habilitar-modulo-por-business.md
 *   - ADR 0093 (multi-tenant Tier 0)
 */

const ROOT = __DIR__ . '/../../..';

const FILES_QUE_NAO_PODEM_HARDCODE_BIZ_4 = [
    '/Modules/Financeiro/Http/Controllers/DataController.php',
    '/Modules/Governance/Http/Controllers/DataController.php',
    '/Modules/Woocommerce/Http/Controllers/DataController.php',
    '/app/Http/Middleware/HandleInertiaRequests.php',
    '/app/Http/Middleware/AdminSidebarMenu.php',
];

describe('Anti-regressão hardcode biz=N — IRREVOGÁVEL Wagner 2026-05-18', function () {
    it('NENHUM arquivo tocado na sessão biz=4 tem hardcode === 4 ou !== 4', function () {
        foreach (FILES_QUE_NAO_PODEM_HARDCODE_BIZ_4 as $rel) {
            $src = file_get_contents(ROOT . $rel);
            // Patterns a banir
            expect($src)->not->toContain('$business_id === 4');
            expect($src)->not->toContain('$businessId === 4');
            expect($src)->not->toContain('$current_biz === 4');
            expect($src)->not->toContain('$business_id !== 4');
            expect($src)->not->toContain('$businessId !== 4');
            expect($src)->not->toContain('$current_biz !== 4');
            expect($src)->not->toContain('$piloto_rotalivre');
            // Mais defensivo: nenhuma variável de business hardcoded == 4 sem espaço
            expect($src)->not->toMatch('/business_id\s*[!=]==\s*4/');
            expect($src)->not->toMatch('/businessId\s*[!=]==\s*4/');
        }
    });

    it('Módulos com gate de subscription preservaram pattern canônico', function () {
        // Financeiro: deve checar permission financeiro.access (não hardcode biz=4)
        $financeiro = file_get_contents(ROOT . '/Modules/Financeiro/Http/Controllers/DataController.php');
        expect($financeiro)->toContain('hasThePermissionInSubscription');
        expect($financeiro)->toContain("'financeiro_module'");

        // Woocommerce: idem
        $woo = file_get_contents(ROOT . '/Modules/Woocommerce/Http/Controllers/DataController.php');
        expect($woo)->toContain('hasThePermissionInSubscription');
        expect($woo)->toContain("'woocommerce_module'");
    });

    it('AdminSidebarMenu usa $enabled_modules (subscription) — não hardcode biz', function () {
        $src = file_get_contents(ROOT . '/app/Http/Middleware/AdminSidebarMenu.php');
        // Pattern canon: in_array('feature', $enabled_modules) + can()
        expect($src)->toContain("in_array('expenses', \$enabled_modules)");
        expect($src)->toContain("in_array('service_staff', \$enabled_modules)");
    });
});

describe('Que sobrevive do trabalho biz=4 anterior — sem hardcode', function () {
    it('Financeiro DataController publica 4 entradas top-level (não dropdown)', function () {
        $src = file_get_contents(ROOT . '/Modules/Financeiro/Http/Controllers/DataController.php');
        // 4 URLs separados em vez de 1 dropdown
        expect($src)->toContain('/financeiro/unificado');
        expect($src)->toContain('/financeiro/fluxo');
        expect($src)->toContain('/financeiro/relatorios');
        expect($src)->toContain('/financeiro/boletos');
        // Orders fracionários 85.0/85.1/85.2/85.3
        expect($src)->toContain('->order(85)');
        expect($src)->toContain('->order(85.1)');
        expect($src)->toContain('->order(85.2)');
        expect($src)->toContain('->order(85.3)');
    });

    it('Lang pt/financeiro tem 3 chaves (cashflow / dre / boletos=Gateway)', function () {
        $src = file_get_contents(ROOT . '/Modules/Financeiro/Resources/lang/pt/financeiro.php');
        expect($src)->toContain("'cashflow_label' => 'Fluxo de Caixa'");
        expect($src)->toContain("'dre_label' => 'DRE / Relatórios'");
        expect($src)->toContain("'boletos_label' => 'Gateway de Pagamento'");
    });

    it('Sidebar.tsx SIDEBAR_GROUPS tem 3 labels novas no grupo fin', function () {
        $src = file_get_contents(ROOT . '/resources/js/Components/cockpit/Sidebar.tsx');
        expect($src)->toContain("'Fluxo de Caixa'");
        expect($src)->toContain("'DRE / Relatórios'");
        expect($src)->toContain("'Gateway de Pagamento'");
        // MENU_ICON_MAP entries
        expect($src)->toContain("'fluxo de caixa': TrendingUp");
        expect($src)->toContain("'gateway de pagamento': CreditCard");
    });
});

describe('Memória canon documenta pattern correto', function () {
    it('feedback-habilitar-modulo-por-business documenta pattern subscription', function () {
        $md = file_get_contents(ROOT . '/memory/reference/feedback-habilitar-modulo-por-business.md');
        expect($md)->toContain('SUBSCRIPTION PACKAGES, NÃO hardcode');
        expect($md)->toContain('habilitar e desabilitar é compra de pacote no modulo superadmin');
        expect($md)->toContain('hasThePermissionInSubscription');
        expect($md)->toContain('Modules/Superadmin/PackagesController');
        expect($md)->toContain('IRREVOGÁVEL Wagner 2026-05-18');
    });
});
