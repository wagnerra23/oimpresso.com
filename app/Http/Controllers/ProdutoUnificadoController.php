<?php

namespace App\Http\Controllers;

use App\Brands;
use App\BusinessLocation;
use App\Category;
use App\Product;
use App\SellingPriceGroup;          // UltimatePOS standard — tabelas de preço
use App\TransactionSellLine;        // histórico de uso (consumo de produto em vendas/OS)
use App\Utils\ModuleUtil;           // camada 1 (módulo no pacote do business)
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
 * ── VISIBILIDADE (UC-PUNI-01..06 · Index.casos.md) ──────────────────────────
 * Esta tela reúne numa rota só o que as outras telas do Produto gateiam separadamente:
 * custo, preço de venda, tabelas de preço e composição. Até 2026-08-13 ela não consultava
 * permissão nenhuma — varredura de `view_purchase_price|access_default_selling_price` no
 * arquivo devolvia 0 ocorrências, e a rota não tinha gate. O contrato agora é:
 *
 *   - a tela      → product.view OU product.create (mesma semântica de ProductController@index)
 *   - cost/margin → view_purchase_price
 *   - price/value → access_default_selling_price (inclusive a porta lateral do Histórico)
 *   - tabelas     → access_default_selling_price (decisão 2026-08-11: mesmo dado, mesmo gate)
 *   - insumos/BOM → módulo Manufacturing no pacote + manufacturing.access_recipe
 *
 * A regra é AUSÊNCIA, não campo vazio (AR-PROD-015: os campos SOMEM da tela). Por isso a
 * chave NÃO é emitida — emitir 0/null afirmaria um valor que o usuário não pode ver, e o
 * teste de contrato varre o payload por VALOR (renomear a chave não o faz passar).
 *
 * TODO [CL]:
 * - Confirmar nomes exatos das classes Mfg* (ler Modules/Manufacturing/Entities/).
 * - Confirmar SellingPriceGroup vs VariationLocationDetails pra preço por tabela.
 * - Reusar BusinessUtil/ProductUtil traits do core (ver ProductController existente).
 */
class ProdutoUnificadoController extends Controller
{
    /**
     * Opções de "Por página" que a UI oferece. Serve de allowlist do querystring:
     * valor fora daqui cai no default (20), pra ninguém pedir a lista inteira por URL.
     */
    private const POR_PAGINA_OPCOES = [12, 20, 50, 100];

    /** Memo do resultado paginado — as props `produtos` e `paginacao` leem a MESMA query. */
    private ?array $produtosPaginados = null;

    public function __construct(private readonly ModuleUtil $moduleUtil)
    {
    }

    public function index(Request $request): Response
    {
        // UC-PUNI-06 — a semântica canônica NÃO é middleware: a lista irmã aborta DENTRO do
        // controller e aceita `product.view` OU `product.create` (ProductController@index:66),
        // porque quem pode cadastrar produto precisa alcançar o catálogo.
        if (! auth()->user()->can('product.view') && ! auth()->user()->can('product.create')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        $podeVerCusto = (bool) auth()->user()->can('view_purchase_price');
        $podeVerPreco = (bool) auth()->user()->can('access_default_selling_price');
        $podeVerBom   = $this->podeVerComposicao((int) $business_id);

        $filters = [
            'tela'      => $request->string('tela', 'produtos')->toString(),
            'tab'       => $request->string('tab', 'all')->toString(),
            'busca'     => $request->string('busca', '')->toString(),
            'categoria' => $request->integer('categoria') ?: null,
            'view'      => $request->string('view', 'table')->toString(),
            'densidade' => $request->string('densidade', 'comfortable')->toString(),
            // Paginação: `por_pagina` é VALIDADO contra as opções que a UI oferece — querystring
            // é entrada de usuário, e `?por_pagina=99999` viraria o `limit(500)` de volta.
            'pagina'     => max(1, $request->integer('pagina') ?: 1),
            'por_pagina' => in_array($pp = $request->integer('por_pagina') ?: 20, self::POR_PAGINA_OPCOES, true)
                ? $pp
                : 20,
        ];

        return Inertia::render('Produto/Unificado/Index', [
            'tela'       => $filters['tela'],
            'filters'    => $filters,
            // EAGER (não closure): são 3 booleanos já resolvidos acima — embrulhar em closure
            // não pouparia query nenhuma, que é o único ganho do defer/D-14.
            // O `only:[...]` do setSubTela NÃO pede esta prop, então ela não viaja no partial
            // reload — e não precisa: o Inertia MESCLA a resposta parcial com as props que a
            // página já tem no cliente, então `permissoes` persiste entre as sub-telas. (Isso
            // vale pra prop eager e closure igual: o que decide o que viaja é a lista `only`,
            // não a forma da prop.) Do outro lado, o `.tsx` aplica default fail-closed — se a
            // prop faltar, esconde tudo em vez de estourar; ausência nunca vira permissão.
            'permissoes' => [
                'custo'      => $podeVerCusto,
                'preco'      => $podeVerPreco,
                'composicao' => $podeVerBom,
            ],
            // closures D-14: não mudam com a troca de sub-tela (`tela`) — pulam no
            // partial reload do setSubTela. kpis/categorias são por business; produtos
            // varia com tab/busca/categoria mas o nav de sub-tela preserva esses filtros.
            'kpis'       => fn () => $this->kpis($business_id),
            'produtos'   => fn () => $this->produtos($business_id, $filters, $podeVerCusto, $podeVerPreco, $podeVerBom)['rows'],
            // Meta da paginação em prop SEPARADA de propósito: `produtos` continua sendo a LISTA
            // crua. Os UCs do contrato fazem `collect($props['produtos'])->firstWhere('id', …)` —
            // embrulhar a lista num objeto quebraria os 7 casos que acabaram de entrar (#5597).
            'paginacao'  => fn () => $this->produtos($business_id, $filters, $podeVerCusto, $podeVerPreco, $podeVerBom)['meta'],
            'categorias' => fn () => $this->categorias($business_id),
            // UC-PUNI-04 / UC-PUNI-03: a sub-tela inteira não é servida sem o direito.
            // O gate é aqui (e não dentro do helper) pra que a prop nasça `[]` sem custo de query.
            'insumos'    => $filters['tela'] === 'insumos'   && $podeVerBom  ? $this->insumos($business_id, $podeVerCusto) : [],
            'tabelas'    => $filters['tela'] === 'tabelas'   && $podeVerPreco ? $this->tabelas($business_id)              : [],
            'historico'  => $filters['tela'] === 'historico' ? $this->historico($business_id, $podeVerPreco) : [],
        ]);
    }

    /**
     * Composição (BOM/insumos) = camada 1 (módulo no pacote do business) + camada 3 (permissão
     * do papel), as camadas canônicas de habilitar módulo por business. ZERO permissão nova e
     * NUNCA `if ($business_id === N)` — ver memory/reference/feedback-habilitar-modulo-por-business.md.
     *
     * O predicado das camadas 1 é cópia literal de ProductController:328 (`isModuleInstalled`
     * + superadmin OU assinatura); a camada 3 (`manufacturing.access_recipe`) é a mesma que
     * Modules\Manufacturing\Http\Controllers\RecipeController:66 exige pra ver receita.
     */
    private function podeVerComposicao(int $business_id): bool
    {
        if (! $this->moduleUtil->isModuleInstalled('Manufacturing')) {
            return false;
        }

        $temModuloNoPacote = auth()->user()->can('superadmin')
            || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'manufacturing_module');

        return (bool) $temModuloNoPacote && auth()->user()->can('manufacturing.access_recipe');
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

    /**
     * Catálogo paginado.
     *
     * ANTES desta versão a lista saía com `->limit(500)->get()` e SEM paginação: catálogo acima
     * de 500 produtos era truncado EM SILÊNCIO — a tela não dizia que havia mais, e a Larissa
     * simplesmente nunca via o resto. A lista irmã `/contacts` mostra "1–50 de 13.433" em 269
     * páginas; esta mostrava 500 e ponto.
     *
     * MEMOIZADO: as props `produtos` e `paginacao` chamam este método na mesma request. Sem o
     * memo, o `paginate()` roda 2× (2 counts + 2 selects) — e as duas closures são justamente
     * o caminho que o partial reload do `setSubTela` percorre.
     *
     * @return array{rows: list<array<string,mixed>>, meta: array<string,int|null>}
     */
    private function produtos(int $business_id, array $f, bool $podeVerCusto, bool $podeVerPreco, bool $podeVerBom): array
    {
        if ($this->produtosPaginados !== null) {
            return $this->produtosPaginados;
        }

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

        $pagina = $q->orderBy('name')->paginate(
            perPage: $f['por_pagina'],
            pageName: 'pagina',
            page: $f['pagina'],
        );

        $rows = $pagina->getCollection()
            ->map(function (Product $p) use ($podeVerCusto, $podeVerPreco, $podeVerBom) {
                $defaultVar = $p->variations->firstWhere('name', 'DUMMY') ?? $p->variations->first();
                $price = (float) ($defaultVar->sell_price_inc_tax ?? 0);
                $cost  = (float) ($defaultVar->default_purchase_price ?? 0);

                $linha = [
                    'id'         => $p->id,
                    'sku'        => $p->sku,
                    'name'       => $p->name,
                    'cat'        => $p->category?->id ? (string) $p->category->id : null,
                    'cat_label'  => $p->category?->name,
                    'unit'       => $p->unit?->short_name ?? 'un',
                    'stockKind'  => $p->enable_stock ? 'estoque' : 'sob_demanda',
                    'stockQty'   => null, // TODO [CL]: somar variation_location_details.qty_available
                    'uses30'     => 0,    // TODO [CL]: agregação cached
                    'active'     => $p->is_inactive == 0,
                    'updated'    => $p->updated_at?->locale('pt_BR')->isoFormat('DD MMM'),
                ];

                // UC-PUNI-02 / UC-PUNI-01 — a chave só existe se o usuário puder ver o valor.
                if ($podeVerPreco) {
                    $linha['price'] = $price;
                }
                if ($podeVerCusto) {
                    $linha['cost'] = $cost;
                }
                // `margin` deriva dos DOIS. Entregá-la sabendo um deles entrega o outro por
                // dedução — é o mesmo vazamento com uma conta no meio.
                if ($podeVerCusto && $podeVerPreco) {
                    $linha['margin'] = $price > 0 ? round(($price - $cost) / $price, 4) : 0;
                }
                // UC-PUNI-04 — preventivo: hoje é literal 0 porque a composição não é servida.
                // A chave nasce gated pra que plugar a query (TODO [CL]: count(MfgRecipe::where(
                // 'variation_id', $defaultVar->id))) não vaze a estrutura de custo por descuido.
                if ($podeVerBom) {
                    $linha['bomCount'] = 0;
                }

                return $linha;
            })->all();

        return $this->produtosPaginados = [
            'rows' => $rows,
            'meta' => [
                'total'      => $pagina->total(),
                'pagina'     => $pagina->currentPage(),
                'ultima'     => $pagina->lastPage(),
                'por_pagina' => $pagina->perPage(),
                // `de`/`ate` vêm null quando a página está vazia (filtro sem resultado) — o
                // .tsx trata, e é o que alimenta o "Mostrando X–Y de N" do rodapé.
                'de'         => $pagina->firstItem(),
                'ate'        => $pagina->lastItem(),
                'opcoes'     => self::POR_PAGINA_OPCOES,
            ],
        ];
    }

    /**
     * DUPLICAÇÃO PROVISÓRIA de ProductController::buildProdutoIndexCategorias().
     *
     * `Category::withCount('products')` estourava BadMethodCallException: `App\Category`
     * não declara `products()` (os únicos métodos do arquivo são getActivitylogOptions,
     * catAndSubCategories, forDropdown, sub_categories, scopeOnlyParent). Como `categorias`
     * é closure do render inicial, a rota dava 500 em QUALQUER sub-tela.
     *
     * A contagem por leftJoin + COUNT já roda em produção na lista React de /products
     * (ProductController::buildProdutoIndexCategorias). O método lá é `protected`, então
     * duplico em vez de expor/mover — não encostar no controller que serve a Blade viva.
     *
     * ⚠️ `categories.` qualificado em TODA cláusula: o leftJoin traz `products`, que também
     * tem `business_id` — sem qualificar o MySQL responde "Column 'business_id' in where
     * clause is ambiguous" (SQLSTATE 23000) e a tela volta a dar 500 por outro caminho.
     *
     * ⚠️ RESIDUAL declarado: a contagem NÃO escopa o lado `products` por business_id —
     * herdado do helper de produção. `category_id` é por business na prática, então não há
     * vazamento conhecido, mas não está defendido. Divergir aqui faria as duas cópias
     * driftarem; mudar a semântica da lista viva é decisão do dono.
     *
     * TODO: quando alguém extrair um Service/scope de categorias-com-contagem, apagar esta
     * cópia e consumir a fonte única. Enquanto forem duas, mudança numa exige a outra.
     */
    private function categorias(int $business_id): array
    {
        $cats = Category::where('categories.business_id', $business_id)
            ->where('categories.category_type', 'product')
            // Raiz em UltimatePOS é `parent_id = 0`, NUNCA NULL — a coluna é int(11) NOT NULL
            // e a convenção está declarada em três lugares independentes de App\Category:
            // catAndSubCategories() compara `== 0`, forDropdown() usa where('parent_id', 0),
            // scopeOnlyParent() idem. O `whereNull` que estava aqui não casava linha nenhuma:
            // mesmo sem o 500, a sub-tela Categorias voltava vazia com o banco cheio.
            ->where('categories.parent_id', 0)
            ->select('categories.id', 'categories.name', 'categories.slug')
            ->leftJoin('products', 'products.category_id', '=', 'categories.id')
            ->groupBy('categories.id', 'categories.name', 'categories.slug')
            ->selectRaw('COUNT(products.id) as count')
            ->orderBy('categories.name')
            ->get();

        return $cats->map(fn ($c) => [
            'id'    => (int) $c->id,
            'slug'  => $c->slug ?? str($c->name)->slug(),
            'label' => (string) $c->name,
            // `count` é alias do COUNT(), não coluna de `categories` — lido por getAttribute()
            // pra não introduzir "Access to an undefined property" novo. A mesma leitura em
            // ProductController::buildProdutoIndexCategorias() usa `$c->count` e só passa
            // por estar grandfathered no phpstan-baseline.neon; código novo não herda isenção.
            'count' => (int) $c->getAttribute('count'),
        ])->all();
    }

    /**
     * Insumos = produtos marcados como `not_for_selling = 1` no UltimatePOS,
     * ou produtos referenciados como ingredient em MfgRecipe. TODO [CL]: confirmar
     * convenção do oimpresso com Wagner.
     */
    private function insumos(int $business_id, bool $podeVerCusto): array
    {
        return Product::where('business_id', $business_id)
            ->where('not_for_selling', 1)
            ->with('unit:id,short_name')
            ->orderBy('name')->limit(200)->get()
            ->map(function ($p) use ($podeVerCusto) {
                $linha = [
                    'id'         => $p->id,
                    'name'       => $p->name,
                    'unit'       => $p->unit?->short_name ?? 'un',
                    'stock'      => 0,   // TODO: variation_location_details
                    'fornecedor' => null, // TODO: contact_supplier no UltimatePOS
                ];
                // UC-PUNI-01 — hoje é literal 0.0, mas o TODO abaixo vai plugar o custo real
                // (variation default_purchase_price): a chave já nasce atrás do mesmo gate da
                // coluna Custo do catálogo, senão o insumo vira a terceira porta lateral.
                if ($podeVerCusto) {
                    $linha['cost'] = 0.0; // TODO: variation default_purchase_price
                }

                return $linha;
            })->all();
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
    private function historico(int $business_id, bool $podeVerPreco): array
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
            ->map(function ($r) use ($podeVerPreco) {
                $linha = [
                    'os'       => $r->os,
                    'date'     => substr($r->date, 0, 10),
                    'prodId'   => $r->prod_id,
                    'prodName' => $r->prod_name,
                    'cat'      => $r->cat,
                    'unit'     => 'un',
                    'client'   => $r->client,
                    'qty'      => (float) $r->qty,
                ];
                // UC-PUNI-02b — `value` é qty × unit_price_inc_tax, ou seja preço de venda por
                // outro caminho. Gatear a lista e deixar o histórico aberto entrega o mesmo dado
                // produto a produto. `unit_price` cru nunca chega ao payload (só o produto dele).
                if ($podeVerPreco) {
                    $linha['value'] = (float) $r->qty * (float) $r->unit_price;
                }

                return $linha;
            })->all();
    }
}
