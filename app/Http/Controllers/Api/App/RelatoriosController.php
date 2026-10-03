<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\User;
use App\Utils\ProductUtil;
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
 *
 * Tier 0 (ADR 0093): business do token em toda consulta.
 */
class RelatoriosController extends Controller
{
    public const PERIODOS = ['mes', 'trimestre', 'ano'];

    public const ABAS = ['dre', 'vendas', 'producao', 'estoque'];

    private const DIAS_SERIE = 14;

    private const TOP_CLIENTES = 5;

    private const ESTOQUE_MAX = 20;

    public function __construct(private ProductUtil $productUtil)
    {
    }

    /** @return array{dre: bool, vendas: bool, producao: bool, estoque: bool} */
    public function blocosVisiveis(User $user): array
    {
        return [
            'dre' => app(FinanceiroController::class)->podeVerFinanceiro($user),
            'vendas' => $user->can('dashboard.data'),
            'producao' => app(PedidosController::class)->podeVerVendas($user),
            'estoque' => $user->can('stock_report.view'),
        ];
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
}
