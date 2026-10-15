<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\Services\AppLojas\AjustesDaEmpresa;
use App\User;
use App\Utils\ProductUtil;
use App\Utils\TransactionUtil;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Relatórios do app das lojas (tela 13). Contrato: memory/requisitos/AppMobile/API-CONTRATO-v1.md §10.3.
 * Só leitura. Cada bloco segue a permissão da tela web dele e vem `null` sem ela; só o bloco da aba
 * pedida vem preenchido (os outros vêm `null` também, para a resposta ficar leve).
 *
 * - kpis + dre: números do Financeiro → regra do Financeiro (FinanceiroController::podeVerFinanceiro).
 *   Base = títulos a receber/pagar NÃO cancelados por competência no período — a mesma base do DRE
 *   web (DreService: SUM(valor_total) por competencia_mes, status != cancelado).
 * - vendas: `dashboard.data` (a do faturado do Início); mesmos filtros de TransactionUtil::getSellTotals
 *   (venda final, locais permitidos), agrupados por dia/cliente numa consulta só.
 * - producao: a regra e os totais de GET /api/app/producao (PedidosController::producao).
 * - estoque: `stock_report.view`; ProductUtil::getProductAlert (o mesmo do Início/painel web).
 * - lucratividade (P6, api/tela-13-lucratividade.md): `dashboard.data`; os números saem de
 *   TransactionUtil::getProfitLossDetails — a MESMA montagem do Lucro/Prejuízo web (ReportController::dadosDeLucro).
 * - comissoes (P6): comissão do UltimatePOS (`users.cmmsn_percent` × base `pos_settings.cmmsn_calculation_type`)
 *   com os filtros de TransactionUtil::getTotalSellCommission / getTotalPaymentWithCommission (relatório
 *   Comissão por vendedor do web), agrupados por vendedor numa consulta só. Quem vê: escopoComissoes().
 *
 * Tier 0 (ADR 0093): business do token em toda consulta.
 */
class RelatoriosController extends Controller
{
    public const PERIODOS = ['mes', 'trimestre', 'ano'];

    public const ABAS = ['dre', 'vendas', 'producao', 'estoque', 'lucratividade', 'comissoes'];

    private const MENORES_MARGENS = 5;

    private const DIAS_SERIE = 14;

    private const TOP_CLIENTES = 5;

    private const ESTOQUE_MAX = 20;

    public function __construct(private ProductUtil $productUtil, private TransactionUtil $transactionUtil)
    {
    }

    /** @return array{dre: bool, vendas: bool, producao: bool, estoque: bool, lucratividade: bool, comissoes: bool} */
    public function blocosVisiveis(User $user): array
    {
        return [
            'dre' => app(FinanceiroController::class)->podeVerFinanceiro($user),
            'vendas' => $user->can('dashboard.data'),
            'producao' => app(PedidosController::class)->podeVerVendas($user),
            'estoque' => $user->can('stock_report.view'),
            'lucratividade' => $user->can('dashboard.data'),
            'comissoes' => $this->escopoComissoes($user) !== null,
        ];
    }

    /**
     * Quem vê a aba comissões: `todos` com `commission_agent.view` (ou `.manage` — a mesma regra da lista web,
     * SalesCommissionAgentController); `propria` para o vendedor com percentual de comissão (ou agente de
     * comissão), se a empresa deixa (`app_comissao_vendedor_ve_propria`, padrão true); senão `null`.
     */
    public function escopoComissoes(User $user): ?string
    {
        if ($user->can('commission_agent.view') || $user->can('commission_agent.manage')) {
            return 'todos';
        }
        if (! AjustesDaEmpresa::valor((int) $user->business_id, 'app_comissao_vendedor_ve_propria')) {
            return null;
        }

        return ((int) $user->is_cmmsn_agnt === 1 || (float) $user->cmmsn_percent > 0) ? 'propria' : null;
    }

    /** A regra da área `relatorios` do Início (§6): algum bloco visível. */
    public function podeVerRelatorios(User $user): bool
    {
        return in_array(true, $this->blocosVisiveis($user), true);
    }

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $blocos = $this->blocosVisiveis($user);
        if (! in_array(true, $blocos, true)) {
            return response()->json(['erro' => 'sem_permissao', 'mensagem' => 'Seu usuário não tem acesso aos relatórios.'], 403);
        }

        $periodo = (string) $request->query('periodo', 'mes');
        if (! in_array($periodo, self::PERIODOS, true)) {
            $periodo = 'mes';
        }
        $aba = (string) $request->query('aba', 'dre');
        if (! in_array($aba, self::ABAS, true)) {
            $aba = 'dre';
        }

        $hoje = CarbonImmutable::today();
        [$de, $ate] = match ($periodo) {
            'trimestre' => [$hoje->firstOfQuarter(), $hoje->lastOfQuarter()],
            'ano' => [$hoje->startOfYear(), $hoje->endOfYear()->startOfDay()],
            default => [$hoje->startOfMonth(), $hoje->endOfMonth()->startOfDay()],
        };
        $bizId = (int) $user->business_id;

        return response()->json([
            'periodo' => ['de' => $de->toDateString(), 'ate' => $ate->toDateString()],
            'kpis' => $blocos['dre'] ? $this->kpis($bizId, $de, $ate) : null,
            'dre' => $aba === 'dre' && $blocos['dre'] ? $this->dre($bizId, $de, $ate) : null,
            'vendas' => $aba === 'vendas' && $blocos['vendas'] ? $this->vendas($user, $bizId, $de, $ate, $hoje) : null,
            'producao' => $aba === 'producao' && $blocos['producao'] ? $this->producao($request) : null,
            'estoque' => $aba === 'estoque' && $blocos['estoque'] ? $this->estoque($user, $bizId) : null,
            'lucratividade' => $aba === 'lucratividade' && $blocos['lucratividade'] ? $this->lucratividade($user, $bizId, $de, $ate) : null,
            'comissoes' => $aba === 'comissoes' && $blocos['comissoes'] ? $this->comissoes($user, $bizId, $de, $ate) : null,
        ]);
    }

    /** Títulos não cancelados com competência no período, por tipo e categoria. */
    private function porCategoria(int $bizId, CarbonImmutable $de, CarbonImmutable $ate)
    {
        return DB::table('fin_titulos as t')
            ->leftJoin('fin_categorias as cat', function ($j) use ($bizId) {
                $j->on('cat.id', '=', 't.categoria_id')->where('cat.business_id', '=', $bizId);
            })
            ->leftJoin('fin_planos_conta as pc', function ($j) use ($bizId) {
                $j->on('pc.id', '=', 't.plano_conta_id')->where('pc.business_id', '=', $bizId);
            })
            ->where('t.business_id', $bizId)
            ->whereNull('t.deleted_at')
            ->where('t.status', '!=', 'cancelado')
            ->whereBetween('t.competencia_mes', [$de->format('Y-m'), $ate->format('Y-m')])
            ->groupBy('t.tipo', DB::raw("COALESCE(cat.nome, pc.nome, 'Sem categoria')"))
            ->selectRaw("t.tipo as tipo, COALESCE(cat.nome, pc.nome, 'Sem categoria') as nome, SUM(t.valor_total) as valor")
            ->get();
    }

    /** @return array{receitas: float, despesas: float, saldo: float, margem_pct: float|null} */
    private function kpis(int $bizId, CarbonImmutable $de, CarbonImmutable $ate): array
    {
        $tot = DB::table('fin_titulos')
            ->where('business_id', $bizId)
            ->whereNull('deleted_at')
            ->where('status', '!=', 'cancelado')
            ->whereBetween('competencia_mes', [$de->format('Y-m'), $ate->format('Y-m')])
            ->groupBy('tipo')
            ->selectRaw('tipo, SUM(valor_total) as total')
            ->get()
            ->mapWithKeys(fn ($l) => [$l->tipo => $l->total]);
        $receitas = round((float) ($tot['receber'] ?? 0), 2);
        $despesas = round((float) ($tot['pagar'] ?? 0), 2);
        $saldo = round($receitas - $despesas, 2);

        return [
            'receitas' => $receitas,
            'despesas' => $despesas,
            'saldo' => $saldo,
            'margem_pct' => $receitas > 0 ? round($saldo / $receitas * 100, 1) : null,
        ];
    }

    /** @return array{receitas_por_categoria: list<array{nome: string, valor: float}>, despesas_por_categoria: list<array{nome: string, valor: float}>} */
    private function dre(int $bizId, CarbonImmutable $de, CarbonImmutable $ate): array
    {
        $linhas = $this->porCategoria($bizId, $de, $ate);
        $lista = fn (string $tipo) => $linhas->where('tipo', $tipo)
            ->map(fn ($l) => ['nome' => (string) $l->nome, 'valor' => round((float) $l->valor, 2)])
            ->sortByDesc('valor')->values()->all();

        return [
            'receitas_por_categoria' => $lista('receber'),
            'despesas_por_categoria' => $lista('pagar'),
        ];
    }

    /** Venda final nos locais permitidos — os filtros de TransactionUtil::getSellTotals. */
    private function vendasBase(User $user, int $bizId)
    {
        $q = DB::table('transactions as t')
            ->where('t.business_id', $bizId)
            ->where('t.type', 'sell')
            ->where('t.status', 'final');
        $locais = $user->permitted_locations($bizId);
        if (! empty($locais) && $locais !== 'all') {
            $q->whereIn('t.location_id', $locais);
        }

        return $q;
    }

    private function vendas(User $user, int $bizId, CarbonImmutable $de, CarbonImmutable $ate, CarbonImmutable $hoje): array
    {
        $ini = $hoje->subDays(self::DIAS_SERIE - 1);
        $porDia = $this->vendasBase($user, $bizId)
            ->whereDate('t.transaction_date', '>=', $ini->toDateString())
            ->whereDate('t.transaction_date', '<=', $hoje->toDateString())
            ->groupBy(DB::raw('DATE(t.transaction_date)'))
            ->selectRaw('DATE(t.transaction_date) as dia, SUM(t.final_total) as total')
            ->get()
            ->mapWithKeys(fn ($l) => [$l->dia => $l->total]);

        $serie = [];
        for ($d = $ini; $d->lte($hoje); $d = $d->addDay()) {
            $serie[] = ['data' => $d->toDateString(), 'valor' => round((float) ($porDia[$d->toDateString()] ?? 0), 2)];
        }

        $top = $this->vendasBase($user, $bizId)
            ->join('contacts as c', function ($j) use ($bizId) {
                $j->on('c.id', '=', 't.contact_id')->where('c.business_id', '=', $bizId);
            })
            ->whereDate('t.transaction_date', '>=', $de->toDateString())
            ->whereDate('t.transaction_date', '<=', $ate->toDateString())
            ->groupBy('c.id', 'c.name', 'c.supplier_business_name')
            ->selectRaw('c.name, c.supplier_business_name, SUM(t.final_total) as total')
            ->orderByDesc('total')
            ->limit(self::TOP_CLIENTES)
            ->get()
            ->map(fn ($c) => [
                'nome' => (string) ($c->name ?: $c->supplier_business_name ?: 'Cliente'),
                'valor' => round((float) $c->total, 2),
            ])->all();

        return ['receita_por_dia' => $serie, 'top_clientes' => $top];
    }

    /** Os totais por etapa de GET /api/app/producao — a mesma fila, sem os itens. */
    private function producao(Request $request): array
    {
        $colunas = app(PedidosController::class)->producao($request)->getData(true)['colunas'] ?? [];

        return [
            'por_etapa' => array_map(fn ($c) => ['rotulo' => (string) $c['rotulo'], 'total' => (int) $c['total']], $colunas),
        ];
    }

    private function estoque(User $user, int $bizId): array
    {
        /** @var EloquentBuilder $q */
        $q = $this->productUtil->getProductAlert($bizId, $user->permitted_locations($bizId));
        $q->addSelect('p.alert_quantity');

        return [
            'baixo' => $q->toBase()->limit(self::ESTOQUE_MAX)->get()->map(fn ($p) => [
                'nome' => $p->type === 'variable'
                    ? trim($p->product . ' — ' . trim(($p->product_variation ?? '') . ' ' . ($p->variation ?? '')))
                    : (string) $p->product,
                'quantidade' => round((float) $p->stock, 2),
                'minimo' => round((float) $p->alert_quantity, 2),
                'unidade' => $p->unit ? (string) $p->unit : null,
            ])->values()->all(),
        ];
    }

    /**
     * Lucratividade do período = o Lucro/Prejuízo web (getProfitLossDetails, locais do usuário):
     * receita = "Total de vendas (sem imposto e desconto)"; margem_contribuicao = "Lucro bruto";
     * custo_variavel = receita − margem (o custo da mercadoria que fecha a conta); custo_fixo = "Total de
     * despesas"; ponto_equilibrio = custo_fixo ÷ (margem ÷ receita).
     */
    private function lucratividade(User $user, int $bizId, CarbonImmutable $de, CarbonImmutable $ate): array
    {
        $locais = $user->permitted_locations($bizId);
        $d = $this->transactionUtil->getProfitLossDetails($bizId, null, $de->toDateString(), $ate->toDateString(), null, $locais);
        $receita = round((float) ($d['total_sell'] ?? 0), 2);
        $margem = round((float) ($d['gross_profit'] ?? 0), 2);
        $fixo = round((float) ($d['total_expense'] ?? 0), 2);

        return [
            'receita' => $receita,
            'custo_variavel' => round($receita - $margem, 2),
            'custo_fixo' => $fixo,
            'margem_contribuicao' => $margem,
            'margem_pct' => $receita > 0 ? round($margem / $receita * 100, 1) : null,
            'ponto_equilibrio' => ($receita > 0 && $margem > 0) ? round($fixo / ($margem / $receita), 2) : null,
            'menores_margens' => $this->menoresMargens($bizId, $de, $ate, $locais),
        ];
    }

    /**
     * Vendas do período com a menor margem (lucro ÷ receita com imposto, a base do lucro bruto).
     *
     * @param  'all'|list<int>  $locais
     * @return list<array{pedido_id: int, numero: string, resumo: string|null, valor: float, margem_pct: float}>
     */
    private function menoresMargens(int $bizId, CarbonImmutable $de, CarbonImmutable $ate, string|array $locais): array
    {
        // Subconsulta: o MySQL não aceita o alias de um SUM numa expressão do ORDER BY.
        return DB::query()->fromSub($this->lucroPorVenda($bizId, $de, $ate, $locais), 'v')
            ->where('v.receita', '>', 0)
            ->orderByRaw('v.lucro / v.receita')
            ->orderBy('v.id')
            ->limit(self::MENORES_MARGENS)
            ->get()
            ->map(fn ($l) => [
                'pedido_id' => (int) $l->id,
                'numero' => (string) ($l->invoice_no ?: $l->id),
                'resumo' => $l->resumo !== null ? (string) $l->resumo : null,
                'valor' => round((float) $l->final_total, 2),
                'margem_pct' => round((float) $l->lucro / (float) $l->receita * 100, 1),
            ])->values()->all();
    }

    /**
     * Lucro e receita por venda. O lucro usa a MESMA expressão e os mesmos filtros de
     * TransactionUtil::getGrossProfit (combo, item sem estoque, lote de compra), agrupada por venda —
     * o teste de contrato confere que a soma por venda bate com getGrossProfit.
     *
     * @param  'all'|list<int>  $locais
     */
    public function lucroPorVenda(int $bizId, CarbonImmutable $de, CarbonImmutable $ate, string|array $locais): \Illuminate\Database\Query\Builder
    {
        $q = DB::table('transaction_sell_lines')
            ->join('transactions as sale', 'transaction_sell_lines.transaction_id', '=', 'sale.id')
            ->leftJoin('transaction_sell_lines_purchase_lines as TSPL', 'transaction_sell_lines.id', '=', 'TSPL.sell_line_id')
            ->leftJoin('purchase_lines as PL', 'TSPL.purchase_line_id', '=', 'PL.id')
            ->join('products as P', 'transaction_sell_lines.product_id', '=', 'P.id')
            ->where('sale.type', 'sell')
            ->where('sale.status', 'final')
            ->where('sale.business_id', $bizId)
            ->where('transaction_sell_lines.children_type', '!=', 'combo')
            ->whereDate('sale.transaction_date', '>=', $de->toDateString())
            ->whereDate('sale.transaction_date', '<=', $ate->toDateString());
        // Como getGrossProfit: lista vazia de locais não filtra (o comportamento do Lucro/Prejuízo web).
        if (! empty($locais) && $locais !== 'all') {
            $q->whereIn('sale.location_id', $locais);
        }

        return $q->groupBy('sale.id', 'sale.invoice_no', 'sale.final_total')
            ->select('sale.id', 'sale.invoice_no', 'sale.final_total')
            ->selectRaw('SUM(IF (TSPL.id IS NULL AND P.type="combo", ('
                . ' SELECT Sum((tspl2.quantity - tspl2.qty_returned) * (tsl.unit_price_inc_tax - pl2.purchase_price_inc_tax)) AS total'
                . ' FROM transaction_sell_lines AS tsl'
                . ' JOIN transaction_sell_lines_purchase_lines AS tspl2 ON tsl.id=tspl2.sell_line_id'
                . ' JOIN purchase_lines AS pl2 ON tspl2.purchase_line_id = pl2.id'
                . ' WHERE tsl.parent_sell_line_id = transaction_sell_lines.id),'
                . ' IF(P.enable_stock=0,(transaction_sell_lines.quantity - transaction_sell_lines.quantity_returned) * transaction_sell_lines.unit_price_inc_tax,'
                . ' (TSPL.quantity - TSPL.qty_returned) * (transaction_sell_lines.unit_price_inc_tax - PL.purchase_price_inc_tax)) )) as lucro')
            // Receita: cada linha uma vez (o join com os lotes de compra repete a linha).
            ->selectRaw('(SELECT SUM((l.quantity - l.quantity_returned) * l.unit_price_inc_tax) FROM transaction_sell_lines l'
                . " WHERE l.transaction_id = sale.id AND l.children_type != 'combo') as receita")
            ->selectRaw('(SELECT p.name FROM transaction_sell_lines tsl JOIN products p ON p.id = tsl.product_id'
                . ' WHERE tsl.transaction_id = sale.id AND tsl.parent_sell_line_id IS NULL ORDER BY tsl.id LIMIT 1) as resumo');
    }

    /**
     * Comissão por vendedor no período — o relatório Comissão por vendedor do web
     * (ReportController::getSalesRepresentativeTotalCommission) para todos de uma vez:
     * vendido = Σ (qtd − devolvida) × preço sem imposto das vendas finais (data da venda);
     * recebido = Σ pagamentos dessas vendas pela data do pagamento (troco negativo);
     * comissao = cmmsn_percent × (recebido se a base é `payment_received`, senão vendido) ÷ 100;
     * a_liberar = cmmsn_percent × saldo em aberto das vendas do período ÷ 100 (0 com `invoice_value`).
     * Locais = os do usuário, como no web (sem local permitido, nada soma).
     */
    private function comissoes(User $user, int $bizId, CarbonImmutable $de, CarbonImmutable $ate): array
    {
        $escopo = $this->escopoComissoes($user);
        $pos = json_decode((string) DB::table('business')->where('id', $bizId)->value('pos_settings'), true);
        $base = (is_array($pos) ? ($pos['cmmsn_calculation_type'] ?? '') : '') === 'payment_received' ? 'payment_received' : 'invoice_value';
        $locais = $user->permitted_locations($bizId);
        $periodo = [$de->toDateString(), $ate->toDateString()];

        $vendas = function () use ($bizId, $locais, $escopo, $user) {
            $q = DB::table('transactions as t')
                ->where('t.business_id', $bizId)
                ->where('t.type', 'sell')
                ->where('t.status', 'final')
                ->whereNotNull('t.commission_agent');
            if ($locais !== 'all') {
                $q->whereIn('t.location_id', $locais ?: [0]);
            }
            if ($escopo === 'propria') {
                $q->where('t.commission_agent', (int) $user->id);
            }

            return $q;
        };

        $vendido = $vendas()
            ->join('transaction_sell_lines as tsl', 'tsl.transaction_id', '=', 't.id')
            ->whereBetween(DB::raw('date(t.transaction_date)'), $periodo)
            ->groupBy('t.commission_agent')
            ->selectRaw('t.commission_agent as agente, SUM((tsl.quantity - tsl.quantity_returned) * tsl.unit_price) as total')
            ->pluck('total', 'agente');
        $recebido = $vendas()
            ->join('transaction_payments as tp', 'tp.transaction_id', '=', 't.id')
            ->whereBetween(DB::raw('date(tp.paid_on)'), $periodo)
            ->groupBy('t.commission_agent')
            ->selectRaw('t.commission_agent as agente, SUM(IF(tp.is_return = 0, tp.amount, tp.amount * -1)) as total')
            ->pluck('total', 'agente');
        $aberto = $base === 'payment_received'
            ? $vendas()
                ->whereBetween(DB::raw('date(t.transaction_date)'), $periodo)
                ->groupBy('t.commission_agent')
                ->selectRaw('t.commission_agent as agente, SUM(GREATEST(t.final_total - COALESCE((SELECT SUM(IF(tp.is_return = 0, tp.amount, tp.amount * -1))'
                    . ' FROM transaction_payments tp WHERE tp.transaction_id = t.id), 0), 0)) as total')
                ->pluck('total', 'agente')
            : collect();

        $ids = $vendido->keys()->merge($recebido->keys())->map(fn ($id) => (int) $id)->unique()->values()->all();
        $usuarios = User::where('business_id', $bizId)
            ->when(
                $escopo === 'propria',
                fn ($q) => $q->where('id', (int) $user->id),
                fn ($q) => $q->where(fn ($w) => $w->whereIn('id', $ids)->orWhere('is_cmmsn_agnt', 1)->orWhere('cmmsn_percent', '>', 0)),
            )
            ->get(['id', 'surname', 'first_name', 'last_name', 'cmmsn_percent']);

        $linhas = $usuarios->map(function (User $u) use ($vendido, $recebido, $aberto, $base) {
            $pct = (float) $u->cmmsn_percent;
            $v = round((float) ($vendido[$u->id] ?? 0), 2);
            $r = round((float) ($recebido[$u->id] ?? 0), 2);

            return [
                'id' => (int) $u->id,
                'nome' => trim(implode(' ', array_filter([$u->surname, $u->first_name, $u->last_name]))),
                'percentual' => $pct,
                'vendido' => $v,
                'recebido' => $r,
                'comissao' => round($pct * ($base === 'payment_received' ? $r : $v) / 100, 2),
                'a_liberar' => $base === 'payment_received' ? round($pct * (float) ($aberto[$u->id] ?? 0) / 100, 2) : 0.0,
            ];
        })->sortBy([['comissao', 'desc'], ['nome', 'asc']])->values()->all();

        return ['base' => $base, 'vendedores' => $linhas];
    }
}
