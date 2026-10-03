<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Produtos do app das lojas (tela 19). Contrato: memory/requisitos/AppMobile/API-CONTRATO-v1.md §9.1.
 * Só leitura: preço e estoque são exibidos, nunca gravados aqui (Novo produto = tela 20, PR à parte).
 *
 * - Visível com `product.view`, a mesma permissão da lista web de produtos.
 * - Produtos ativos do business do token, sem os do tipo `modifier` (adicional de cardápio).
 * - Estoque somado só nos locais que o usuário pode ver (User::permitted_locations). Usuário sem
 *   nenhum local permitido vê o estoque vazio, nunca o de todos.
 * - "Estoque baixo" = a mesma regra do alerta da web (ProductUtil::getProductAlert): alguma
 *   variação × local com qty_available <= alert_quantity, em produto que controla estoque.
 *
 * Tier 0 (ADR 0093): business_id do usuário do token, explícito em toda consulta.
 */
class ProdutosController extends Controller
{
    private const POR_PAGINA = 30;

    public function produtos(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if (! $user->can('product.view')) {
            return response()->json(['erro' => 'sem_permissao', 'mensagem' => 'Seu usuário não tem acesso aos produtos.'], 403);
        }
        $bizId = (int) $user->business_id;
        $locais = $this->locais($user, $bizId);
        $busca = trim((string) $request->query('q', ''));
        $categoria = (string) $request->query('categoria', 'todas');
        $pagina = max((int) $request->query('pagina', 1), 1);

        $q = $this->base($bizId, $busca);
        if ($categoria !== 'todas' && ctype_digit($categoria)) {
            $q->where('p.category_id', (int) $categoria);
        }

        $linhas = (clone $q)
            ->leftJoin('units as u', 'u.id', '=', 'p.unit_id')
            ->select(['p.id', 'p.name', 'p.sku', 'p.type', 'p.enable_stock', 'p.alert_quantity', 'c.name as categoria', 'u.short_name as unidade'])
            ->selectSub(fn ($s) => $s->from('variations as v')->whereColumn('v.product_id', 'p.id')->whereNull('v.deleted_at')->selectRaw('MIN(v.sell_price_inc_tax)'), 'preco')
            ->selectSub(fn ($s) => $s->from('variations as v')->whereColumn('v.product_id', 'p.id')->whereNull('v.deleted_at')->selectRaw('COUNT(*)'), 'n_variacoes')
            ->selectSub(fn ($s) => $this->noLocal($s->from('variation_location_details as vld')->whereColumn('vld.product_id', 'p.id'), $locais)->selectRaw('SUM(vld.qty_available)'), 'qtd')
            ->selectSub(fn ($s) => $this->baixoQuery($s, $locais)->whereColumn('vld.product_id', 'p.id')->selectRaw('COUNT(*)'), 'n_baixo')
            ->orderBy('p.name')
            ->offset(($pagina - 1) * self::POR_PAGINA)
            ->limit(self::POR_PAGINA + 1)
            ->get();

        $temMais = $linhas->count() > self::POR_PAGINA;

        $categorias = $this->base($bizId, $busca)
            ->whereNotNull('p.category_id')
            ->groupBy('p.category_id', 'c.name')
            ->orderBy('c.name')
            ->get(['p.category_id as id', 'c.name as nome', DB::raw('COUNT(*) as total')])
            ->map(fn ($c) => ['id' => (int) $c->id, 'nome' => (string) $c->nome, 'total' => (int) $c->total])
            ->values();

        return response()->json([
            'itens' => $linhas->take(self::POR_PAGINA)->map(fn ($p) => [
                'id' => (int) $p->id,
                'nome' => (string) $p->name,
                'codigo' => (string) $p->sku,
                'categoria' => $p->categoria ?: null,
                'calculo' => $p->unidade ? 'por ' . mb_strtolower((string) $p->unidade) : null,
                'preco' => $p->preco !== null ? round((float) $p->preco, 2) : null,
                'variacoes' => $p->type === 'variable' ? (int) $p->n_variacoes : null,
                'estoque' => [
                    'controla' => (bool) $p->enable_stock,
                    'qtd' => $p->enable_stock ? round((float) ($p->qtd ?? 0), 4) : null,
                    'unidade' => $p->unidade ?: null,
                ],
                'baixo' => (int) $p->n_baixo > 0,
            ])->values(),
            'categorias' => $categorias,
            'total' => (clone $this->base($bizId, $busca))->count(),
            'baixo_estoque' => $this->produtosEmAlerta($bizId, $locais),
            'pagina' => $pagina,
            'tem_mais' => $temMais,
        ]);
    }

    /**
     * GET /api/app/estoque?filtro=todos|baixo&q=&pagina= — Estoque (tela 05), §9.2. Só leitura.
     * Uma linha por variação × loja (variation_location_details), só nas lojas permitidas e só de
     * produto que controla estoque. `minimo` = alert_quantity; baixo = a regra do alerta da web.
     */
    public function estoque(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if (! $user->can('product.view')) {
            return response()->json(['erro' => 'sem_permissao', 'mensagem' => 'Seu usuário não tem acesso ao estoque.'], 403);
        }
        $bizId = (int) $user->business_id;
        $locais = $this->locais($user, $bizId);
        $busca = trim((string) $request->query('q', ''));
        $filtro = $request->query('filtro') === 'baixo' ? 'baixo' : 'todos';
        $pagina = max((int) $request->query('pagina', 1), 1);

        $q = $this->baseEstoque($bizId, $locais, $busca);
        if ($filtro === 'baixo') {
            $this->soBaixo($q);
        }
        $linhas = $this->comDetalheDaLinha($q, $bizId)
            ->orderBy('p.name')->orderBy('v.name')->orderBy('l.name')
            ->offset(($pagina - 1) * self::POR_PAGINA)
            ->limit(self::POR_PAGINA + 1)
            ->get();

        $temMais = $linhas->count() > self::POR_PAGINA;

        return response()->json([
            'itens' => $linhas->take(self::POR_PAGINA)->map(fn ($e) => $this->linhaEstoque($e))->values(),
            'contadores' => [
                'todos' => $this->baseEstoque($bizId, $locais, $busca)->count(),
                'baixo' => $this->soBaixo($this->baseEstoque($bizId, $locais, $busca))->count(),
            ],
            'pagina' => $pagina,
            'tem_mais' => $temMais,
        ]);
    }

    /**
     * GET /api/app/estoque/{id} — Movimentações de UM item (tela 29), §9.3. Só leitura.
     * `id` = a linha da 05 (variation_location_details.id). O histórico é o mesmo da tela web
     * de histórico de estoque (ProductUtil::getVariationStockHistory), do mais novo ao mais velho.
     * Registrar movimento fica na web (decisão [W] 2026-10-02: cada tipo é uma transação contábil).
     */
    public function estoqueItem(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if (! $user->can('product.view')) {
            return response()->json(['erro' => 'sem_permissao', 'mensagem' => 'Seu usuário não tem acesso ao estoque.'], 403);
        }
        $bizId = (int) $user->business_id;
        $pagina = max((int) $request->query('pagina', 1), 1);

        $e = $this->comDetalheDaLinha($this->baseEstoque($bizId, $this->locais($user, $bizId), '')->where('vld.id', $id), $bizId)
            ->addSelect(['vld.variation_id', 'vld.location_id'])
            ->first();
        if (! $e) {
            return response()->json(['erro' => 'nao_encontrado', 'mensagem' => 'Item de estoque não encontrado.'], 404);
        }

        // getVariationStockHistory já devolve do mais novo ao mais velho (termina em array_reverse).
        $historico = $this->productUtil()->getVariationStockHistory($bizId, (int) $e->variation_id, (int) $e->location_id);
        $fatia = array_slice($historico, ($pagina - 1) * self::POR_PAGINA, self::POR_PAGINA);

        return response()->json([
            'item' => $this->linhaEstoque($e),
            'historico' => array_map(fn (array $h) => [
                'id' => (int) $h['transaction_id'],
                'tipo' => (string) $h['type'],
                'rotulo' => (string) $h['type_label'],
                'referencia' => trim(implode(' · ', array_filter([
                    (string) ($h['ref_no'] ?? ''),
                    (string) ($h['supplier_business_name'] ?? '') ?: (string) ($h['contact_name'] ?? ''),
                ], fn ($x) => $x !== ''))) ?: null,
                'quando' => \Carbon\Carbon::parse($h['date'])->toIso8601String(),
                'qtd' => round((float) $h['quantity_change'], 4),
                'saldo' => round((float) $h['stock'], 4),
            ], $fatia),
            'pagina' => $pagina,
            'tem_mais' => count($historico) > $pagina * self::POR_PAGINA,
        ]);
    }

    /** Linhas variação × loja de estoque do business, só nas lojas permitidas, com a busca. */
    private function baseEstoque(int $bizId, ?array $locais, string $busca): Builder
    {
        $q = DB::table('variation_location_details as vld')
            ->join('variations as v', 'v.id', '=', 'vld.variation_id')
            ->join('products as p', 'p.id', '=', 'vld.product_id')
            ->join('business_locations as l', 'l.id', '=', 'vld.location_id')
            ->where('p.business_id', $bizId)
            ->where('l.business_id', $bizId)
            ->where('p.enable_stock', 1)
            ->where('p.is_inactive', 0)
            ->where('p.type', '!=', 'modifier')
            ->whereNull('v.deleted_at');
        $this->noLocal($q, $locais);
        if ($busca !== '') {
            $like = '%' . $busca . '%';
            $q->where(fn ($w) => $w->where('p.name', 'like', $like)
                ->orWhere('p.sku', 'like', $like)
                ->orWhere('v.sub_sku', 'like', $like));
        }

        return $q;
    }

    private function soBaixo(Builder $q): Builder
    {
        return $q->whereNotNull('p.alert_quantity')->whereColumn('vld.qty_available', '<=', 'p.alert_quantity');
    }

    /** Acrescenta unidade e prateleira e seleciona as colunas que a linha de estoque devolve. */
    private function comDetalheDaLinha(Builder $q, int $bizId): Builder
    {
        return $q->leftJoin('units as u', 'u.id', '=', 'p.unit_id')
            ->leftJoin('product_racks as r', function ($j) use ($bizId) {
                $j->on('r.product_id', '=', 'p.id')->on('r.location_id', '=', 'vld.location_id')->where('r.business_id', $bizId);
            })
            ->select([
                'vld.id', 'p.id as produto_id', 'p.name', 'p.type', 'v.name as variacao', 'p.sku', 'v.sub_sku',
                'vld.qty_available', 'p.alert_quantity', 'u.short_name as unidade', 'l.name as local',
                'r.rack', 'r.row', 'r.position',
            ]);
    }

    private function linhaEstoque(object $e): array
    {
        $prateleira = implode(' · ', array_filter([$e->rack, $e->row, $e->position], fn ($x) => (string) $x !== ''));

        return [
            'id' => (int) $e->id,
            'produto_id' => (int) $e->produto_id,
            'nome' => $e->type === 'variable' ? $e->name . ' · ' . $e->variacao : (string) $e->name,
            'codigo' => (string) ($e->sub_sku ?: $e->sku),
            'qtd' => round((float) $e->qty_available, 4),
            'minimo' => $e->alert_quantity !== null ? round((float) $e->alert_quantity, 4) : null,
            'unidade' => $e->unidade ?: null,
            'local' => (string) $e->local,
            'prateleira' => $prateleira !== '' ? $prateleira : null,
        ];
    }

    private function productUtil(): \App\Utils\ProductUtil
    {
        return app(\App\Utils\ProductUtil::class);
    }

    // ------------------------------------------------------------------

    /** Produtos ativos e vendáveis do business, com a busca por nome, código ou categoria. */
    private function base(int $bizId, string $busca): Builder
    {
        $q = DB::table('products as p')
            ->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
            ->where('p.business_id', $bizId)
            ->where('p.is_inactive', 0)
            ->where('p.type', '!=', 'modifier');

        if ($busca !== '') {
            $like = '%' . $busca . '%';
            $q->where(fn ($w) => $w->where('p.name', 'like', $like)
                ->orWhere('p.sku', 'like', $like)
                ->orWhere('c.name', 'like', $like));
        }

        return $q;
    }

    /**
     * Locais que o usuário pode ver: `null` = todos; lista (possivelmente vazia) = só esses.
     *
     * @return list<int>|null
     */
    private function locais(User $user, int $bizId): ?array
    {
        $p = $user->permitted_locations($bizId);

        if ($p === 'all') {
            return null;
        }
        $ids = [];
        foreach ((array) $p as $id) {
            $ids[] = (int) $id;
        }

        return $ids;
    }

    /** @param  list<int>|null  $locais */
    private function noLocal(Builder $q, ?array $locais): Builder
    {
        return $locais === null ? $q : $q->whereIn('vld.location_id', $locais === [] ? [0] : $locais);
    }

    /**
     * Linhas variação × local em estoque baixo — a regra do ProductUtil::getProductAlert.
     *
     * @param  list<int>|null  $locais
     */
    private function baixoQuery(Builder $q, ?array $locais): Builder
    {
        $q->from('variation_location_details as vld')
            ->join('variations as v2', 'v2.id', '=', 'vld.variation_id')
            ->join('products as p2', 'p2.id', '=', 'vld.product_id')
            ->where('p2.enable_stock', 1)
            ->where('p2.is_inactive', 0)
            ->whereNull('v2.deleted_at')
            ->whereNotNull('p2.alert_quantity')
            ->whereColumn('vld.qty_available', '<=', 'p2.alert_quantity');

        return $this->noLocal($q, $locais);
    }

    /** @param  list<int>|null  $locais */
    private function produtosEmAlerta(int $bizId, ?array $locais): int
    {
        return (int) $this->baixoQuery(DB::query(), $locais)
            ->where('p2.business_id', $bizId)
            ->where('p2.type', '!=', 'modifier')
            ->distinct()
            ->count('vld.product_id');
    }
}
