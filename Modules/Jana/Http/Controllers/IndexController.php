<?php

namespace Modules\Jana\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\Sells\SellsCockpitAggregator;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\Jana\Entities\Meta;
use Modules\Jana\Services\ApuracaoService;

/**
 * Painel da Jana — a raiz do módulo (`GET /ia`).
 *
 * Onda 3 da fusão (US-COPI-148, 2026-08-07): era `DashboardController` em
 * `/ia/dashboard`. A rota mudou para `/ia` porque o Painel JÁ ERA o destino
 * pós-login — `routes/web.php` fazia `/home → redirect('/ia/dashboard')` —,
 * então a troca alinha a URL com o que já acontecia, em vez de inverter produto.
 * `/ia/dashboard` e `/ia/cockpit` viraram **301** para `/ia`.
 *
 * ⚠️ O conteúdo primário vem do `_components/JanaCockpit.tsx` (PT-04). O antigo
 * `components/JanaCockpitV2.tsx` foi **removido em 2026-08-10** (0 imports medidos);
 * até então este bloco afirmava que ele "serve a tab Insights de `/sells`", o que era
 * falso — a tab saiu de `/sells` e o `SellsInsightsView` já estava deletado. A regra
 * **R7** do `ui:lint` (sem bundle paralelo na Jana) segue valendo e não depende disso.
 * Ver `RUNBOOK-components.md`.
 */
class IndexController extends Controller
{
    public function index(
        Request $request,
        SellsCockpitAggregator $cockpitAggregator,
        ApuracaoService $apuracao
    ) {
        $businessId = (int) $request->session()->get('user.business_id');
        $businessName = (string) ($request->session()->get('business.name') ?? '');

        // Wagner 2026-05-25 HOTFIX pós-PR #1547: `metas` SEM Inertia::defer porque
        // a Page lê `metas.length` direto. coworkAggregates pode usar defer
        // (o cockpit é resiliente — sparkline opcional, idem em /sells).
        return Inertia::render('Jana/Index', [
            'metas' => $this->buildMetasPayload($businessId, $apuracao),

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
    protected function buildMetasPayload(int $businessId, ApuracaoService $apuracao): \Illuminate\Support\Collection
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
            // Farol vem do SERVIDOR (ApuracaoService::farol) — o charter exigia
            // isso em §Goals e §Anti-hooks desde sempre, e o frontend calculava
            // assim mesmo. Agora ele só consome.
            'farol'              => $apuracao->farol($meta),
            // Projeção vem do SERVIDOR pelo mesmo motivo do farol (§Anti-hooks do
            // charter): a Page só consome. `null` = não há base pra projetar —
            // exatamente os casos em que o farol é 'cinza'.
            'projecao'           => $apuracao->projecao($meta),
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
