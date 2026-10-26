<?php

namespace Modules\ComunicacaoVisual\Http\Controllers\Public;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Modules\ComunicacaoVisual\Services\AceiteDoOrcamento;
use Symfony\Component\HttpFoundation\Response;

/**
 * US-COMVIS-023 — página PÚBLICA (sem login) em que o cliente aceita o orçamento ou pede ajuste.
 *
 *   GET  /orcamento-cv/{token}          → orçamento + botões (ou o aviso de link vencido/usado)
 *   POST /orcamento-cv/{token}/aceitar  → vira OS pelo OrcamentoViraOs (sem recalcular valor)
 *   POST /orcamento-cv/{token}/ajuste   → volta a rascunho com o comentário (obrigatório)
 *
 * A empresa vem do token (só o sha256 fica no banco), nunca da URL. Token desconhecido = 404, sem
 * dizer por quê. Throttle 30/min/IP nas rotas. `noindex` e `no-referrer`: o link não pode vazar
 * por busca nem pelo cabeçalho Referer de um clique na página.
 */
class OrcamentoAceitePublicoController extends Controller
{
    public function __construct(private readonly AceiteDoOrcamento $aceite) {}

    public function mostrar(string $token): Response
    {
        $orcamento = $this->aceite->paraCliente($token);
        abort_if($orcamento === null, 404);

        return $this->pagina($token, $orcamento);
    }

    public function aceitar(string $token): RedirectResponse
    {
        return $this->decidir($token, 'aceito', null, 'Orçamento aceito. Obrigado! A gráfica já recebeu o pedido.');
    }

    public function ajuste(Request $request, string $token): RedirectResponse
    {
        $comentario = (string) $request->validate(
            ['comentario' => ['required', 'string', 'max:2000']],
            ['comentario.required' => 'Conte o que precisa mudar no orçamento.']
        )['comentario'];

        return $this->decidir($token, 'ajuste', $comentario, 'Pedido de ajuste enviado. A gráfica vai revisar e mandar um novo orçamento.');
    }

    private function decidir(string $token, string $decisao, ?string $comentario, string $feito): RedirectResponse
    {
        abort_if($this->aceite->paraCliente($token) === null, 404);
        try {
            $this->aceite->decidir($token, $decisao, $comentario);
        } catch (\DomainException $e) {
            // redirect explícito, não back(): com `no-referrer` o navegador não manda o Referer.
            return redirect("/orcamento-cv/{$token}")->withErrors(['decisao' => $e->getMessage()]);
        }

        return redirect("/orcamento-cv/{$token}")->with('aceite_feito', $feito);
    }

    /** @param array<string, mixed> $orcamento */
    private function pagina(string $token, array $orcamento): Response
    {
        return Inertia::render('ComunicacaoVisual/AceitePublico', [
            'token'     => $token,
            'orcamento' => $orcamento,
            'feito'     => session('aceite_feito'),
        ])->toResponse(request())
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Referrer-Policy', 'no-referrer');
    }
}
