<?php

declare(strict_types=1);

namespace App\Services\Relatorios\Montavel;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * As 4 fontes do relatório montável (US-REL-001), confirmadas por [W] em 2026-10-27.
 *
 * Tudo aqui é LISTA FECHADA: o usuário só escolhe chaves; a expressão SQL de cada campo,
 * filtro e agrupamento mora neste arquivo. Nenhum texto do pedido vira SQL.
 * Cada `base()` já sai filtrada por business_id (Tier 0, ADR 0093).
 *
 * Campo: [rótulo, expressão SQL, tipo (txt|data|brl|num), só para quem vê custo?]
 * Vendas = o mesmo recorte do TransactionUtil::getSellTotals (sell + final), para os totais baterem.
 */
final class FontesDoRelatorio
{
    public function nomes(): array
    {
        return ['vendas', 'titulos', 'estoque', 'os'];
    }

    public function definicao(string $fonte): array
    {
        return match ($fonte) {
            'vendas' => [
                'rotulo' => 'Vendas', 'data' => 't.transaction_date', 'local' => 't.location_id',
                'campos' => [
                    'data' => ['Data', 'DATE(t.transaction_date)', 'data'],
                    'numero' => ['Número', 't.invoice_no', 'txt'],
                    'cliente' => ['Cliente', 'c.name', 'txt'],
                    'vendedor' => ['Vendedor', "TRIM(CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,'')))", 'txt'],
                    'local' => ['Local', 'l.name', 'txt'],
                    'imposto' => ['Imposto', 't.tax_amount', 'brl'],
                    'total' => ['Total', 't.final_total', 'brl'],
                    'pagamento' => ['Pagamento', 't.payment_status', 'txt'],
                ],
                'filtros' => ['local' => 't.location_id', 'vendedor' => 't.created_by', 'pagamento' => 't.payment_status', 'cliente' => 't.contact_id'],
                'grupos' => ['dia' => 'DATE(t.transaction_date)', 'mes' => "DATE_FORMAT(t.transaction_date,'%Y-%m')", 'vendedor' => "TRIM(CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,'')))", 'local' => 'l.name', 'cliente' => 'c.name'],
            ],
            'titulos' => [
                'rotulo' => 'Títulos', 'data' => 'ft.vencimento', 'local' => null,
                'campos' => [
                    'tipo' => ['Tipo', 'ft.tipo', 'txt'],
                    'cliente' => ['Cliente/Fornecedor', 'COALESCE(c.name, ft.cliente_descricao)', 'txt'],
                    'emissao' => ['Emissão', 'ft.emissao', 'data'],
                    'vencimento' => ['Vencimento', 'ft.vencimento', 'data'],
                    'categoria' => ['Categoria', 'fc.nome', 'txt'],
                    'status' => ['Situação', 'ft.status', 'txt'],
                    'valor' => ['Valor', 'ft.valor_total', 'brl'],
                    'aberto' => ['Em aberto', 'ft.valor_aberto', 'brl'],
                ],
                'filtros' => ['tipo' => 'ft.tipo', 'status' => 'ft.status', 'categoria' => 'ft.categoria_id'],
                'grupos' => ['mes' => "DATE_FORMAT(ft.vencimento,'%Y-%m')", 'categoria' => 'fc.nome', 'cliente' => 'COALESCE(c.name, ft.cliente_descricao)', 'status' => 'ft.status'],
            ],
            'estoque' => [
                'rotulo' => 'Estoque', 'data' => null, 'local' => 'vld.location_id',
                'campos' => [
                    'produto' => ['Produto', "CONCAT(p.name, IF(v.name = 'DUMMY', '', CONCAT(' ', v.name)))", 'txt'],
                    'sku' => ['SKU', 'v.sub_sku', 'txt'],
                    'local' => ['Local', 'l.name', 'txt'],
                    'categoria' => ['Categoria', 'cat.name', 'txt'],
                    'qtd' => ['Quantidade', 'vld.qty_available', 'num'],
                    'custo' => ['Custo unitário', 'v.dpp_inc_tax', 'brl', true],
                    'valor_estoque' => ['Valor em estoque', 'vld.qty_available * v.dpp_inc_tax', 'brl', true],
                ],
                'filtros' => ['local' => 'vld.location_id', 'categoria' => 'p.category_id', 'marca' => 'p.brand_id'],
                'grupos' => ['local' => 'l.name', 'categoria' => 'cat.name'],
            ],
            'os' => [
                'rotulo' => 'OS', 'data' => 'js.created_at', 'local' => 'js.location_id',
                'campos' => [
                    'numero' => ['Número', 'js.job_sheet_no', 'txt'],
                    'cliente' => ['Cliente', 'c.name', 'txt'],
                    'status' => ['Status', 'rs.name', 'txt'],
                    'tecnico' => ['Técnico', "TRIM(CONCAT(COALESCE(st.first_name,''),' ',COALESCE(st.last_name,'')))", 'txt'],
                    'entrada' => ['Entrada', 'DATE(js.created_at)', 'data'],
                    'entrega' => ['Entrega prevista', 'DATE(js.delivery_date)', 'data'],
                    'custo_estimado' => ['Custo estimado', 'js.estimated_cost', 'brl'],
                ],
                'filtros' => ['status' => 'js.status_id', 'tecnico' => 'js.service_staff', 'local' => 'js.location_id'],
                'grupos' => ['status' => 'rs.name', 'tecnico' => "TRIM(CONCAT(COALESCE(st.first_name,''),' ',COALESCE(st.last_name,'')))", 'mes' => "DATE_FORMAT(js.created_at,'%Y-%m')"],
            ],
            default => throw new \InvalidArgumentException("Fonte desconhecida: {$fonte}"),
        };
    }

    /** Consulta base da fonte, já presa à empresa. */
    public function base(string $fonte, int $businessId): Builder
    {
        return match ($fonte) {
            'vendas' => DB::table('transactions as t')
                ->leftJoin('contacts as c', 'c.id', '=', 't.contact_id')
                ->leftJoin('business_locations as l', 'l.id', '=', 't.location_id')
                ->leftJoin('users as u', 'u.id', '=', 't.created_by')
                ->where('t.business_id', $businessId)->where('t.type', 'sell')->where('t.status', 'final'),
            'titulos' => DB::table('fin_titulos as ft')
                ->leftJoin('contacts as c', 'c.id', '=', 'ft.cliente_id')
                ->leftJoin('fin_categorias as fc', 'fc.id', '=', 'ft.categoria_id')
                ->where('ft.business_id', $businessId)->whereNull('ft.deleted_at'),
            'estoque' => DB::table('variation_location_details as vld')
                ->join('products as p', 'p.id', '=', 'vld.product_id')
                ->join('variations as v', 'v.id', '=', 'vld.variation_id')
                ->join('business_locations as l', 'l.id', '=', 'vld.location_id')
                ->leftJoin('categories as cat', 'cat.id', '=', 'p.category_id')
                ->where('p.business_id', $businessId)->where('l.business_id', $businessId)->whereNull('v.deleted_at'),
            'os' => DB::table('repair_job_sheets as js')
                ->leftJoin('contacts as c', 'c.id', '=', 'js.contact_id')
                ->leftJoin('repair_statuses as rs', 'rs.id', '=', 'js.status_id')
                ->leftJoin('users as st', 'st.id', '=', 'js.service_staff')
                ->where('js.business_id', $businessId),
            default => throw new \InvalidArgumentException("Fonte desconhecida: {$fonte}"),
        };
    }
}
