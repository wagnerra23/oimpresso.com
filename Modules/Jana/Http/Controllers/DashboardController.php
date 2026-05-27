<?php

namespace Modules\Jana\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\Sells\SellsCockpitAggregator;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\Jana\Entities\Meta;

/**
 * Dashboard canon da Jana V2 — JanaCockpitV2 (brief diário + KPIs + análises + ações)
 * hospedado em /ia/dashboard, com Metas como bloco secundário.
 *
 * Antes da Onda 2026-05-26 (jana-cockpit-move), este controller passava só `metas`
 * e o cockpit V2 vivia como tab em /sells. Hoje JanaCockpitV2 é o conteúdo primário —
 * a marca IA (Jana) ganha sua tela própria.
 */
class DashboardController extends Controller
{
    public function index(Request $request, SellsCockpitAggregator $cockpitAggregator)
    {
        $businessId = (int) $request->session()->get('user.business_id');
        $businessName = (string) ($request->session()->get('business.name') ?? '');

        // Wagner 2026-05-25 HOTFIX pós-PR #1547: `metas` SEM Inertia::defer porque
        // Dashboard.tsx lê `metas.length` direto. coworkAggregates pode usar defer
        // (JanaCockpitV2 é resiliente — sparkline opcional, idem em /sells).
        return Inertia::render('Jana/Dashboard', [
            'metas' => $this->buildMetasPayload($businessId),

            // Jana V2 cockpit (movido de /sells — agora canon aqui).
            'sellKpis' => $cockpitAggregator->buildSellKpis($businessId),
            'insightsAggregates' => $cockpitAggregator->buildInsightsAggregates($businessId),
            'coworkAggregates' => Inertia::defer(fn () => $cockpitAggregator->buildCoworkAggregates($businessId)),

            // Tenant context pro header da Jana (avatar + breadcrumb v2026.05).
            'janaContext' => [
                'businessId'   => $businessId,
                'businessName' => $businessName,
                'userName'     => optional(auth()->user())->name,
            ],
        ]);
    }

    /**
     * D6.a defer closure — hidrata metas ativas do business com eager loads.
     * Multi-tenant Tier 0: filtra por business_id (ou repo-wide null) — ADR 0093.
     */
    protected function buildMetasPayload(int $businessId): \Illuminate\Support\Collection
    {
        $metas = Meta::where('ativo', true)
            ->where(function ($q) use ($businessId) {
                $q->where('business_id', $businessId)
                  ->orWhereNull('business_id');
            })
            ->with([
                'periodoAtual',
                'ultimaApuracao',
                'apuracoes' => fn ($q) => $q->orderBy('data_ref')->limit(12),
            ])
            ->get();

        return $metas->map(fn ($meta) => [
            'id'                 => $meta->id,
            'slug'               => $meta->slug,
            'nome'               => $meta->nome,
            'unidade'            => $meta->unidade,
            'tipo_agregacao'     => $meta->tipo_agregacao,
            'periodo_atual'      => $meta->periodoAtual ? [
                'data_ini'   => $meta->periodoAtual->data_ini,
                'data_fim'   => $meta->periodoAtual->data_fim,
                'valor_alvo' => (float) $meta->periodoAtual->valor_alvo,
                'trajetoria' => $meta->periodoAtual->trajetoria,
            ] : null,
            'ultima_apuracao'    => $meta->ultimaApuracao ? [
                'data_ref'         => $meta->ultimaApuracao->data_ref,
                'valor_realizado'  => (float) $meta->ultimaApuracao->valor_realizado,
            ] : null,
            'apuracoes_recentes' => $meta->apuracoes->map(fn ($a) => [
                'data_ref'        => $a->data_ref,
                'valor_realizado' => (float) $a->valor_realizado,
            ])->values(),
        ]);
    }
}
