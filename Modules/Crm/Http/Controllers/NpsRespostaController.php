<?php

declare(strict_types=1);

namespace Modules\Crm\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Crm\Services\Nps\PesquisaNps;

/**
 * Página pública de resposta do NPS (US-CRM-097) — `/nps/{token}`, aberta pelo link do WhatsApp.
 *
 * Sem login: o token é o único dado da URL. A empresa vem da linha do token, e a página mostra
 * só o nome dela e se a pesquisa já foi respondida — nunca o cliente, a venda ou a OS.
 * Flag `crm.nps_enabled` desligada ou token desconhecido = 404 (mesma resposta).
 */
class NpsRespostaController extends Controller
{
    public function __construct(private readonly PesquisaNps $nps) {}

    public function show(string $token): Response
    {
        $linha = $this->linha($token);

        return Inertia::render('Crm/Nps/Responder', [
            'token' => $token,
            'empresa' => (string) DB::table('business')->where('id', $linha->business_id)->value('name'),
            'nota' => $linha->nota !== null ? (int) $linha->nota : null,
        ]);
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $this->linha($token);
        $dados = $request->validate([
            'nota' => ['required', 'integer', 'between:0,10'],
            'comentario' => ['nullable', 'string', 'max:1000'],
        ]);

        // 'ja_respondida' também volta para a página, que mostra a nota que valeu.
        $this->nps->responder($token, (int) $dados['nota'], $dados['comentario'] ?? null);

        return redirect()->route('crm.nps.responder', ['token' => $token]);
    }

    private function linha(string $token): object
    {
        abort_unless((bool) config('crm.nps_enabled', false) && preg_match('/^[a-z0-9]{40}$/', $token), 404);
        $linha = DB::table('crm_nps_pesquisas')->where('token', $token)->first(['business_id', 'nota']);
        abort_if($linha === null, 404);

        return $linha;
    }
}
