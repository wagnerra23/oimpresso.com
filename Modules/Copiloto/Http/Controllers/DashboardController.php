<?php

namespace Modules\Copiloto\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\Copiloto\Entities\Meta;

/**
 * Dashboard de metas ativas com sparkline + farol.
 * Renderiza Inertia Page 'Copiloto/Dashboard' (ver adr/ui/0001).
 */
class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $businessId = $request->session()->get('user.business_id');

        $metas = Meta::where('ativo', true)
            ->where(function ($q) use ($businessId) {
                $q->where('business_id', $businessId)
                  ->orWhereNull('business_id');
            })
            ->with([
                'periodoAtual',
                'ultimaApuracao',
                'apuracoes' => fn ($q) => $q->orderBy('data_ref')->limit(12),
            ])
            ->get();

        // Se não tem nenhuma meta, redireciona ao chat (ver adr/arq/0002)
        if ($metas->isEmpty()) {
            return redirect()->route('copiloto.chat.index')
                ->with('status', 'Nenhuma meta ativa. Converse com o Copiloto pra criar a primeira.');
        }

        $metasTransformadas = $metas->map(fn ($meta) => [
            'id'                 => $meta->id,
            'slug'               => $meta->slug,
            'nome'               => $meta->nome,
            'unidade'            => $meta->unidade,
            'tipo_agregacao'     => $meta->tipo_agregacao,
            'periodo_atual'      => $meta->periodoAtual ? [
                'data_ini'   => $meta->periodoAtual->data_ini,
                'data_fim'   => $meta->periodoAtual->data_fim,
                'valor_alvo' => (float) $meta->periodoAtual->valor_alvo,
                'trajetoria' => $meta->periodoAtual->trajetoria,
            ] : null,
            'ultima_apuracao'    => $meta->ultimaApuracao ? [
                'data_ref'         => $meta->ultimaApuracao->data_ref,
                'valor_realizado'  => (float) $meta->ultimaApuracao->valor_realizado,
            ] : null,
            'apuracoes_recentes' => $meta->apuracoes->map(fn ($a) => [
                'data_ref'        => $a->data_ref,
                'valor_realizado' => (float) $a->valor_realizado,
            ])->values(),
        ]);

        return Inertia::render('Copiloto/Dashboard', [
            'metas' => $metasTransformadas,
        ]);
    }
}
