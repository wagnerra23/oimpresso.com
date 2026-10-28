<?php

namespace Modules\VozDoCliente\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
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
     * Caixa de triagem — os sinais deste business, pendentes primeiro, na Page
     * `VozDoCliente/Caixa`.
     *
     * Cutover (F5 do MWART, 2026-10-27): a Blade `vozdocliente::caixa`, a flag
     * `useV2VozDoClienteCaixa` e o `?legacy=1` saíram. A flag valia `true` para
     * todas as empresas desde o cutover [W] de 2026-10-13 (14 dias de canário).
     *
     * A permissão é checada antes de qualquer consulta: quem não pode ver não lê nada.
     */
    public function index(): Response
    {
        abort_unless(
            auth()->user()->can('superadmin') || auth()->user()->can('vozdocliente.triar'),
            403
        );

        $businessId = session('user.business_id') ?? session('business.id');

        return $this->caixaInertia((int) $businessId);
    }

    /**
     * O que a caixa mostra e em que ordem.
     *
     * Tier 0 (ADR 0093): o filtro por `business_id` vem do global scope declarado
     * em Sinal::booted(), provado por SinalCrossTenantTest. O `where` explícito é
     * redundante de propósito — defesa em profundidade se o scope sumir do Model.
     * Ordenação por CASE e não `FIELD()`, que é MySQL-only e quebraria o SQLite.
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
