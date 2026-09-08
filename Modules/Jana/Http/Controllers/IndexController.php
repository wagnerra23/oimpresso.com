<?php

namespace Modules\Jana\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\Sells\SellsCockpitAggregator;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\Jana\Entities\Meta;
use Modules\Jana\Entities\MetaFonte;
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
                // A fonte herda tenancy do parent (`BelongsToBusinessViaParent`) e NAO
                // precisa de dispensa nenhuma — medido em 2026-09-08, depois de eu ter
                // posto um `withoutGlobalScope` aqui por uma armadilha que NAO existe:
                //
                //   · usuario COMUM  — `ScopeByBusiness` filtra `business_id = <sessao>`
                //     ESTRITO, entao meta de plataforma (nulo) nem chega no payload; toda
                //     meta que chega casa o escopo do parent.
                //   · SUPERADMIN     — `ScopeByBusiness` abre pra `= X OR IS NULL`, e o
                //     `ScopeByBusinessViaParent` abre EXATAMENTE igual pro parent.
                //
                // Nos dois papeis os dois escopos concordam. Quem provou foi o UC-JPAIN-22:
                // ele reprovou em `expect($plataforma)->not->toBeNull()` — o que sumia era a
                // META, nunca a fonte dela. Dispensar defesa Tier 0 sem necessidade e o
                // oposto do que a ADR 0093 pede.
                'fonte',
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
            // Os tres abaixo vinham de `metas/show.blade.php` e nao existiam no payload.
            // `business_id` sai CRU: quem decide como escrever "Plataforma" x "este
            // negocio" e a tela; o back nao manda frase pronta.
            'origem'             => $meta->origem,
            'business_id'        => $meta->business_id,
            // A fonte vinha de `fontes/show.blade.php`, so-leitura por decisao (o editor
            // com previa do numero antes de salvar e a US-COPI-040). `null` = meta sem
            // fonte gravada, que e estado REAL: sem fonte a meta nao apura.
            'fonte'              => $this->fontePayload($meta),
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

    /**
     * Payload da fonte da meta — o que a `fontes/show.blade.php` mostrava.
     *
     * Existe como METODO, e nao inline no `map`, por causa do analisador estatico:
     * `Meta::fonte()` declara `HasOne` SEM o generico, entao `$meta->fonte` chega
     * tipado como `Model` e acessar `->driver` vira "undefined property" (o PHPStan
     * acusou as 3 linhas). O `instanceof` abaixo e o que estreita o tipo.
     *
     * Tipar a relacao seria o conserto de RAIZ e e melhor — mas ela tem outros dois
     * consumidores vivos (`SqlDriver`, `ApuracaoService`), e mexer no tipo deles pode
     * acordar achado novo num PR que e sobre tela. Fica declarado, nao escondido.
     *
     * `null` = meta sem fonte gravada. Estado REAL: sem fonte a meta nao apura.
     */
    private function fontePayload(Meta $meta): ?array
    {
        $fonte = $meta->fonte;

        if (! $fonte instanceof MetaFonte) {
            return null;
        }

        return [
            'driver'      => $fonte->driver,
            'cadencia'    => $fonte->cadencia,
            'config_json' => $fonte->config_json,
        ];
    }
}
