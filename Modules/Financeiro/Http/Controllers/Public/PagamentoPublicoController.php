<?php

namespace Modules\Financeiro\Http\Controllers\Public;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Modules\Financeiro\Services\LinkDePagamentoDoTitulo;
use Symfony\Component\HttpFoundation\Response;

/**
 * US-FIN-081 — página PÚBLICA (sem login) em que o cliente paga um título a receber.
 *
 *   GET  /pagar/{token}           → título + valor em aberto + meio de pagar já emitido
 *   POST /pagar/{token}/{metodo}  → emite Pix ou boleto (ou devolve o que já existe)
 *
 * Mesmo desenho do aceite do orçamento (US-COMVIS-023): a empresa vem do token (só o sha256 fica
 * no banco), nunca da URL; token desconhecido = 404 sem dizer por quê; throttle na rota;
 * `noindex` e `no-referrer`. Com `financeiro.link_pagamento.enabled` desligado, tudo responde 404.
 */
class PagamentoPublicoController extends Controller
{
    public function __construct(private readonly LinkDePagamentoDoTitulo $link) {}

    public function mostrar(string $token): Response
    {
        abort_unless(LinkDePagamentoDoTitulo::ligado(), 404);
        $titulo = $this->link->paraCliente($token);
        abort_if($titulo === null, 404);

        $resposta = Inertia::render('Financeiro/PagamentoPublico', [
            'token'  => $token,
            'titulo' => $titulo,
            'gerado' => session('pagamento_gerado'),
        ])->toResponse(request());
        $resposta->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $resposta->headers->set('Referrer-Policy', 'no-referrer');

        return $resposta;
    }

    public function pagar(string $token, string $metodo): RedirectResponse
    {
        abort_unless(LinkDePagamentoDoTitulo::ligado(), 404);
        abort_if($this->link->paraCliente($token) === null, 404);
        try {
            $meio = $this->link->pagar($token, $metodo);
        } catch (\DomainException $e) {
            // redirect explícito, não back(): com `no-referrer` o navegador não manda o Referer.
            return redirect("/pagar/{$token}")->withErrors(['pagamento' => $e->getMessage()]);
        }

        return redirect("/pagar/{$token}")->with('pagamento_gerado', $meio);
    }
}
