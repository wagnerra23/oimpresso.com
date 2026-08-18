<?php

namespace App\Http\Controllers;

use App\Category;
use App\Product;
use App\SellingPriceGroup;          // UltimatePOS standard — tabelas de preço
use App\TransactionSellLine;        // histórico de uso (consumo de produto em vendas/OS)
use App\Utils\ModuleUtil;           // camada 1 (módulo no pacote do business)
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tela "Catálogo Unificado" (Cockpit V2) — módulo Produto.
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
 * ── O QUE A TELA É (handoff "Consulta de Produtos", 2026-08-18) ─────────────
 * Índice do catálogo em PARIDADE com a Consulta de Clientes (/contacts), que é a
 * golden master do padrão de índice: PageHeader → abas por TIPO → KPI-filtros →
 * busca em linha própria → filtros + contagem → cartão de tabela com rolagem
 * interna (sem rodapé de paginação) → drawer de detalhe.
 *
 * As abas recortam por TIPO (todos/produtos/serviços/matéria-prima/kits/inativos)
 * e trocam tabela, contagem E os KPIs — não são filtro decorativo. As 4 sub-telas
 * anteriores (categorias/insumos/tabelas/histórico) continuam servidas: saíram da
 * barra de abas e passaram pro menu de ações do cabeçalho.
 *
 * ── VISIBILIDADE (UC-PUNI-01..06 · Index.casos.md) ──────────────────────────
 * Esta tela reúne numa rota só o que as outras telas do Produto gateiam separadamente:
 * custo, preço de venda, tabelas de preço e composição. O contrato é:
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
 * O mesmo predicado governa o KPI: "Margem baixa" É uma leitura da estrutura de custo
 * (quantos itens estão sob o piso). Pra quem não pode ver custo, o card não é montado —
 * mesma regra da coluna, aplicada ao contador.
 */
class ProdutoUnificadoController extends Controller
{
    /**
     * Piso de margem — abaixo disso o item entra no KPI "Margem baixa" e a célula fica em
     * `--neg`. É PARÂMETRO DE NEGÓCIO, não constante de tela: o handoff §9 exige que a margem
     * seja calculada com o piso vigente, não com 42% fixo. Enquanto não existe a coluna de
     * configuração por business, o valor mora aqui e VIAJA como prop (`pisoMargem`) — o
     * frontend nunca o redeclara, então plugar a configuração depois é mudar uma linha.
     */
    private const PISO_MARGEM = 0.42;

    /** Janela de "sem venda" do KPI de item parado, em dias. */
    private const DIAS_PARADO = 90;

    /**
     * Teto de linhas por resposta. Não existe rodapé de paginação (D-06 da SPEC v1.0 — a
     * golden master não tem rodapé), então o corte é do servidor e a tela DECLARA quando
     * cortou (`totalDaAba` vs linhas recebidas). Volume real de catálogo é dívida aberta:
     * ADR 0402 (proposta) tem que escolher entre carga incremental, virtualização ou rodapé
     * canônico — nenhuma das três é decisão desta tela.
     */
    private const TETO_LINHAS = 500;

    /** Abas por tipo. A chave `todos` é o cadastro inteiro; `inativos` é o complemento. */
    private const ABAS = ['todos', 'produtos', 'servicos', 'materia', 'kits', 'inativos'];

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

        $business_id = (int) request()->session()->get('user.business_id');

        $podeVerCusto = (bool) auth()->user()->can('view_purchase_price');
        $podeVerPreco = (bool) auth()->user()->can('access_default_selling_price');
        $podeVerBom = $this->podeVerComposicao($business_id);

        $aba = $request->string('aba', 'produtos')->toString();
        if (! in_array($aba, self::ABAS, true)) {
            $aba = 'produtos';
        }

        $filters = [
            'tela' => $request->string('tela', 'produtos')->toString(),
            'aba' => $aba,
            'busca' => trim($request->string('busca', '')->toString()),
            // KPI-filtro: recorte operacional sobre a aba. `margem` é ignorado adiante
            // pra quem não pode ver custo — o card nem é montado nesse perfil.
            'kpi' => $request->string('kpi', '')->toString(),
            'categoria' => $request->integer('categoria') ?: null,
            'tipo' => $request->string('tipo', '')->toString(),
            'marca' => $request->integer('marca') ?: null,
            'estoque' => $request->string('estoque', '')->toString(),
            'margem' => $request->string('margem', '')->toString(),
        ];

        return Inertia::render('Produto/Unificado/Index', [
            'tela' => $filters['tela'],
            'filters' => $filters,
            'pisoMargem' => self::PISO_MARGEM,
            'diasParado' => self::DIAS_PARADO,
            'tetoLinhas' => self::TETO_LINHAS,
            // EAGER (não closure): são 3 booleanos já resolvidos acima — embrulhar em closure
            // não pouparia query nenhuma, que é o único ganho do defer/D-14.
            // O `only:[...]` dos partial reloads NÃO pede esta prop, e não precisa: o Inertia
            // MESCLA a resposta parcial com as props que a página já tem no cliente. Do outro
            // lado, o `.tsx` aplica default fail-closed — ausência nunca vira permissão.
            'permissoes' => [
                'custo' => $podeVerCusto,
                'preco' => $podeVerPreco,
                'composicao' => $podeVerBom,
            ],
            // `Inertia::defer` (D-14 · RUNBOOK-inertia-defer-pattern): toda prop com agregação
            // ou subquery sai do caminho crítico do primeiro paint e o partial reload escolhe
            // quais rodam. Trocar de aba pede `produtos`+`kpis`+`totalDaAba`; digitar na busca
            // pede os mesmos três; abrir uma sub-tela secundária pede só a lista dela.
            'abas' => Inertia::defer(fn () => $this->contagemDasAbas($business_id)),
            'kpis' => Inertia::defer(fn () => $this->kpis($business_id, $filters, $podeVerCusto, $podeVerPreco)),
            'produtos' => Inertia::defer(fn () => $this->produtos($business_id, $filters, $podeVerCusto, $podeVerPreco, $podeVerBom)),
            'totalDaAba' => Inertia::defer(fn () => $this->totalDaAba($business_id, $filters, $podeVerCusto, $podeVerPreco)),
            'opcoesFiltro' => Inertia::defer(fn () => $this->opcoesFiltro($business_id)),
            'categorias' => Inertia::defer(fn () => $this->categorias($business_id)),
            // UC-PUNI-04 / UC-PUNI-03: a sub-tela inteira não é servida sem o direito.
            // O gate é aqui (e não dentro do helper) pra que a prop nasça `[]` sem custo de query.
            'insumos' => $filters['tela'] === 'insumos' && $podeVerBom ? $this->insumos($business_id, $podeVerCusto) : [],
            'tabelas' => $filters['tela'] === 'tabelas' && $podeVerPreco ? $this->tabelas($business_id) : [],
            'historico' => $filters['tela'] === 'historico' ? $this->historico($business_id, $podeVerPreco) : [],
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

    // ───────── Catálogo: uma fonte, três leituras (linhas, KPIs, contagem das abas) ─────────

    /**
     * Subconsulta canônica do catálogo — a ÚNICA definição de "o que é uma linha do catálogo".
     * Linhas, KPIs e contagem das abas leem daqui; se divergissem, o contador discordaria da
     * lista e destruiria a confiança na tela (handoff §9).
     *
     * Os joins espelham `ProductController@index:78-91` (a lista viva): `variations` + left
     * `variation_location_details`, `type != 'modifier'`, variação não deletada. `business_id`
     * declarado explicitamente — `App\Product` não tem global scope de tenant (ADR 0093).
     *
     * ⚠️ `qtd` é `SUM(vld.qty_available)` e vem NULL quando o produto não tem nenhum registro
     * de estoque. Quem distingue "sem estoque" (0) de "não estocável" (null) é `enable_stock`,
     * aplicado na leitura — não o NULL do SUM.
     *
     * ⚠️ `ultima_venda` é subselect correlacionado (uma leitura por produto). Aceitável no
     * volume atual; quando o catálogo crescer, o caminho é materializar num job diário, como o
     * UltimatePOS faz com os agregados de venda.
     */
    private function catalogoSub(int $business_id): QueryBuilder
    {
        return DB::table('products as p')
            ->join('variations as v', 'v.product_id', '=', 'p.id')
            ->leftJoin('variation_location_details as vld', 'vld.variation_id', '=', 'v.id')
            ->leftJoin('categories as cat', 'cat.id', '=', 'p.category_id')
            ->leftJoin('units as u', 'u.id', '=', 'p.unit_id')
            ->whereNull('v.deleted_at')
            ->where('p.business_id', $business_id)
            ->where('p.type', '!=', 'modifier')
            ->groupBy(
                'p.id',
                'p.name',
                'p.sku',
                'p.is_inactive',
                'p.enable_stock',
                'p.not_for_selling',
                'p.type',
                'p.alert_quantity',
                'p.category_id',
                'cat.name',
                'p.brand_id',
                'u.short_name'
            )
            ->select([
                'p.id',
                'p.name as nome',
                'p.sku as referencia',
                'p.is_inactive',
                'p.enable_stock',
                'p.alert_quantity as minimo',
                'p.category_id',
                'p.brand_id',
                DB::raw('cat.name as categoria'),
                DB::raw("COALESCE(u.short_name, 'un') as unidade"),
                // Tipo do item — o UltimatePOS não tem coluna "tipo de item"; ele é derivado
                // de três colunas que já existem. A ORDEM das cláusulas é a regra:
                //   combo            → kit           (produto composto)
                //   not_for_selling  → matéria-prima (existe pra consumo, não pra venda)
                //   enable_stock = 0 → serviço       (não guarda saldo)
                //   resto            → produto
                DB::raw("CASE
                    WHEN p.type = 'combo' THEN 'kit'
                    WHEN p.not_for_selling = 1 THEN 'materia'
                    WHEN p.enable_stock = 0 THEN 'servico'
                    ELSE 'produto'
                END as tipo"),
                DB::raw('SUM(vld.qty_available) as qtd'),
                DB::raw('MIN(v.sell_price_inc_tax) as preco'),
                DB::raw('MIN(v.dpp_inc_tax) as custo'),
                DB::raw(
                    "(SELECT MAX(t.transaction_date)
                        FROM transaction_sell_lines tsl
                        JOIN transactions t ON t.id = tsl.transaction_id
                       WHERE tsl.product_id = p.id
                         AND t.business_id = ?
                         AND t.type = 'sell'
                         AND t.status = 'final') as ultima_venda"
                ),
            ])
            ->addBinding($business_id, 'select');
    }

    /**
     * Catálogo com a ABA aplicada — o recorte que os KPIs contam e a tabela lista.
     * A aba de tipo conta SÓ ativos; `inativos` é o complemento; `todos` é o cadastro inteiro.
     */
    private function catalogoDaAba(int $business_id, string $aba): QueryBuilder
    {
        $q = DB::query()->fromSub($this->catalogoSub($business_id), 'c');

        return match ($aba) {
            'produtos' => $q->where('c.is_inactive', 0)->where('c.tipo', 'produto'),
            'servicos' => $q->where('c.is_inactive', 0)->where('c.tipo', 'servico'),
            'materia' => $q->where('c.is_inactive', 0)->where('c.tipo', 'materia'),
            'kits' => $q->where('c.is_inactive', 0)->where('c.tipo', 'kit'),
            'inativos' => $q->where('c.is_inactive', 1),
            default => $q,
        };
    }

    /**
     * Busca, filtros da toolbar e KPI-filtro compostos num ÚNICO `where` — os três recortes se
     * combinam no servidor (handoff §9; era a pendência §14 item 4 do protótipo, onde eles não
     * se combinavam). `margem` só entra pra quem pode ver custo: sem o direito, o recorte por
     * margem devolveria a estrutura de custo por diferença de contagem.
     */
    private function aplicarRecortes(QueryBuilder $q, array $f, bool $podeVerCusto, bool $podeVerPreco): QueryBuilder
    {
        if ($f['busca'] !== '') {
            // Escopo da busca: descrição, código (id), referência (sku) e categoria — a
            // referência é pesquisável mesmo sem ocupar coluna na grade (SPEC D-09).
            $termo = '%' . $f['busca'] . '%';
            $q->where(function ($qq) use ($termo, $f) {
                $qq->where('c.nome', 'like', $termo)
                    ->orWhere('c.referencia', 'like', $termo)
                    ->orWhere('c.categoria', 'like', $termo);
                if (ctype_digit($f['busca'])) {
                    $qq->orWhere('c.id', (int) $f['busca']);
                }
            });
        }

        if ($f['categoria']) {
            $q->where('c.category_id', $f['categoria']);
        }
        if ($f['marca']) {
            $q->where('c.brand_id', $f['marca']);
        }
        if ($f['tipo'] !== '') {
            $q->where('c.tipo', $f['tipo']);
        }

        match ($f['estoque']) {
            'em' => $q->where('c.enable_stock', 1)->whereRaw('COALESCE(c.qtd, 0) > COALESCE(c.minimo, 0)'),
            'baixo' => $q->where('c.enable_stock', 1)->whereRaw('COALESCE(c.qtd, 0) > 0')->whereRaw('COALESCE(c.qtd, 0) <= COALESCE(c.minimo, 0)'),
            'sem' => $q->where('c.enable_stock', 1)->whereRaw('COALESCE(c.qtd, 0) = 0'),
            'nao' => $q->where('c.enable_stock', 0),
            default => null,
        };

        if ($podeVerCusto && $podeVerPreco && $f['margem'] !== '') {
            match ($f['margem']) {
                'sob_piso' => $q->whereRaw('c.preco > 0 AND (c.preco - c.custo) / c.preco < ?', [self::PISO_MARGEM]),
                'ok' => $q->whereRaw('c.preco > 0 AND (c.preco - c.custo) / c.preco >= ?', [self::PISO_MARGEM]),
                default => null,
            };
        }

        // KPI-filtro (toggle): recorte operacional sobre a aba já aplicada.
        $limite = now()->subDays(self::DIAS_PARADO)->toDateTimeString();
        match ($f['kpi']) {
            'ativos' => $q->where('c.is_inactive', 0),
            'min' => $q->where('c.enable_stock', 1)->whereRaw('COALESCE(c.qtd, 0) > 0')->whereRaw('COALESCE(c.qtd, 0) <= COALESCE(c.minimo, 0)'),
            'zero' => $q->where('c.enable_stock', 1)->whereRaw('COALESCE(c.qtd, 0) = 0'),
            'parado' => $q->where(fn ($qq) => $qq->whereNull('c.ultima_venda')->orWhere('c.ultima_venda', '<', $limite)),
            'margem' => $podeVerCusto && $podeVerPreco
                ? $q->whereRaw('c.preco > 0 AND (c.preco - c.custo) / c.preco < ?', [self::PISO_MARGEM])
                : $q,
            default => null,
        };

        return $q;
    }

    /**
     * Contagem por aba. `todos` é o cadastro inteiro (não a aba ativa) — é o número que o
     * subtítulo do cabeçalho usa como "N cadastrados".
     *
     * @return array<string,int>
     */
    private function contagemDasAbas(int $business_id): array
    {
        $linha = DB::query()->fromSub($this->catalogoSub($business_id), 'c')
            ->selectRaw('COUNT(*) as todos')
            ->selectRaw("SUM(CASE WHEN c.is_inactive = 0 AND c.tipo = 'produto' THEN 1 ELSE 0 END) as produtos")
            ->selectRaw("SUM(CASE WHEN c.is_inactive = 0 AND c.tipo = 'servico' THEN 1 ELSE 0 END) as servicos")
            ->selectRaw("SUM(CASE WHEN c.is_inactive = 0 AND c.tipo = 'materia' THEN 1 ELSE 0 END) as materia")
            ->selectRaw("SUM(CASE WHEN c.is_inactive = 0 AND c.tipo = 'kit' THEN 1 ELSE 0 END) as kits")
            ->selectRaw('SUM(CASE WHEN c.is_inactive = 1 THEN 1 ELSE 0 END) as inativos')
            ->first();

        return [
            'todos' => (int) ($linha->todos ?? 0),
            'produtos' => (int) ($linha->produtos ?? 0),
            'servicos' => (int) ($linha->servicos ?? 0),
            'materia' => (int) ($linha->materia ?? 0),
            'kits' => (int) ($linha->kits ?? 0),
            'inativos' => (int) ($linha->inativos ?? 0),
        ];
    }

    /**
     * Os seis KPI-filtros, contados SOBRE A ABA ATIVA (handoff §4.3) e pela MESMA subconsulta
     * da listagem. O card "Margem baixa" só é contado pra quem pode ver custo E preço — a
     * contagem é leitura da estrutura de custo, e o gate da coluna vale igual pro contador.
     *
     * @return array<string,int>
     */
    private function kpis(int $business_id, array $f, bool $podeVerCusto, bool $podeVerPreco): array
    {
        $limite = now()->subDays(self::DIAS_PARADO)->toDateTimeString();

        $q = $this->catalogoDaAba($business_id, $f['aba'])
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN c.is_inactive = 0 THEN 1 ELSE 0 END) as ativos')
            ->selectRaw('SUM(CASE WHEN c.enable_stock = 1 AND COALESCE(c.qtd, 0) > 0 AND COALESCE(c.qtd, 0) <= COALESCE(c.minimo, 0) THEN 1 ELSE 0 END) as baixo')
            ->selectRaw('SUM(CASE WHEN c.enable_stock = 1 AND COALESCE(c.qtd, 0) = 0 THEN 1 ELSE 0 END) as zero')
            ->selectRaw('SUM(CASE WHEN c.ultima_venda IS NULL OR c.ultima_venda < ? THEN 1 ELSE 0 END) as parado', [$limite]);

        if ($podeVerCusto && $podeVerPreco) {
            $q->selectRaw('SUM(CASE WHEN c.preco > 0 AND (c.preco - c.custo) / c.preco < ? THEN 1 ELSE 0 END) as margem', [self::PISO_MARGEM]);
        }

        $linha = $q->first();

        $kpis = [
            'ativos' => (int) ($linha->ativos ?? 0),
            'min' => (int) ($linha->baixo ?? 0),
            'zero' => (int) ($linha->zero ?? 0),
            'parado' => (int) ($linha->parado ?? 0),
            'total' => (int) ($linha->total ?? 0),
        ];

        // UC-PUNI-01 — a chave só existe se o usuário puder ver o valor. Sem custo não há
        // card de margem: mesma regra da coluna, aplicada ao contador.
        if ($podeVerCusto && $podeVerPreco) {
            $kpis['margem'] = (int) ($linha->margem ?? 0);
        }

        return $kpis;
    }

    /**
     * Quantas linhas o recorte inteiro tem — antes do teto. É o número que a tela imprime na
     * contagem à direita dos filtros e o que permite dizer "mostrando X de Y" quando cortou.
     * Sem ele, um catálogo de mil itens apareceria como 500 e a tela mentiria por omissão.
     */
    private function totalDaAba(int $business_id, array $f, bool $podeVerCusto, bool $podeVerPreco): int
    {
        return (int) $this->aplicarRecortes(
            $this->catalogoDaAba($business_id, $f['aba']),
            $f,
            $podeVerCusto,
            $podeVerPreco
        )->count();
    }

    /**
     * As linhas da tabela.
     *
     * `stockQty` distingue TRÊS estados que a tela precisa separar:
     *   - `null`  → não estocável (serviço; `enable_stock = 0`)
     *   - `0`     → sem estoque (bloqueia venda — dado `[V0]`)
     *   - `> 0`   → saldo real
     * Imprimir 0 pra um serviço afirmaria estoque zerado; imprimir "—" pra um item zerado
     * esconderia o bloqueio. Os dois erros já aconteceram nesta tela.
     */
    private function produtos(int $business_id, array $f, bool $podeVerCusto, bool $podeVerPreco, bool $podeVerBom): array
    {
        $limite = now()->subDays(self::DIAS_PARADO)->toDateTimeString();

        $linhas = $this->aplicarRecortes(
            $this->catalogoDaAba($business_id, $f['aba']),
            $f,
            $podeVerCusto,
            $podeVerPreco
        )->orderBy('c.nome')->limit(self::TETO_LINHAS)->get();

        return $linhas->map(function ($r) use ($podeVerCusto, $podeVerPreco, $podeVerBom, $limite) {
            $estocavel = (bool) $r->enable_stock;
            $preco = (float) ($r->preco ?? 0);
            $custo = (float) ($r->custo ?? 0);
            $ultimaVenda = $r->ultima_venda ? (string) $r->ultima_venda : null;

            $linha = [
                'id' => (int) $r->id,
                // "Código" é o identificador interno do cadastro; "Referência" é o SKU
                // cadastrado (SPEC exceção 9 — "Referência" substitui "SKU" no rótulo).
                'codigo' => (int) $r->id,
                'referencia' => $r->referencia,
                // `sku` mantido por compatibilidade com quem já lê esta prop (sub-telas e
                // testes de contrato) — mesmo valor de `referencia`, não um segundo dado.
                'sku' => $r->referencia,
                'name' => (string) $r->nome,
                'tipo' => (string) $r->tipo,
                'cat' => $r->category_id ? (string) $r->category_id : null,
                'cat_label' => $r->categoria,
                'unit' => (string) $r->unidade,
                'stockKind' => $estocavel ? 'estoque' : 'sob_demanda',
                'stockQty' => $estocavel ? (float) ($r->qtd ?? 0) : null,
                'minimo' => $estocavel && $r->minimo !== null ? (float) $r->minimo : null,
                'parado' => $ultimaVenda === null || $ultimaVenda < $limite,
                'ultimaVenda' => $ultimaVenda ? substr($ultimaVenda, 0, 10) : null,
                'active' => (int) $r->is_inactive === 0,
            ];

            // UC-PUNI-02 / UC-PUNI-01 — a chave só existe se o usuário puder ver o valor.
            if ($podeVerPreco) {
                $linha['price'] = $preco;
            }
            if ($podeVerCusto) {
                $linha['cost'] = $custo;
            }
            // `margin` deriva dos DOIS. Entregá-la sabendo um deles entrega o outro por
            // dedução — é o mesmo vazamento com uma conta no meio.
            if ($podeVerCusto && $podeVerPreco) {
                $linha['margin'] = $preco > 0 ? round(($preco - $custo) / $preco, 4) : 0;
            }
            // UC-PUNI-04 — preventivo: hoje é literal 0 porque a composição não é servida.
            // A chave nasce gated pra que plugar a query não vaze a estrutura de custo.
            if ($podeVerBom) {
                $linha['bomCount'] = 0;
            }

            return $linha;
        })->all();
    }

    /**
     * Opções dos gatilhos de filtro da toolbar.
     *
     * ⚠️ DESVIO DECLARADO do handoff (§4.5 lista Categoria · Tipo · Fornecedor · Estoque ·
     * Margem): o UltimatePOS não guarda fornecedor NO PRODUTO — fornecedor só existe por
     * COMPRA (`transactions.type = 'purchase'` → `contact_id`). Filtrar por ele exigiria
     * varrer o histórico de compras a cada consulta. No lugar entra **Marca** (`brands`),
     * que é o atributo que o produto de fato carrega. Diferença fora da lista aprovada do
     * §6 — precisa de aprovação antes de virar definitivo.
     *
     * @return array<string,list<array{value:string,label:string}>>
     */
    private function opcoesFiltro(int $business_id): array
    {
        $categorias = Category::where('categories.business_id', $business_id)
            ->where('categories.category_type', 'product')
            ->orderBy('categories.name')
            ->get(['categories.id', 'categories.name']);

        $marcas = DB::table('brands')
            ->where('business_id', $business_id)
            ->orderBy('name')
            ->get(['id', 'name']);

        return [
            'categorias' => $categorias->map(fn ($c) => ['value' => (string) $c->id, 'label' => (string) $c->name])->all(),
            'marcas' => $marcas->map(fn ($b) => ['value' => (string) $b->id, 'label' => (string) $b->name])->all(),
        ];
    }

    // ───────── Sub-telas secundárias (menu ⋯ do cabeçalho) ─────────

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
            'id' => (int) $c->id,
            'slug' => $c->slug ?? str($c->name)->slug(),
            'label' => (string) $c->name,
            // `count` é alias do COUNT(), não coluna de `categories` — lido por getAttribute()
            // pra não introduzir "Access to an undefined property" novo.
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
                    'id' => $p->id,
                    'name' => $p->name,
                    'unit' => $p->unit?->short_name ?? 'un',
                    'stock' => 0,   // TODO: variation_location_details
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
                'id' => (string) $g->id,
                'label' => $g->name,
                'desc' => $g->description ?? '',
                'mult' => 1.00, // TODO [CL]: ver decisão acima
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
                    'os' => $r->os,
                    'date' => substr($r->date, 0, 10),
                    'prodId' => $r->prod_id,
                    'prodName' => $r->prod_name,
                    'cat' => $r->cat,
                    'unit' => 'un',
                    'client' => $r->client,
                    'qty' => (float) $r->qty,
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
