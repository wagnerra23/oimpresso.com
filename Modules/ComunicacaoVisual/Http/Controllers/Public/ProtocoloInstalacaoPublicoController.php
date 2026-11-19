<?php

declare(strict_types=1);

namespace Modules\ComunicacaoVisual\Http\Controllers\Public;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Routing\Controller;
use Modules\ComunicacaoVisual\Services\ProtocoloInstalacao;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protocolo da instalação para o CLIENTE, sem login (US-COMVIS-007 · UC-PCV-25 · 1d).
 *
 *   GET /instalacao-cv/{biz}/{id}/protocolo?expires=…&signature=…  → o PDF do ProtocoloInstalacao
 *
 * O link é gerado por `ProtocoloInstalacao::linkPublico()` e vai ao cliente no aviso de instalação
 * concluída. Quem protege é a assinatura do Laravel (middleware `signed`): trocar a empresa, a
 * instalação ou a validade invalida o link (403), e passado o prazo ele expira (403).
 *
 * Tier 0 (ADR 0093): a empresa vem da URL ASSINADA, nunca de sessão. Instalação que não é dessa
 * empresa, ou ainda não concluída, dá 404 — sem dizer qual dos dois.
 * Throttle 30/min/IP, como o aceite do orçamento; sem indexação e sem Referer para outros sites.
 */
class ProtocoloInstalacaoPublicoController extends Controller
{
    public function __construct(private readonly ProtocoloInstalacao $protocolo) {}

    public function baixar(int $biz, int $id): Response
    {
        try {
            $pdf = $this->protocolo->pdf($biz, $id);
        } catch (ModelNotFoundException|\DomainException) {
            abort(404, 'Protocolo indisponível.');
        }

        $resposta = $pdf->stream('protocolo-instalacao-'.$id.'.pdf');
        $resposta->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $resposta->headers->set('Referrer-Policy', 'no-referrer');
        $resposta->headers->set('Cache-Control', 'private, no-store');

        return $resposta;
    }
}
