<?php

namespace Modules\VozDoCliente\Http\Controllers;

use App\Services\FeatureFlagService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;
use Modules\VozDoCliente\Entities\Sinal;
use Modules\VozDoCliente\Http\Requests\StoreSinalRequest;

/**
 * SinalController — grava o sinal e mostra a caixa de triagem.
 *
 * Tier 0 ({@see ADR 0093}): o `business_id` é resolvido AQUI, explicitamente, a
 * partir da sessão — nunca aceito do request. Cliente não escolhe de que
 * business é o sinal que está mandando.
 */
class SinalController extends Controller
{
    /** Situações aceitas em `?situacao=` (ausente = todas). */
    private const SITUACOES = [Sinal::STATUS_PENDENTE, Sinal::STATUS_TRIADO, Sinal::STATUS_FECHADO];

    /**
     * Caixa de triagem — os sinais deste business, pendentes primeiro.
     *
     * Dual (thread modulos-faltantes/playbook/03, RUNBOOK-caixa): com a flag
     * `useV2VozDoClienteCaixa` ligada para o negócio responde a Page
     * `VozDoCliente/Caixa`; desligada no GrowthBook ou com `?legacy=1`, segue a Blade de
     * sempre. Desde o cutover [W] 2026-10-13 a flag está nos fallbacks do FeatureFlagService com `true`.
     *
     * A permissão é checada ANTES da flag: quem não pode ver não consulta flag.
     */
    public function index(): View|Response
    {
        abort_unless(
            auth()->user()->can('superadmin') || auth()->user()->can('vozdocliente.triar'),
            403
        );

        $businessId = session('user.business_id') ?? session('business.id');

        if (! request()->boolean('legacy')
            && app(FeatureFlagService::class)->isOn('useV2VozDoClienteCaixa', ['business_id' => $businessId])) {
            return $this->caixaInertia((int) $businessId);
        }

        // Tier 0 (ADR 0093): o filtro por `business_id` vem do global scope
        // declarado em Sinal::booted() — `where('voz_sinais.business_id', <sessão>)`.
        // Está provado por SinalCrossTenantTest (biz=1 × biz=99), não afirmado.
        // O `where` explícito abaixo é REDUNDANTE com o scope de propósito:
        // defesa em profundidade, pra que a consulta continue escopada mesmo se
        // alguém remover o scope do Model um dia sem rodar o teste.
        //
        // Ordenação portável de propósito: `FIELD()` é MySQL-only e quebraria a
        // suíte em SQLite. O CASE funciona nos dois e diz a mesma coisa —
        // pendente primeiro, fechado por último.
        $sinais = $this->consultaDaCaixa($businessId)->paginate(30);

        return view('vozdocliente::caixa', compact('sinais'));
    }

    /**
     * A mesma consulta serve a Blade e a Page — uma fonte só para "o que a caixa
     * mostra e em que ordem", senão as duas telas divergem durante o dual.
     */
    private function consultaDaCaixa(int|string|null $businessId)
    {
        return Sinal::query()
            ->where('business_id', $businessId)
            ->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'triaged' THEN 1 ELSE 2 END")
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    /**
     * Page React da caixa. `contagem` é eager (um GROUP BY indexado em
     * business_id+status); a lista paginada é adiada (Inertia::defer) e é o que o
     * chip de situação recarrega.
     */
    private function caixaInertia(int $businessId): Response
    {
        $situacao = request()->query('situacao');
        $situacao = in_array($situacao, self::SITUACOES, true) ? $situacao : null;
        $limiar = (int) config('vozdocliente.severidade_que_exige_triagem', 3);

        $porStatus = Sinal::query()->where('business_id', $businessId)
            ->selectRaw('status, COUNT(*) AS n')->groupBy('status')->pluck('n', 'status');
        $alta = Sinal::query()->where('business_id', $businessId)
            ->where('severidade', '>=', $limiar)->count();

        return Inertia::render('VozDoCliente/Caixa', [
            'filtro' => $situacao,
            'limiar_alta' => $limiar,
            'contagem' => [
                'total' => (int) $porStatus->sum(),
                'pending' => (int) ($porStatus[Sinal::STATUS_PENDENTE] ?? 0),
                'triaged' => (int) ($porStatus[Sinal::STATUS_TRIADO] ?? 0),
                'closed' => (int) ($porStatus[Sinal::STATUS_FECHADO] ?? 0),
                'alta' => $alta,
            ],
            'sinais' => Inertia::defer(fn () => $this->consultaDaCaixa($businessId)
                ->when($situacao, fn ($q) => $q->where('status', $situacao))
                ->paginate(30)->withQueryString()
                ->through(fn (Sinal $s) => [
                    'id' => $s->id,
                    // getAttribute() e não ->created_at: o docblock de Sinal não declara a coluna, e
                    // mexer no Model acorda a dívida grandfathered do MultiTenantScopeArchitectureTest.
                    'quando' => $s->getAttribute('created_at')?->toIso8601String(),
                    'autor' => $s->autor_nome,
                    'texto' => $s->texto,
                    'onde' => $s->url_vista,
                    'severidade' => $s->severidade,
                    'status' => $s->status,
                    'us' => $s->triado_para_us,
                ])),
        ]);
    }

    public function store(StoreSinalRequest $request): RedirectResponse
    {
        $businessId = (int) (session('user.business_id') ?? session('business.id'));

        // Sem business em sessão não há a quem pertencer o sinal — gravar com
        // business 0 criaria linha órfã invisível pro global scope (Tier 0).
        abort_if($businessId <= 0, 403, 'Sessao sem business — sinal nao pode ser gravado.');

        $texto = (string) $request->validated()['texto'];

        // Dedup: mesmo business + mesmo texto = o mesmo sinal. `firstOrCreate`
        // sobre a unique (business_id, hash_origem) evita que reenvio por
        // impaciência (clicar duas vezes) vire duas linhas na caixa.
        //
        // SEM `withoutGlobalScope` de propósito: o business do scope e o daqui
        // são o mesmo (ambos da sessão), então o scope é redundante — e furar o
        // scope sem necessidade é o que a proibição Tier 0 barra.
        Sinal::firstOrCreate(
            [
                'business_id' => $businessId,
                'hash_origem' => Sinal::hashDe($businessId, $texto),
            ],
            [
                'user_id'    => auth()->id(),
                'autor_nome' => auth()->user()->first_name ?? null,
                'canal'      => 'sistema',
                'texto'      => $texto,
                'severidade' => $request->validated()['severidade'] ?? null,
                'url_vista'  => $request->validated()['url_vista'] ?? $request->headers->get('referer'),
                'status'     => Sinal::STATUS_PENDENTE,
            ]
        );

        // Convenção UltimatePOS `status` => ['success', 'msg'] — é a ÚNICA que o
        // flash bag lê (HandleInertiaRequests: 'status.msg' + 'status.success').
        // Eu tinha escrito `->with('status_ok', ...)`, chave que aparecia 1× no
        // repo inteiro: aqui. Gravava o sinal e a tela não dizia nada — o mesmo
        // modo de falha silenciosa catalogado em 2026-06-04 (venda bloqueada sem
        // aviso). Achado pela revisão de design, não por gate.
        //
        // Copy sem jargão: "triagem" é vocabulário interno, não de quem opera a
        // loja. E não promete resposta que não existe — o canal não é suporte.
        return back()->with('status', [
            'success' => 1,
            'msg'     => 'Recebido. Obrigado — isso ajuda a melhorar o sistema.',
        ]);
    }
}
