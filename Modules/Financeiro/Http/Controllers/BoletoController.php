<?php

namespace Modules\Financeiro\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Util\OtelHelper;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Financeiro\Models\BoletoRemessa;
use Modules\Financeiro\Services\TituloService;

/**
 * Cancelamento de BoletoRemessa legacy.
 *
 * História: este controller servia a tela `/financeiro/boletos` (protótipo Cowork
 * "Boleto e Contas Inter", aprovado [W] 2026-05-09). Em 2026-05-19 a tela foi
 * substituída pela Cobrança (F3 PaymentGateway UI — Cowork F1.5 score 96/100
 * aprovado [W]; ADR 0144 + ADR 0170), `Pages/Financeiro/Boletos/Index` foi
 * deletada no hotfix do mesmo dia e `GET /boletos` virou 301 pra
 * `/financeiro/cobranca`.
 *
 * O `index()` e seus helpers privados (`shapeRemessa`, `kpis`, `funil`,
 * `listarContas`, `bancoShort`) sobreviveram àquela deleção como código
 * inalcançável: nenhuma rota GET apontava pra eles e o `Inertia::render`
 * apontava pra uma page que não existia mais. O `OrphanRenderGateTest` (Catraca
 * Viva, Fase 1) detectou o render órfão e o manteve numa allowlist cuja ação
 * declarada era "limpar dead code: task separada" — é o que este arquivo passou
 * a refletir; a entrada saiu da allowlist junto.
 *
 * Sobrou o `cancelar()`, que a rota 301 não cobre: ele cancela BoletoRemessa
 * legacy direto no banco, e o modelo segue vivo (CnabDirectStrategy,
 * TituloService, ContaBancaria, Config/retention). O comentário em Routes/web.php
 * declarou preservá-lo por 60 dias a partir de 2026-05-19 — prazo que terminou em
 * 2026-07-18. Aposentar ou manter é decisão [W]; nada aqui a antecipa.
 */
class BoletoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('can:financeiro.dashboard.view');
    }

    public function cancelar(Request $request, int $remessaId, TituloService $service): RedirectResponse
    {
        $businessId = (int) $request->session()->get('business.id');

        // Wave 17 D9 — operação mutativa observada (span por op + business).
        return OtelHelper::spanBiz('financeiro.boleto.cancelar', function () use ($request, $remessaId, $service, $businessId) {
            $remessa = BoletoRemessa::where('business_id', $businessId)->findOrFail($remessaId);

            if ($remessa->status === BoletoRemessa::STATUS_CANCELADO) {
                return back()->with('error', 'Boleto ja cancelado.');
            }

            if ($remessa->status === BoletoRemessa::STATUS_PAGO) {
                return back()->with('error', 'Boleto ja pago — nao pode ser cancelado.');
            }

            $service->cancelarBoleto($remessa, $request->input('motivo', 'cancelado pelo usuario'));

            return back()->with('success', 'Boleto cancelado.');
        }, ['op' => 'cancelar', 'remessa_id' => $remessaId]);
    }
}
