<?php

declare(strict_types=1);

/**
 * Pest — US-SELL-COWORK · backend payload extras (sla_kind, days_to_due, pipeline,
 * seller, items_summary, installments).
 *
 * Cobertura estrutural: cada campo novo está presente no payload JSON do
 * SellController::inertiaList. Os valores derivados (sla_kind condicional)
 * são testados via análise da função map() existente — Pest browser cobre
 * o end-to-end visual quando o ambiente permite.
 *
 * Refs:
 *  - app/Http/Controllers/SellController.php::inertiaList
 *  - memory/requisitos/Sells/index-r1-visual-comparison.md
 *  - resources/js/Pages/Sells/Index.tsx
 */

const COWORK_SELL_CONTROLLER_PATH = 'app/Http/Controllers/SellController.php';

function coworkReadSellController(): string
{
    return file_get_contents(base_path(COWORK_SELL_CONTROLLER_PATH));
}

it('payload da listagem expõe sla_kind', function () {
    expect(coworkReadSellController())->toContain("'sla_kind' => \$slaKind");
});

it('payload expõe days_to_due signed (null permitido)', function () {
    expect(coworkReadSellController())->toContain("'days_to_due' => \$daysToDue");
});

it('payload expõe pipeline_step + pipeline_total + pipeline_label', function () {
    $source = coworkReadSellController();
    expect($source)
        ->toContain("'pipeline_step'")
        ->toContain("'pipeline_total'")
        ->toContain("'pipeline_label'");
});

it('payload expõe seller_name + seller_abbr', function () {
    $source = coworkReadSellController();
    expect($source)
        ->toContain("'seller_name' => \$sellerName")
        ->toContain("'seller_abbr' => \$sellerAbbr");
});

it('payload expõe items_summary + items_count', function () {
    $source = coworkReadSellController();
    expect($source)
        ->toContain("'items_summary' => \$itemsSummary")
        ->toContain("'items_count'");
});

it('payload expõe payment_method_label + installments', function () {
    $source = coworkReadSellController();
    expect($source)
        ->toContain("'payment_method_label' => \$paymentMethodLabel")
        ->toContain("'installments' => (int) (\$r->installments_count");
});

it('sla_kind cobre os 4 estados — fresh/warning/overdue/paid', function () {
    $source = coworkReadSellController();
    expect($source)
        ->toContain("\$slaKind = 'paid'")
        ->toContain("\$slaKind = 'overdue'")
        ->toContain("\$slaKind = 'warning'")
        ->toContain("\$slaKind = 'fresh'");
});

it('payment_method_label mapeia chaves UltimatePOS pra PT-BR', function () {
    $source = coworkReadSellController();
    expect($source)
        ->toContain("'cash'")
        ->toContain("=> 'Dinheiro'")
        ->toContain("'custom_pay_1' => 'PIX'")
        ->toContain("'custom_pay_2' => 'Boleto'");
});

it('JOIN users sob alias seller_u preserva tenancy', function () {
    $source = coworkReadSellController();
    // LEFT JOIN não impacta business_id global scope porque transactions.business_id
    // permanece como filtro principal em where()
    expect($source)
        ->toContain("leftJoin('users as seller_u', 'transactions.created_by', '=', 'seller_u.id')")
        ->toContain("->where('transactions.business_id', \$business_id)");
});

it('subqueries pipeline_total + items_first_name + installments_count usam alias diferente pra evitar conflito com sps externo', function () {
    $source = coworkReadSellController();
    // pipeline_total subquery usa sps_t (alias interno) — não conflita com sps externo
    expect($source)
        ->toContain('sale_process_stages sps_t')
        ->toContain('transaction_sell_lines tsl_n')
        ->toContain('transaction_payments tp_i');
});

it('preserva campos legacy do US-SELL-008/021/023/024', function () {
    $source = coworkReadSellController();
    // não pode regredir contratos existentes
    expect($source)
        ->toContain("'fiscal_status'")
        ->toContain("'fiscal_modelo'")
        ->toContain("'current_stage_key'")
        ->toContain("'is_grouped_invoice'")
        ->toContain("'display_date'");
});

// ──────────────────────────────────────────────────────────────
// Onda 3 (ADR 0192) — Integração Vendas × Oficina
// Backend payload `/sells-list-json` devolve `source` + `source_label` + `os_ref`
// pra frontend `VdSource` pill renderizar Balcão/Oficina/Online + link OS.
// ──────────────────────────────────────────────────────────────

it('payload expõe source (Onda 3 ADR 0192 · default balcao retroativo)', function () {
    $source = coworkReadSellController();
    expect($source)
        ->toContain("'source' => (string) (\$r->source ?? 'balcao')");
});

it('payload expõe source_label PT-BR derivado server-side (não vaza enum bruto)', function () {
    $source = coworkReadSellController();
    expect($source)
        ->toContain("'source_label'")
        ->toContain("'oficina' => 'Oficina'")
        ->toContain("'online'  => 'Online'")
        ->toContain("default   => 'Balcão'");
});

it('payload expõe os_ref pra cross-link Sells → Repair (link ↗ #OS-NNNN)', function () {
    $source = coworkReadSellController();
    expect($source)
        ->toContain("'os_ref' => \$r->os_ref");
});

// ──────────────────────────────────────────────────────────────
// UC-S12 (Index.casos.md) — indicador "venda com devolução" (setinha de retorno).
// Restaura o indicador perdido no rewrite Cowork #1032 (o payload React nunca
// selecionava return_exists → venda devolvida aparecia como normal). Reportado
// por Guilherme @ biz=4 ROTA LIVRE, 2026-07-03. Critério canônico do UltimatePOS:
// existe uma sell_return apontando pra venda via return_parent_id (mesmo do JOIN
// SR em TransactionUtil::getSellsCurrentFy).
// ──────────────────────────────────────────────────────────────

it('UC-S12 · payload expõe has_return derivado da subquery return_exists', function () {
    $source = coworkReadSellController();
    expect($source)
        ->toContain("'has_return' => (int) (\$r->return_exists ?? 0) > 0");
});

it('UC-S12 · return_exists usa o critério canônico return_parent_id + type=sell_return', function () {
    $source = coworkReadSellController();
    // Regressão que defende: remover a subquery volta a esconder a setinha → falha de CI.
    expect($source)
        ->toContain('sr.return_parent_id = transactions.id')
        ->toContain("sr.type = 'sell_return'")
        ->toContain('as return_exists');
});

it('UC-S12 · SellsTabelaUnificada renderiza o badge de devolução quando has_return', function () {
    $tsx = file_get_contents(base_path('resources/js/Pages/Sells/_components/SellsTabelaUnificada.tsx'));
    expect($tsx)
        ->toContain('v.has_return')
        ->toContain('vd-return-flag')
        ->toContain('Venda com devolução');
});
