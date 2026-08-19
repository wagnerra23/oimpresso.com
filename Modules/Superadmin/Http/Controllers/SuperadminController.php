<?php

namespace Modules\Superadmin\Http\Controllers;

use App\Business;
use App\Util\OtelHelper;
use Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Modules\Superadmin\Entities\Subscription;
use Modules\Superadmin\Services\SuperadminDashboardService;
use Illuminate\Routing\Controller;

class SuperadminController extends Controller
{
    /**
     * Visão geral da plataforma (`GET /superadmin`).
     *
     * O retorno deixou de ser `Illuminate\Http\Response` (view Blade) e passou a
     * `Inertia\Response` na SA-O1 — o docblock acompanha, senão o PHPStan acusa
     * `return.type` com razão.
     */
    public function index(Request $request, SuperadminDashboardService $dashboard): InertiaResponse
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        // SA-O1 (MWART F3): a home deixa de renderizar Blade/AdminLTE e passa a Inertia.
        // O span perdeu o sufixo `legacy` junto — ele descrevia a view, não a rota.
        //
        // Os números saem do SuperadminDashboardService, que já era o dono das leituras
        // desde a W18/W23. Até aqui o `index()` refazia a query de negócios-sem-assinatura
        // inline enquanto `countNotSubscribedBusinesses()` existia e fazia o mesmo — duas
        // fontes para o mesmo número drifam (UC-SADASH-02).
        //
        // Só entram props que o banco sustenta. MRR, funil trial→pago, churn e receita por
        // pacote NÃO têm query e ficam fora — renderizar o mock do protótipo em produção
        // seria fabricar número (UC-SADASH-05). Entram na SA-O1b.
        return OtelHelper::spanBiz('superadmin.dashboard.index', function () use ($request, $dashboard) {
            $periodo = $this->periodoValido($request->input('periodo'));
            $janela = $this->janelaDoPeriodo($periodo);

            return Inertia::render('superadmin/Dashboard/Index', [
                'periodo' => $periodo,
                'janela' => $janela,

                // Inertia::defer: cada uma é uma query. O partial reload do segmented pede
                // só `statsPeriodo` + `janela`, então as outras 3 não reexecutam.
                'statsPeriodo' => Inertia::defer(
                    fn () => $dashboard->statsForPeriod($janela['inicio'], $janela['fim'])
                ),
                'semAssinatura' => Inertia::defer(
                    fn () => $dashboard->countNotSubscribedBusinesses()
                ),
                'tendencia' => Inertia::defer(
                    fn () => $this->tendenciaPayload($dashboard)
                ),
                'recentes' => Inertia::defer(
                    fn () => $this->recentesPayload()
                ),
            ]);
        }, ['component' => 'superadmin.dashboard.index']);
    }

    /**
     * Períodos aceitos pelo segmented. Entrada desconhecida cai em `mes` — a tela nunca
     * fica sem janela, e não damos ao usuário um caminho pra montar intervalo arbitrário.
     */
    private function periodoValido(?string $periodo): string
    {
        return in_array($periodo, ['hoje', 'semana', 'mes', 'ano'], true) ? $periodo : 'mes';
    }

    /**
     * Janela ROLANTE do período (o F1 pede isso explícito na tela, não "mês corrente").
     *
     * @return array{inicio: string, fim: string, rotulo: string}
     */
    private function janelaDoPeriodo(string $periodo): array
    {
        $fim = Carbon::today();

        $inicio = match ($periodo) {
            'hoje' => $fim->copy(),
            'semana' => $fim->copy()->subDays(6),
            'ano' => $fim->copy()->subDays(364),
            default => $fim->copy()->subDays(29),
        };

        return [
            'inicio' => $inicio->toDateString(),
            'fim' => $fim->toDateString(),
            'rotulo' => 'Janela rolante — encerra em '.$fim->format('d/m/Y'),
        ];
    }

    /**
     * Tendência mensal (12 meses) no formato que o Chart do DS consome.
     *
     * @return array<int, array{label: string, value: float}>
     */
    private function tendenciaPayload(SuperadminDashboardService $dashboard): array
    {
        $serie = [];

        foreach ($dashboard->buildMonthlyRevenueChart() as $mes => $valor) {
            $serie[] = ['label' => $mes, 'value' => (float) $valor];
        }

        return $serie;
    }

    /**
     * Cadastros recentes — 5 linhas.
     *
     * SUPERADMIN: cross-tenant intencional (ADR 0093 §exceções) — a home enxerga TODOS os
     * negócios da plataforma. Aplicar escopo de tenant aqui quebraria o produto (UC-SADASH-04).
     *
     * @return array<int, array{id: int, nome: string, criado: string, assinatura: string}>
     */
    private function recentesPayload(): array
    {
        // DB::table, não Business::query(): as colunas do JOIN (`sub_status`, `sub_end`) não
        // existem no model, e hidratar Eloquent só pra ler 5 linhas não paga. O PHPStan
        // reclamava disso com razão (`property.notFound`) — aqui o retorno é stdClass mesmo.
        return DB::table('business')
            ->leftJoin('subscriptions AS s', function ($join) {
                $join->on('business.id', '=', 's.business_id')
                    ->whereRaw('s.id = (SELECT MAX(s2.id) FROM subscriptions s2 WHERE s2.business_id = business.id)');
            })
            ->orderByDesc('business.id')
            ->limit(5)
            ->get(['business.id', 'business.name', 'business.created_at', 's.status AS sub_status', 's.end_date AS sub_end'])
            ->map(fn ($b) => [
                'id' => (int) $b->id,
                'nome' => (string) $b->name,
                'criado' => $b->created_at ? Carbon::parse($b->created_at)->format('d/m/Y') : '—',
                'assinatura' => $this->rotuloAssinatura($b->sub_status, $b->sub_end),
            ])
            ->all();
    }

    /**
     * Enum do banco → PT-BR. A tela NUNCA mostra o valor cru (RUNBOOK-dashboard §2).
     *
     * `declined` é gravado por OnCobrancaVencidaBloqueaSubscription quando a cobrança vence;
     * `expired`/`cancelled` só passaram a ser graváveis no #5945 (o enum não os aceitava).
     */
    private function rotuloAssinatura(?string $status, $fim): string
    {
        if ($status === null) {
            return 'Sem assinatura';
        }

        return match ($status) {
            'approved' => ($fim && Carbon::parse($fim)->isPast()) ? 'Vencida' : 'Ativa',
            'waiting' => 'Pendente',
            'declined' => 'Bloqueada',
            'expired' => 'Vencida',
            'cancelled' => 'Cancelada',
            default => 'Sem assinatura',
        };
    }

    /**
     * Returns the stats for superadmin
     *
     * @param $start date
     * @param $end date
     * @return json
     */
    public function stats(Request $request)
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        // Wave 27 D9: span stats endpoint AJAX (chamado pela dashboard SPA Blade).
        return OtelHelper::spanBiz('superadmin.legacy.stats', function () use ($request) {
            $start_date = $request->get('start');
            $end_date = $request->get('end');

            // SUPERADMIN: stats GLOBAIS cross-tenant intencional (ADR 0093 §exceções).
            $subscription = Subscription::whereRaw('DATE(created_at) BETWEEN ? AND ?', [$start_date, $end_date])
                ->where('status', 'approved')
                ->select(DB::raw('SUM(package_price) as total'))
                ->first()
                ->total;

            $registrations = Business::whereRaw('DATE(created_at) BETWEEN ? AND ?', [$start_date, $end_date])
                ->select(DB::raw('COUNT(id) as total'))
                ->first()
                ->total;

            return ['new_subscriptions' => $subscription,
                'new_registrations' => $registrations,
            ];
        }, ['component' => 'superadmin.legacy.stats']);
    }
}
