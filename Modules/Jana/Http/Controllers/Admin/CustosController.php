<?php

namespace Modules\Jana\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Jana\Services\CustosService;

/**
 * US-COPI-070 — Dashboard de custo de IA do Copiloto.
 *
 * Visão admin do business sobre quanto a IA custou no período (mês atual,
 * mês anterior, últimos 90 dias ou range custom). Mostra KPIs, breakdown
 * por usuário e gráfico de gasto diário.
 *
 * Permissão: copiloto.admin.custos.view (independente de copiloto.superadmin).
 * Scope:     business_id da sessão.
 *
 * Ver ADR Copiloto/adr/arq/0003 (Onda 1 — ROI direto).
 */
class CustosController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('can:copiloto.admin.custos.view');
    }

    public function index(Request $request, CustosService $service): Response
    {
        $businessId = (int) $request->session()->get('user.business_id');

        $preset = $request->get('preset', 'mes_atual');
        if (! in_array($preset, ['mes_atual', 'mes_anterior', '90d', 'custom'], true)) {
            $preset = 'mes_atual';
        }

        $range = $service->resolverPeriodo(
            $preset,
            $request->get('de'),
            $request->get('ate'),
        );

        // D6.a Wave 17: painel é DB-bound + foreach pesado → deferred.
        // Build helper closure pra evitar chamar service 4× (1 por prop).
        $painelDeferred = Inertia::defer(fn () => $service->painel($businessId, $range['inicio'], $range['fim']));

        return Inertia::render('Jana/Admin/Custos/Index', [
            'periodo'      => $range,  // eager — range resolvido sem DB
            'filters'      => [
                'preset' => $preset,
                'de'     => $request->get('de'),
                'ate'    => $request->get('ate'),
            ],
            'pricing' => [
                'modelo_default' => config('copiloto.ai.pricing_default_model'),
                'cambio_brl_usd' => (float) config('copiloto.ai.cambio_brl_usd'),
            ],
            // D6.a Wave 17 — payloads pesados defer'd. Front wrap em <Deferred data="kpis,por_usuario,serie_diaria" fallback={skeleton}>.
            'kpis'         => Inertia::defer(fn () => $service->painel($businessId, $range['inicio'], $range['fim'])['kpis']),
            'por_usuario'  => Inertia::defer(fn () => $service->painel($businessId, $range['inicio'], $range['fim'])['por_usuario']),
            'serie_diaria' => Inertia::defer(fn () => $service->painel($businessId, $range['inicio'], $range['fim'])['serie_diaria']),
        ]);
    }
}
