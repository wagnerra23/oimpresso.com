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

        // Pré-check rápido (count) pra decidir redirect antes de hidratar payload.
        // Anti-pattern evitado: get() completo só pra ver isEmpty() — desperdiça
        // a query pesada com eager loads quando user não tem meta ativa.
        $temMetas = Meta::where('ativo', true)
            ->where(function ($q) use ($businessId) {
                $q->where('business_id', $businessId)
                  ->orWhereNull('business_id');
            })
            ->exists();

        if (! $temMetas) {
            return redirect()->route('jana.chat.index')
                ->with('status', 'Nenhuma meta ativa. Converse com o Copiloto pra criar a primeira.');
        }

        // D6.a (Wave 14 governance v3) — Inertia::defer no payload `metas`.
        // Eager loads (periodoAtual + ultimaApuracao + apuracoes×12) podem
        // ficar custosos com N metas. Closure só executa em segundo round
        // partial-reload, mantendo TTFB inicial baixo e exibindo skeleton.
        // Multi-tenant scope preservado dentro do closure (business_id capturado).
        return Inertia::render('Jana/Dashboard', [
            'metas' => Inertia::defer(fn () => $this->buildMetasPayload($businessId)),
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
