<?php

namespace Modules\Financeiro\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Financeiro\Models\BoletoRemessa;
use Modules\Financeiro\Services\TituloService;

/**
 * Tela /financeiro/boletos.
 * Lista BoletoRemessa com filtros + acao cancelar.
 */
class BoletoController extends Controller
{
    public function index(Request $request): Response
    {
        $businessId = $request->session()->get('business.id');

        $remessas = BoletoRemessa::where('business_id', $businessId)
            ->whereNull('deleted_at')
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->with(['titulo:id,numero,cliente_descricao,vencimento'])
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn (BoletoRemessa $b) => [
                'id' => $b->id,
                'titulo_id' => $b->titulo_id,
                'titulo_numero' => $b->titulo?->numero,
                'cliente' => $b->titulo?->cliente_descricao,
                'nosso_numero' => $b->nosso_numero,
                'linha_digitavel' => $b->linha_digitavel,
                'codigo_barras' => $b->codigo_barras,
                'valor_total' => $b->valor_total,
                'vencimento' => $b->vencimento?->toDateString(),
                'status' => $b->status,
                'strategy' => $b->strategy,
                'enviado_em' => $b->enviado_em?->toIso8601String(),
                'pago_em' => $b->pago_em?->toIso8601String(),
                'created_at' => $b->created_at->toIso8601String(),
            ]);

        return Inertia::render('Financeiro/Boletos/Index', [
            'remessas' => $remessas,
            'filtros' => ['status' => $request->status],
        ]);
    }

    public function cancelar(Request $request, int $remessaId, TituloService $service): RedirectResponse
    {
        $businessId = $request->session()->get('business.id');

        $remessa = BoletoRemessa::where('business_id', $businessId)->findOrFail($remessaId);

        if ($remessa->status === BoletoRemessa::STATUS_CANCELADO) {
            return back()->with('error', 'Boleto ja cancelado.');
        }

        if ($remessa->status === BoletoRemessa::STATUS_PAGO) {
            return back()->with('error', 'Boleto ja pago — nao pode ser cancelado.');
        }

        $service->cancelarBoleto($remessa, $request->input('motivo', 'cancelado pelo usuario'));

        return back()->with('success', 'Boleto cancelado.');
    }
}
