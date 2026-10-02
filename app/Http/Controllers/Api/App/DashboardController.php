<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\User;
use App\Utils\TransactionUtil;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard do app das lojas (tela 35). Contrato: memory/requisitos/AppMobile/API-CONTRATO-v1.md §10.4.
 * Só leitura. Endpoint e área exigem `dashboard.data` (a do faturado do Início); cada número vem do
 * mesmo dono que já o calcula em outra tela, para baterem:
 *
 * - faturamento_30d: TransactionUtil::getSellTotals (o painel web e o faturado do Início).
 * - meta_mes: InicioController::alvoMensal (a meta mensal da Jana que gera a meta do dia).
 * - pedidos_* / pedidos_por_dia: PedidosController (regras da lista de Pedidos); null sem vendas.
 * - producao_*: as colunas de GET /api/app/producao; null sem vendas.
 * - a_receber / vencido: UnificadoService::kpis + FinanceiroController::vencidoReceber (os da tela 06);
 *   null sem acesso ao Financeiro.
 *
 * Tier 0 (ADR 0093): business do token em tudo (os donos acima já filtram).
 */
class DashboardController extends Controller
{
    private const DIAS_PEDIDOS = 14;

    public function __construct(private TransactionUtil $transactionUtil)
    {
    }

    /** A regra da área `dashboard` do Início (§6). */
    public function podeVerDashboard(User $user): bool
    {
        return $user->can('dashboard.data');
    }

    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if (! $this->podeVerDashboard($user)) {
            return response()->json(['erro' => 'sem_permissao', 'mensagem' => 'Seu usuário não tem acesso ao painel.'], 403);
        }
        $bizId = (int) $user->business_id;
        $locais = $user->permitted_locations($bizId);
        $hoje = CarbonImmutable::today();

        $pedidos = app(PedidosController::class);
        $contadores = $pedidos->contadoresPara($user);
        $porDia = $pedidos->porDiaPara($user, self::DIAS_PEDIDOS);
        $colunas = $pedidos->podeVerVendas($user)
            ? collect($pedidos->producao($request)->getData(true)['colunas'] ?? [])->keyBy('id')
            : null;

        $financeiro = app(FinanceiroController::class);
        $fin = $financeiro->podeVerFinanceiro($user)
            ? app(\Modules\Financeiro\Services\UnificadoService::class)->kpis($bizId, $hoje)
            : null;

        return response()->json([
            'faturamento_30d' => $this->faturamento30d($bizId, $locais, $hoje),
            'kpis' => [
                'pedidos_ativos' => $contadores['ativos'] ?? null,
                'pedidos_novos' => $porDia === null ? null : (int) end($porDia)['total'],
                'producao_em_curso' => $colunas === null ? null : (int) ($colunas['in_production']['total'] ?? 0),
                'a_receber' => $fin === null ? null : round((float) $fin['total_receber'], 2),
                'vencido' => $fin === null ? null : $financeiro->vencidoReceber($bizId, $hoje),
            ],
            'pedidos_por_dia' => $porDia,
            'meta_mes' => $this->metaMes($bizId, $locais, $hoje),
            'producao_concluida' => $colunas === null ? null : [
                'concluidas' => (int) ($colunas['ready_for_invoice']['total'] ?? 0),
                'total' => (int) $colunas->sum('total'),
            ],
        ]);
    }

    private function vendido($bizId, $locais, CarbonImmutable $de, CarbonImmutable $ate): float
    {
        return (float) ($this->transactionUtil
            ->getSellTotals($bizId, $de->toDateString(), $ate->toDateString(), null, null, $locais)['total_sell_inc_tax'] ?? 0);
    }

    /** @return array{valor: float, variacao_pct: float|null, serie_semanal: list<float>} */
    private function faturamento30d(int $bizId, $locais, CarbonImmutable $hoje): array
    {
        $atual = $this->vendido($bizId, $locais, $hoje->subDays(29), $hoje);
        $anterior = $this->vendido($bizId, $locais, $hoje->subDays(59), $hoje->subDays(30));

        // Últimos 7 dias, dia a dia (antigo → hoje), com os filtros do getSellTotals.
        $ini = $hoje->subDays(6);
        $q = DB::table('transactions as t')
            ->where('t.business_id', $bizId)
            ->where('t.type', 'sell')
            ->where('t.status', 'final')
            ->whereDate('t.transaction_date', '>=', $ini->toDateString())
            ->whereDate('t.transaction_date', '<=', $hoje->toDateString());
        if (! empty($locais) && $locais !== 'all') {
            $q->whereIn('t.location_id', $locais);
        }
        $dia = $q->groupBy(DB::raw('DATE(t.transaction_date)'))
            ->selectRaw('DATE(t.transaction_date) as dia, SUM(t.final_total) as total')
            ->get()
            ->pluck('total', 'dia');
        $serie = [];
        for ($d = $ini; $d->lte($hoje); $d = $d->addDay()) {
            $serie[] = round((float) ($dia[$d->toDateString()] ?? 0), 2);
        }

        return [
            'valor' => round($atual, 2),
            'variacao_pct' => $anterior > 0 ? round(($atual - $anterior) / $anterior * 100, 1) : null,
            'serie_semanal' => $serie,
        ];
    }

    /** @return array{valor: float, realizado_pct: int}|null */
    private function metaMes(int $bizId, $locais, CarbonImmutable $hoje): ?array
    {
        $alvo = app(InicioController::class)->alvoMensal($bizId);
        if ($alvo === null || $alvo <= 0) {
            return null;
        }
        $mes = $this->vendido($bizId, $locais, $hoje->startOfMonth(), $hoje);

        return ['valor' => round($alvo, 2), 'realizado_pct' => (int) round($mes / $alvo * 100)];
    }
}
