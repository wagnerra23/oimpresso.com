<?php

namespace Modules\OficinaAuto\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * ProducaoOficinaController — redirect permanente pro Quadro de OS canônico.
 *
 * UNIFICAÇÃO DOS 2 KANBANS (ADR 0265 — erradicação de locação, 2026-06-10):
 * a tela "Produção · Oficina" era um kanban VEÍCULO-cêntrico cujas colunas eram
 * status de LOCAÇÃO (`disponivel`/`locada`/`aguardando`/`manutencao`/`pronta` do
 * processo legado `cacamba_locacao`) com cards de aluguel (diárias, vencimento,
 * capacidade m³, endereço de obra). Numa oficina de REPARO esse modelo é o próprio
 * vocabulário proibido — não há string que conserte colunas de aluguel.
 *
 * O kanban canônico é o Quadro de OS (`/oficina-auto/ordens-servico/board` —
 * ServiceOrderController@board + Pages/OficinaAuto/ServiceOrders/Board.tsx):
 * OS-cêntrico, colunas data-driven pelas etapas reais do FSM `oficina_mecanica_os`
 * (recepção → diagnóstico → aprovação → peças → execução → pronto), port do
 * protótipo Cowork aprovado, drawer rico V2 (DVI + fotos + gate). UMA verdade.
 *
 * Esta rota sobrevive como redirect (menu antigo, bookmarks, links em WhatsApp).
 * Filtros compatíveis (q / mecanico / box) são repassados; `capacidade` (m³ —
 * conceito de caçamba) morre aqui.
 *
 * @see Modules/OficinaAuto/Http/Controllers/ServiceOrderController.php (board)
 * @see resources/js/Pages/OficinaAuto/ServiceOrders/Board.tsx (kanban canônico)
 * @see memory/decisions/0265-oficina-reparo-erradica-locacao.md
 */
class ProducaoOficinaController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        $params = array_filter([
            'q'        => trim((string) $request->string('q')) ?: null,
            'mecanico' => $request->integer('mecanico') ?: null,
            'box'      => trim((string) $request->string('box')) ?: null,
        ]);

        return redirect()->to(
            '/oficina-auto/ordens-servico/board' . ($params ? '?' . http_build_query($params) : ''),
            301
        );
    }
}
