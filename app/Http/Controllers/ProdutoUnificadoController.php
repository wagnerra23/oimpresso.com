<?php

namespace App\Http\Controllers;

use App\Brands;
use App\BusinessLocation;
use App\Category;
use App\Product;
use App\SellingPriceGroup;          // UltimatePOS standard — tabelas de preço
use App\TransactionSellLine;        // histórico de uso (consumo de produto em vendas/OS)
use App\Variation;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Manufacturing\Entities\MfgRecipe;            // BOM real do UltimatePOS Mfg
use Modules\Manufacturing\Entities\MfgRecipeIngredient;

/**
 * Tela "Catálogo Unificado" (Cockpit V2) — módulo Produto.
 * Origem: protótipo Cowork [CC] aprovado por [W] em 2026-05-09.
 *
 * IMPORTANTE: este controller mora em app/Http/Controllers/ porque o domínio
 * "produto" no oimpresso é UltimatePOS herdado (App\Product, App\Variation,
 * App\Category direto em app/), NÃO um módulo separado. BOM real vem de
 * Modules\Manufacturing (MfgRecipe). Tabelas de preço = SellingPriceGroup.
 *
 * Persona-foco: Larissa [L] — balcão ROTA LIVRE 1280×1024.
 *
 * @memcofre tela=/products/unificado status=em-implementacao
 *
 * TODO [CL]:
 * - Confirmar nomes exatos das classes Mfg* (ler Modules/Manufacturing/Entities/).
 * - Confirmar SellingPriceGroup vs VariationLocationDetails pra preço por tabela.
 * - Plugar permission middleware: 'product.view', 'product.create', 'product.update'.
 * - Reusar BusinessUtil/ProductUtil traits do core (ver ProductController existente).
 */
class ProdutoUnificadoController extends Controller
{
    public function index(Request $request): Response
    {
        $business_id = request()->session()->get('user.business_id');

        $filters = [
            'tela'      => $request->string('tela', 'produtos')->toString(),
            'tab'       => $request->string('tab', 'all')->toString(),
            'busca'     => $request->string('busca', '')->toString(),
            'categoria' => $request->integer('categoria') ?: null,
            'view'      => $request->string('view', 'table')->toString(),
            'densidade' => $request->string('densidade', 'comfortable')->toString(),
        ];

        return Inertia::render('Produto/Unificado/Index', [
            'tela'       => $filters['tela'],
            'filters'    => $filters,
            // closures D-14: não mudam com a troca de sub-tela (`tela`) — pulam no
            // partial reload do setSubTela. kpis/categorias são por business; produtos
            // varia com tab/busca/categoria mas o nav de sub-tela preserva esses filtros.
            'kpis'       => fn () => $this->kpis($business_id),
            'produtos'   => fn () => $this->produtos($business_id, $filters),
            'categorias' => fn () => $this->categorias($business_id),
            'insumos'    => $filters['tela'] === 'insumos'   ? $this->insumos($business_id)   : [],
            'tabelas'    => $filters['tela'] === 'tabelas'   ? $this->tabelas($business_id)   : [],
            'historico'  => $filters['tela'] === 'historico' ? $this->historico($business_id) : [],
        ]);
    }

    // ───────── Helpers privados ─────────

    private function kpis(int $business_id): array
    {
        $base = Product::where('business_id', $business_id);

        // "Saídas em 30d" e "uses_30d" exigem agregação de transaction_sell_lines.
        // TODO [CL]: cachear por job diário pra evitar N+1 — UltimatePOS faz isso via cron.
        $saidas30 = TransactionSellLine::join('transactions as t', 't.id', '=', 'transaction_sell_lines.transaction_id')
            ->where('t.business_id', $business_id)
            ->where('t.type', 'sell')
            ->where('t.status', 'final')
            ->where('t.transaction_date', '>=', now()->subDays(30))
            ->sum('transaction_sell_lines.quantity');

        return [
            'catalogo_ativo'  => (clone $base)->active()->count(),
            'populares'       => 0,  // TODO [CL]: top vendidos 30d (join sell_lines)
            'saidas_30d'      => (int) $saidas30,
            'margem_media'    => 0.0, // TODO [CL]: AVG((default_sell_price - default_purchase_price) / default_sell_price) via Variation
            'sem_giro'        => 0,   // TODO [CL]: count(products) sem sell_line nos últimos 30d
        ];
    }

    private function produtos(int $business_id, array $f): array
    {
        $q = Product::where('business_id', $business_id)
            ->with(['category:id,name', 'brand:id,name', 'unit:id,short_name', 'variations']);

        match ($f['tab']) {
            'active'   => $q->active(),
            'inactive' => $q->inactive(),
            'lowstock' => $q,  // TODO [CL]: join variation_location_details where qty_available <= alert_quantity
            default    => null,
        };

        if ($f['categoria']) $q->where('category_id', $f['categoria']);
        if ($f['busca']) {
            $q->where(function ($qq) use ($f) {
                $qq->where('name', 'like', "%{$f['busca']}%")
                   ->orWhere('sku', 'like', "%{$f['busca']}%");
            });
        }

        return $q->orderBy('name')->limit(500)->get()->map(function (Product $p) {
            $defaultVar = $p->variations->firstWhere('name', 'DUMMY') ?? $p->variations->first();
            $price = (float) ($defaultVar->default_sell_price_inc_tax ?? 0);
            $cost  = (float) ($defaultVar->default_purchase_price ?? 0);
            return [
                'id'         => $p->id,
                'sku'        => $p->sku,
                'name'       => $p->name,
                'cat'        => $p->category?->id ? (string) $p->category->id : null,
                'cat_label'  => $p->category?->name,
                'unit'       => $p->unit?->short_name ?? 'un',
                'price'      => $price,
                'cost'       => $cost,
                'margin'     => $price > 0 ? round(($price - $cost) / $price, 4) : 0,
                'stockKind'  => $p->enable_stock ? 'estoque' : 'sob_demanda',
                'stockQty'   => null, // TODO [CL]: somar variation_location_details.qty_available
                'uses30'     => 0,    // TODO [CL]: agregação cached
                'active'     => $p->is_inactive == 0,
                'updated'    => $p->updated_at?->locale('pt_BR')->isoFormat('DD MMM'),
                'bomCount'   => 0,    // TODO [CL]: count(MfgRecipe::where('variation_id', $defaultVar->id))
            ];
        })->all();
    }

    private function categorias(int $business_id): array
    {
        return Category::where('business_id', $business_id)
            ->where('category_type', 'product')
            ->whereNull('parent_id')
            ->withCount('products')
            ->orderBy('name')->get()
            ->map(fn ($c) => [
                'id'    => $c->id,
                'slug'  => $c->slug ?? str($c->name)->slug(),
                'label' => $c->name,
                'count' => (int) $c->products_count,
            ])->all();
    }

    /**
     * Insumos = produtos marcados como `not_for_selling = 1` no UltimatePOS,
     * ou produtos referenciados como ingredient em MfgRecipe. TODO [CL]: confirmar
     * convenção do oimpresso com Wagner.
     */
    private function insumos(int $business_id): array
    {
        return Product::where('business_id', $business_id)
            ->where('not_for_selling', 1)
            ->with('unit:id,short_name')
            ->orderBy('name')->limit(200)->get()
            ->map(fn ($p) => [
                'id'         => $p->id,
                'name'       => $p->name,
                'unit'       => $p->unit?->short_name ?? 'un',
                'cost'       => 0.0, // TODO: variation default_purchase_price
                'stock'      => 0,   // TODO: variation_location_details
                'fornecedor' => null, // TODO: contact_supplier no UltimatePOS
            ])->all();
    }

    /**
     * Tabelas de preço = SellingPriceGroup (UltimatePOS standard).
     * Multiplicador NÃO existe nativamente — UltimatePOS guarda preço por variation×group.
     * O protótipo Cowork usa multiplicador como simplificação visual.
     * TODO [CL] decidir com Wagner: (a) adicionar coluna `multiplier` em SellingPriceGroup,
     * ou (b) calcular preço por tabela via VariationGroupPrice e dropar conceito de multiplicador.
     */
    private function tabelas(int $business_id): array
    {
        return SellingPriceGroup::where('business_id', $business_id)
            ->orderBy('name')->get()
            ->map(fn ($g) => [
                'id'    => (string) $g->id,
                'label' => $g->name,
                'desc'  => $g->description ?? '',
                'mult'  => 1.00, // TODO [CL]: ver decisão acima
            ])->all();
    }

    /**
     * Histórico de uso = transaction_sell_lines dos últimos 30d.
     * Cada linha é um produto consumido em uma venda/OS final.
     */
    private function historico(int $business_id): array
    {
        return TransactionSellLine::join('transactions as t', 't.id', '=', 'transaction_sell_lines.transaction_id')
            ->join('products as p', 'p.id', '=', 'transaction_sell_lines.product_id')
            ->leftJoin('contacts as c', 'c.id', '=', 't.contact_id')
            ->leftJoin('categories as cat', 'cat.id', '=', 'p.category_id')
            ->where('t.business_id', $business_id)
            ->where('t.type', 'sell')
            ->where('t.status', 'final')
            ->where('t.transaction_date', '>=', now()->subDays(30))
            ->orderByDesc('t.transaction_date')
            ->limit(200)
            ->get([
                't.invoice_no as os', 't.transaction_date as date',
                'p.sku as prod_id', 'p.name as prod_name', 'cat.name as cat',
                'c.name as client',
                'transaction_sell_lines.quantity as qty',
                'transaction_sell_lines.unit_price_inc_tax as unit_price',
            ])
            ->map(fn ($r) => [
                'os'       => $r->os,
                'date'     => substr($r->date, 0, 10),
                'prodId'   => $r->prod_id,
                'prodName' => $r->prod_name,
                'cat'      => $r->cat,
                'unit'     => 'un',
                'client'   => $r->client,
                'qty'      => (float) $r->qty,
                'value'    => (float) $r->qty * (float) $r->unit_price,
            ])->all();
    }
}
