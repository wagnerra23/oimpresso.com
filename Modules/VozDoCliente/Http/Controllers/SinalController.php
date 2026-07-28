<?php

namespace Modules\VozDoCliente\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
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
    /**
     * Caixa de triagem — os sinais deste business, pendentes primeiro.
     *
     * Blade de propósito: tela Inertia exige charter + Padrão de Tela + gate
     * visual (ADR 0104/UI-0013), que é PR próprio. Aqui a prioridade é o canal
     * existir e ser usável.
     */
    public function index(): View
    {
        abort_unless(
            auth()->user()->can('superadmin') || auth()->user()->can('vozdocliente.triar'),
            403
        );

        // Global scope já limita ao business da sessão (Sinal::booted).
        //
        // Ordenação portável de propósito: `FIELD()` é MySQL-only e quebraria a
        // suíte em SQLite. O CASE funciona nos dois e diz a mesma coisa —
        // pendente primeiro, fechado por último.
        $sinais = Sinal::query()
            ->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'triaged' THEN 1 ELSE 2 END")
            ->orderByDesc('created_at')
            ->paginate(30);

        return view('vozdocliente::caixa', compact('sinais'));
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

        return back()->with('status_ok', 'Recebemos seu relato. Obrigado — ele vai pra triagem.');
    }
}
