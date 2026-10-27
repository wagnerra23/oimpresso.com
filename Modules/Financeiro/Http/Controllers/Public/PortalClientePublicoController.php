<?php

namespace Modules\Financeiro\Http\Controllers\Public;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Modules\Financeiro\Services\LinkDePagamentoDoTitulo;
use Modules\Financeiro\Services\PortalDoCliente;
use Symfony\Component\HttpFoundation\Response;

/**
 * US-FIN-082 — portal PÚBLICO (sem login) do cliente: títulos em aberto, segunda via, histórico, pagar.
 *
 *   GET  /portal-cliente/{token}                       → títulos do cliente daquela empresa
 *   POST /portal-cliente/{token}/{tituloId}/{metodo}   → paga um título pelo link de pagamento (US-FIN-081)
 *
 * Mesmo desenho do link de pagamento: empresa e cliente vêm do token (só o sha256 no banco);
 * token desconhecido = 404; throttle; `noindex` e `no-referrer`. Flag desligada = 404. Pagar exige
 * também a flag do link de pagamento, porque é ela que liga a baixa pelo webhook.
 */
class PortalClientePublicoController extends Controller
{
    public function __construct(private readonly PortalDoCliente $portal) {}

    public function mostrar(string $token): Response
    {
        abort_unless(PortalDoCliente::ligado(), 404);
        $dados = $this->portal->paraCliente($token);
        abort_if($dados === null, 404);

        $resposta = Inertia::render('Financeiro/PortalCliente', [
            'token'      => $token,
            'portal'     => $dados,
            'pode_pagar' => LinkDePagamentoDoTitulo::ligado(),
            'gerado'     => session('portal_gerado'),
        ])->toResponse(request());
        $resposta->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $resposta->headers->set('Referrer-Policy', 'no-referrer');

        return $resposta;
    }

    public function pagar(string $token, int $tituloId, string $metodo): RedirectResponse
    {
        abort_unless(PortalDoCliente::ligado() && LinkDePagamentoDoTitulo::ligado(), 404);
        abort_if($this->portal->paraCliente($token) === null, 404);
        try {
            $meio = $this->portal->pagar($token, $tituloId, $metodo);
        } catch (\DomainException $e) {
            // redirect explícito, não back(): com `no-referrer` o navegador não manda o Referer.
            return redirect("/portal-cliente/{$token}")->withErrors(['pagamento' => $e->getMessage()]);
        }

        return redirect("/portal-cliente/{$token}")->with('portal_gerado', ['titulo_id' => $tituloId, 'meio' => $meio]);
    }
}
