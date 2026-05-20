<?php

namespace Modules\Fiscal\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Modules\NfeBrasil\Models\NfeEmissao;
use Modules\NfeBrasil\Services\Manifestacao\ManifestacaoService;
use Modules\NfeBrasil\Services\NfeService;

/**
 * Ações de mutação fiscal (Wave 4) — thin delegate.
 *
 * Delega pra Services existentes em Modules/NfeBrasil sem duplicar lógica:
 *  - Cancelar NFe → NfeService::cancelar (FSM cascade ADR 0143 LIVE biz=1)
 *  - Manifestar DF-e → ManifestacaoService (cienciar/confirmar/desconhecer/nao-realizada)
 *
 * Retorna back()->with('flash') pra usuário ficar na tela Fiscal cockpit.
 *
 * Multi-tenant Tier 0 (ADR 0093):
 *  - $businessId via session('user.business_id')
 *  - guard explícito no findOrFail (HasBusinessScope cobre, mas defesa em profundidade)
 *
 * Permissões:
 *  - fiscal.nfe.acoes: cancelar NFe
 *  - fiscal.dfe.manage: manifestar DF-e
 *
 * Throttle 30/min anti-DOS herda do pattern Modules/NfeBrasil/Routes/web.php
 * (proteção SEFAZ — disparos descontrolados podem ban IP no webservice).
 *
 * NÃO inclui CC-e + Inutilização + Retransmitir neste PR — esses exigem UI
 * adicional (texto correção / faixa numérica / re-build payload) e ficam
 * em PR seguinte conforme Non-Goals do charter Fiscal/Nfe.
 */
class AcoesController extends Controller
{
    /**
     * POST /fiscal/acoes/nfe/{emissao}/cancelar
     *
     * Cancela NFe/NFC-e autorizada dentro da janela legal (24h NFCe / 168h NFe).
     * Justificativa obrigatória ≥15 caracteres (regra CONFAZ).
     *
     * Chama NfeService::cancelar que orquestra:
     *  - Validação janela legal
     *  - Envio evento 110111 SEFAZ
     *  - Persistir NfeEvento (cstat 135 = homologado)
     *  - Update NfeEmissao.status='cancelada'
     *  - Dispara FSM cascade ADR 0143 (refund gateway + notificação cliente — biz=1 LIVE)
     */
    public function cancelarNfe(Request $request, NfeService $nfeService, int $emissao): RedirectResponse
    {
        if (! auth()->user()->can('superadmin') && ! auth()->user()->can('fiscal.nfe.acoes')) {
            abort(403, 'Sem permissão fiscal.nfe.acoes');
        }

        $data = $request->validate([
            'motivo' => ['required', 'string', 'min:15', 'max:255'],
        ]);

        $businessId = (int) session('user.business_id');

        // Defesa em profundidade — global scope já filtra, mas guard explícito
        // protege caso futuro alguém remova HasBusinessScope.
        $nfeEmissao = NfeEmissao::query()
            ->where('id', $emissao)
            ->where('business_id', $businessId)
            ->firstOrFail();

        if ($nfeEmissao->status !== 'autorizada') {
            return back()->with('error', "Apenas notas autorizadas podem ser canceladas. Status atual: {$nfeEmissao->status}");
        }

        try {
            $evento = $nfeService->cancelar($businessId, $nfeEmissao->id, $data['motivo']);

            Log::info('Fiscal.acoes.cancelarNfe ok', [
                'business_id'    => $businessId,
                'nfe_emissao_id' => $nfeEmissao->id,
                'evento_id'      => $evento->id,
                'cstat_evento'   => $evento->cstat_evento,
            ]);

            return back()->with('success', "NFe {$nfeEmissao->numero} cancelada · cstat {$evento->cstat_evento}");
        } catch (\Throwable $e) {
            Log::error('Fiscal.acoes.cancelarNfe falhou', [
                'business_id'    => $businessId,
                'nfe_emissao_id' => $nfeEmissao->id,
                'error'          => $e->getMessage(),
            ]);

            return back()->with('error', 'Cancelamento falhou: ' . $e->getMessage());
        }
    }

    /**
     * POST /fiscal/acoes/dfe/{recebido}/{acao}
     *
     * Manifestação destinatário SEFAZ — 4 ações:
     *  - cienciar     → tpEvento 210210 (ciência da operação)
     *  - confirmar    → tpEvento 210200 (confirmação)
     *  - desconhecer  → tpEvento 210220 (desconhecimento — exige justificativa)
     *  - nao_realizada → tpEvento 210240 (operação não realizada — exige justificativa)
     *
     * Delega pra ManifestacaoService (Modules/NfeBrasil/Services/Manifestacao/).
     * Idempotente: re-chamar mesma ação retorna evento existente.
     */
    public function manifestarDfe(
        Request $request,
        ManifestacaoService $service,
        int $recebido,
        string $acao,
    ): RedirectResponse {
        if (! auth()->user()->can('superadmin') && ! auth()->user()->can('fiscal.dfe.manage')) {
            abort(403, 'Sem permissão fiscal.dfe.manage');
        }

        $businessId = (int) session('user.business_id');

        $acoesValidas = ['cienciar', 'confirmar', 'desconhecer', 'nao_realizada'];
        if (! in_array($acao, $acoesValidas, true)) {
            abort(404, 'Ação manifestação não reconhecida');
        }

        $justificativa = null;
        if (in_array($acao, ['desconhecer', 'nao_realizada'], true)) {
            $data = $request->validate([
                'justificativa' => ['required', 'string', 'min:15', 'max:255'],
            ]);
            $justificativa = $data['justificativa'];
        }

        try {
            // ManifestacaoService API: cada método retorna evento aplicado
            // (pattern já validado nos endpoints existentes /nfe-brasil/manifestacao).
            match ($acao) {
                'cienciar'      => $service->cienciar($businessId, $recebido),
                'confirmar'     => $service->confirmar($businessId, $recebido),
                'desconhecer'   => $service->desconhecer($businessId, $recebido, $justificativa),
                'nao_realizada' => $service->naoRealizada($businessId, $recebido, $justificativa),
            };

            Log::info('Fiscal.acoes.manifestarDfe ok', [
                'business_id'      => $businessId,
                'dfe_recebido_id'  => $recebido,
                'acao'             => $acao,
            ]);

            $labels = [
                'cienciar'      => 'Ciência registrada',
                'confirmar'     => 'Operação confirmada',
                'desconhecer'   => 'Desconhecimento registrado',
                'nao_realizada' => 'Operação não realizada registrada',
            ];

            return back()->with('success', $labels[$acao]);
        } catch (\Throwable $e) {
            Log::error('Fiscal.acoes.manifestarDfe falhou', [
                'business_id'     => $businessId,
                'dfe_recebido_id' => $recebido,
                'acao'            => $acao,
                'error'           => $e->getMessage(),
            ]);

            return back()->with('error', 'Manifestação falhou: ' . $e->getMessage());
        }
    }
}
