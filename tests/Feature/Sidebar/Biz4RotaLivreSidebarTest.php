<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

/**
 * Sidebar customizado pra biz=4 (ROTA LIVRE Larissa) — testes estruturais.
 *
 * Wagner 2026-05-18: cliente piloto vai usar Financeiro + DRE + Fluxo + Boletos.
 * Remover Tarefas + Governança + Despesas (ela não usa).
 *
 * Cobre:
 *   - Modules/Financeiro/Http/Controllers/DataController.php — guard biz=4 ABERTO
 *     + dropdown com 4 sub-items
 *   - app/Http/Middleware/HandleInertiaRequests.php — shortcuts.tarefas FALSE pra biz=4
 *   - Modules/Governance/Http/Controllers/DataController.php — return early se biz=4
 *   - app/Http/Middleware/AdminSidebarMenu.php — Expense dropdown FALSE pra biz=4
 *   - Modules/Financeiro/Resources/lang/pt/financeiro.php — 3 lang keys novas
 *
 * Tier 0 multi-tenant (ADR 0093) preservado: business_id continua scope global.
 * Apenas customiza VISIBILIDADE do menu por biz, não autorização de dados.
 *
 * Refs: pedido Wagner 2026-05-18 "Libere para empresa 4 o: Financeiro, DRE/
 *       Relatorio, Fluxo de Caixa, Boletos. Remova a Tarefa/Governança/Despesas".
 */

const ROOT = __DIR__ . '/../../..';

describe('biz=4 ROTA LIVRE — LIBERAR Financeiro + sub-items', function () {
    it('Financeiro DataController: guard permite biz=4 (não apenas superadmin)', function () {
        $src = file_get_contents(ROOT . '/Modules/Financeiro/Http/Controllers/DataController.php');
        expect($src)->toContain('$business_id = (int) session(\'user.business_id\')');
        expect($src)->toContain('$piloto_rotalivre = ($business_id === 4)');
        expect($src)->toContain('! $piloto_rotalivre');
        // Comentário Wagner 2026-05-18 deve estar presente (rastreabilidade)
        expect($src)->toContain('Wagner 2026-05-18');
        expect($src)->toContain('ROTA LIVRE');
    });

    it('Financeiro DataController: dropdown com 4 sub-items (Visão / Fluxo / DRE / Boletos)', function () {
        $src = file_get_contents(ROOT . '/Modules/Financeiro/Http/Controllers/DataController.php');
        expect($src)->toContain('$menu->dropdown');
        expect($src)->toContain('/financeiro/unificado');
        expect($src)->toContain('/financeiro/fluxo');
        expect($src)->toContain('/financeiro/relatorios/dre');
        expect($src)->toContain('/financeiro/boletos');
        expect($src)->toContain('cashflow_label');
        expect($src)->toContain('dre_label');
        expect($src)->toContain('boletos_label');
    });

    it('Lang pt/financeiro tem 3 chaves novas (cashflow_label / dre_label / boletos_label como Gateway)', function () {
        $src = file_get_contents(ROOT . '/Modules/Financeiro/Resources/lang/pt/financeiro.php');
        expect($src)->toContain("'cashflow_label' => 'Fluxo de Caixa'");
        expect($src)->toContain("'dre_label' => 'DRE / Relatórios'");
        // Wagner 2026-05-18 fix: "Boletos" renomeado pra "Gateway de Pagamento"
        // (Inter API + PIX + futuras integrações). Lang key 'boletos_label'
        // preservada pra manter URL /financeiro/boletos compatível.
        expect($src)->toContain("'boletos_label' => 'Gateway de Pagamento'");
    });
});

describe('biz=4 ROTA LIVRE — Woocommerce escondido (Wagner 2026-05-18)', function () {
    it('Woocommerce DataController: return early quando business_id === 4', function () {
        $src = file_get_contents(ROOT . '/Modules/Woocommerce/Http/Controllers/DataController.php');
        expect($src)->toContain('$business_id = (int) session()->get(\'user.business_id\')');
        expect($src)->toContain('if ($business_id === 4)');
        expect($src)->toContain('Wagner 2026-05-18');
        expect($src)->toContain('ROTA LIVRE');
    });
});

describe('biz=4 ROTA LIVRE — ESCONDER Tarefas / Governança / Despesas', function () {
    it('HandleInertiaRequests: shortcuts.tarefas FALSE quando businessId === 4', function () {
        $src = file_get_contents(ROOT . '/app/Http/Middleware/HandleInertiaRequests.php');
        // Guard biz=4 explícito
        expect($src)->toContain('$businessId !== 4');
        // Comentário Wagner pra rastreabilidade
        expect($src)->toContain('Wagner 2026-05-18');
        expect($src)->toContain('ROTA LIVRE');
        // Branch catch tb tem guard (back-compat)
        expect($src)->toContain("\$shortcuts['tarefas'] = (\$businessId !== 4)");
    });

    it('Governance DataController: return early quando business_id === 4', function () {
        $src = file_get_contents(ROOT . '/Modules/Governance/Http/Controllers/DataController.php');
        expect($src)->toContain('$business_id = (int) session(\'user.business_id\')');
        expect($src)->toContain('if ($business_id === 4) return');
        expect($src)->toContain('Wagner 2026-05-18');
        expect($src)->toContain('ROTA LIVRE');
    });

    it('AdminSidebarMenu: Expense dropdown skipado quando biz === 4', function () {
        $src = file_get_contents(ROOT . '/app/Http/Middleware/AdminSidebarMenu.php');
        expect($src)->toContain('$current_biz = (int) session(\'user.business_id\')');
        expect($src)->toContain('$current_biz !== 4');
        expect($src)->toContain('Wagner 2026-05-18');
        expect($src)->toContain('ROTA LIVRE');
    });
});

describe('biz=4 ROTA LIVRE — multi-tenant Tier 0 preservado', function () {
    it('mudanças NÃO tocam queries/scopes de dados (apenas visibilidade UI)', function () {
        // Verifica que os 4 arquivos editados NÃO mudaram nenhum query/Eloquent scope.
        // Tudo que muda é VISIBILIDADE do item no sidebar. Acesso via URL direta
        // continua sujeito a permission gates nos controllers.
        $files = [
            ROOT . '/Modules/Financeiro/Http/Controllers/DataController.php',
            ROOT . '/Modules/Governance/Http/Controllers/DataController.php',
            ROOT . '/app/Http/Middleware/HandleInertiaRequests.php',
            ROOT . '/app/Http/Middleware/AdminSidebarMenu.php',
        ];
        foreach ($files as $f) {
            $src = file_get_contents($f);
            // Nenhum dos edits introduz where('business_id', 4) hardcoded
            // (isso seria vazamento Tier 0). Guards são apenas no menu render.
            expect($src)->not->toContain("where('business_id', 4)");
            expect($src)->not->toContain('whereBusinessId(4)');
        }
    });
});
