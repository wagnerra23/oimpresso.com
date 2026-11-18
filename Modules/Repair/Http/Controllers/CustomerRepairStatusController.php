<?php

namespace Modules\Repair\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Route;

class CustomerRepairStatusController extends Controller
{
    /**
     * Portal do cliente (GET /repair-status) — leva ao ConsultaOs.
     *
     * Decisão [W] 2026-10-02 (D-PORTAL, playbook repair/_DECISOES-W-2026-10-02): o portal
     * público é o ConsultaOs (Inertia), que lê as folhas de OS do Repair (US-CONSULTA-001).
     * Redireciona em vez de renderizar a Page aqui pra a tela ter UMA URL — links antigos e
     * QR impressos seguem funcionando. 302 (não 301): se o ConsultaOs for desligado, o
     * navegador não guardou o desvio.
     *
     * Cutover MWART (2026-10-27): a tela Blade antiga e o `POST /post-repair-status` que ela
     * usava foram removidos. Sem o ConsultaOs registrado (módulo desligado no sistema),
     * esta rota responde 404 — não há mais segunda versão do portal.
     *
     * Desde 2026-11-17 ("empresa na URL") o destino `/consulta-os` sem código NÃO busca: só
     * mostra o aviso de que o cliente precisa do link da empresa. Este redirect fica (2026-11-18)
     * para que QR e links antigos não caiam em 404 — eles chegam no aviso, não numa busca. O link
     * certo é o `/consulta-os/{codigo}` da empresa (configuração do Repair). Os atalhos do site
     * que apontavam para cá saíram no mesmo dia.
     */
    public function index()
    {
        abort_unless(Route::has('consulta-os.index'), 404);

        return redirect()->route('consulta-os.index');
    }
}
