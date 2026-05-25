<?php

namespace Modules\Jana\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\Jana\Entities\Meta;

/**
 * Dashboard de metas ativas com sparkline + farol.
 * Renderiza Inertia Page 'Copiloto/Dashboard' (ver adr/ui/0001).
 */
class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $businessId = $request->session()->get('user.business_id');

        // Wagner 2026-05-25: Dashboard é PRIMEIRA ABA canon da Jana + destino
        // pós-login (`/home → /ia/dashboard`). Empty state já implementado em
        // Dashboard.tsx (linha 296) com CTA "Pergunte algo a Jana". O redirect
        // antigo "sem metas → chat" criava loop UX: login → /ia/dashboard →
        // bounce pra /ia (chat). Removido — frontend renderiza empty card.

        // Wagner 2026-05-25 HOTFIX pós-PR #1547: removido `Inertia::defer` no
        // payload `metas`. Causa: Dashboard.tsx (linha 279/296/318) lê
        // `metas.length` direto sem wrap `<Deferred>` nem fallback — defer
        // entrega `undefined` no primeiro render e quebra com TypeError em
        // prod. Bug pré-existia mascarado pelo redirect "sem metas → chat".
        // Reintroduzir defer quando frontend ganhar `<Deferred data="metas"
        // fallback={<Skeleton />}>` wrap (refactor MWART futuro).
        return Inertia::render('Jana/Dashboard', [
            'metas' => $this->buildMetasPayload($businessId),
        ]);
    }

    /**
     * D6.a defer closure — hidrata metas ativas do business com eager loads.
     * Multi-tenant Tier 0: filtra por business_id (ou repo-wide null) — ADR 0093.
     */
    protected function buildMetasPayload(int $businessId): \Illuminate\Support\Collection
    {
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

        return $metas->map(fn ($meta) => [
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
    }
}
