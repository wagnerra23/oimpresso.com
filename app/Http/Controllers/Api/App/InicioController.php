<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\User;
use App\Utils\ModuleUtil;
use App\Utils\ProductUtil;
use App\Utils\TransactionUtil;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Início do app das lojas (oimpresso-app). Contrato: memory/requisitos/AppMobile/API-CONTRATO-v1.md §6.
 * Só leitura. Cada bloco respeita a permissão da tela web equivalente e vem `null` sem ela.
 *
 * - faturado_hoje: TransactionUtil::getSellTotals (o mesmo do painel web), só com `dashboard.data`.
 * - meta_dia: D11 ([W]) = meta MENSAL de faturamento da Jana ÷ dias úteis do mês, `derivada: true`.
 * - kpis.pedidos_*: PedidosController::contadoresPara (mesmas regras da aba Pedidos).
 * - kpis.estoque_baixo: ProductUtil::getProductAlert, só com `stock_report.view`.
 * - financeiro: Financeiro\UnificadoService::kpis, só com acesso ao Financeiro.
 * - proximas_tarefas: TarefasController::proximasPara (3).
 * - nao_lidas: NotificacoesController::naoLidas (o ponto no sino; §6.1).
 *
 * Tier 0 (ADR 0093): business_id do usuário do token em tudo.
 */
class InicioController extends Controller
{
    public function __construct(
        private TransactionUtil $transactionUtil,
        private ProductUtil $productUtil,
        private ModuleUtil $moduleUtil,
    ) {
    }

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $bizId = (int) $user->business_id;
        $pedidos = app(PedidosController::class)->contadoresPara($user);

        return response()->json([
            'usuario' => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: (string) $user->username,
            'empresa' => (string) DB::table('business')->where('id', $bizId)->value('name'),
            'faturado_hoje' => $this->faturado($user, $bizId),
            'meta_dia' => $user->can('dashboard.data') ? $this->metaDia($bizId) : null,
            'kpis' => [
                'pedidos_ativos' => $pedidos['ativos'] ?? null,
                'pedidos_atrasados' => $pedidos['atrasados'] ?? null,
                'estoque_baixo' => $user->can('stock_report.view') ? $this->estoqueBaixo($user, $bizId) : null,
            ],
            'financeiro' => $this->financeiro($user, $bizId),
            'proximas_tarefas' => app(TarefasController::class)->proximasPara($user, 3),
            'nao_lidas' => app(NotificacoesController::class)->naoLidas($user),
        ] + $this->perfil($user));
    }

    /**
     * Áreas que o app mostra e onde ele abre (D6 [W]: "perfil colaborador abre no ponto").
     * Cada área segue a MESMA regra de acesso da rota dela, então aba visível = rota que responde.
     * perfil 'erp' = tem alguma área do ERP; senão 'colaborador' (só o ponto).
     *
     * @return array{perfil: string, abre_em: string, areas: list<string>}
     */
    private function perfil(User $user): array
    {
        $tarefas = app(TarefasController::class)->podeVerTarefas($user);
        $vendas = app(PedidosController::class)->podeVerVendas($user);
        $pessoas = app(PessoasController::class)->podeVerPessoas($user);
        $ponto = DB::table('ponto_colaborador_config')
            ->where('business_id', (int) $user->business_id)
            ->where('user_id', (int) $user->id)
            ->where('controla_ponto', true)
            ->exists();
        $erp = $tarefas || $vendas || $pessoas;

        $areas = array_keys(array_filter([
            'inicio' => $erp,
            'tarefas' => $tarefas,
            'pedidos' => $vendas,
            'producao' => $vendas,
            'pessoas' => $pessoas,
            'ponto' => $ponto,
            'mais' => true,
        ]));

        return [
            'perfil' => $erp ? 'erp' : 'colaborador',
            'abre_em' => $erp ? 'inicio' : ($ponto ? 'ponto' : 'mais'),
            'areas' => $areas,
        ];
    }

    /**
     * Quantos itens estão abaixo do mínimo. `getProductAlert` devolve um Builder (o docblock dele
     * diz array e está errado — ver GradesDoPainelService::estoqueMinimo), agrupado por
     * variation_location_details.id; `getCountForPagination` conta as linhas do agrupamento.
     */
    private function estoqueBaixo(User $user, int $bizId): int
    {
        /** @var Builder $q */
        $q = $this->productUtil->getProductAlert($bizId, $user->permitted_locations($bizId));

        return (int) $q->toBase()->getCountForPagination();
    }

    /** @return array{valor: float, ontem: float, variacao_pct: float|null}|null */
    private function faturado(User $user, int $bizId): ?array
    {
        if (! $user->can('dashboard.data')) {
            return null;
        }
        $locais = $user->permitted_locations($bizId);
        $hoje = Carbon::today()->toDateString();
        $ontem = Carbon::yesterday()->toDateString();

        $vHoje = (float) ($this->transactionUtil->getSellTotals($bizId, $hoje, $hoje, null, null, $locais)['total_sell_inc_tax'] ?? 0);
        $vOntem = (float) ($this->transactionUtil->getSellTotals($bizId, $ontem, $ontem, null, null, $locais)['total_sell_inc_tax'] ?? 0);

        return [
            'valor' => round($vHoje, 2),
            'ontem' => round($vOntem, 2),
            'variacao_pct' => $vOntem > 0 ? round(($vHoje - $vOntem) / $vOntem * 100, 1) : null,
        ];
    }

    /**
     * Meta do dia derivada (D11): alvo do período MENSAL vigente de uma meta ativa de faturamento
     * (slug começando com "faturamento") do business ÷ dias úteis (seg–sex) do mês. Sem meta
     * mensal vigente → null (não inventa número).
     *
     * @return array{valor: float, derivada: true}|null
     */
    private function metaDia(int $bizId): ?array
    {
        $hoje = Carbon::today();

        $alvo = DB::table('jana_meta_periodos as p')
            ->join('jana_metas as m', 'm.id', '=', 'p.meta_id')
            ->where('m.business_id', $bizId)
            ->where('m.ativo', true)
            ->where('m.slug', 'like', 'faturamento%')
            ->where('p.tipo_periodo', 'mes')
            ->whereDate('p.data_ini', '<=', $hoje->toDateString())
            ->whereDate('p.data_fim', '>=', $hoje->toDateString())
            ->orderByDesc('p.id')
            ->value('p.valor_alvo');

        if ($alvo === null) {
            return null;
        }

        $uteis = 0;
        for ($d = $hoje->copy()->startOfMonth(); $d->lte($hoje->copy()->endOfMonth()); $d->addDay()) {
            if (! $d->isWeekend()) {
                $uteis++;
            }
        }

        return ['valor' => round((float) $alvo / max($uteis, 1), 2), 'derivada' => true];
    }

    /** @return array{a_receber: float, a_pagar: float}|null */
    private function financeiro(User $user, int $bizId): ?array
    {
        $temModulo = $user->can('superadmin')
            || $this->moduleUtil->hasThePermissionInSubscription($bizId, 'financeiro_module');
        if (! $temModulo || ! ($user->can('superadmin') || $user->can('financeiro.access'))) {
            return null;
        }
        if (! class_exists(\Modules\Financeiro\Services\UnificadoService::class)) {
            return null;
        }

        $k = app(\Modules\Financeiro\Services\UnificadoService::class)->kpis($bizId);

        return [
            'a_receber' => round((float) $k['total_receber'], 2),
            'a_pagar' => round((float) $k['total_pagar'], 2),
        ];
    }
}
