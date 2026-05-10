<?php

namespace Modules\ComunicacaoVisual\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\ComunicacaoVisual\Entities\Apontamento;
use Modules\ComunicacaoVisual\Services\ApontamentoTracker;
use RuntimeException;

/**
 * ApontamentoController — API JSON de apontamentos de produção (spool plotter).
 *
 * Sprint 1 — US-COMVIS-004: operador balcão registra início/fim de produção em uma OS.
 *
 * Endpoints:
 *   GET  /comunicacao-visual/api/apontamentos                  → index (list paginado)
 *   POST /comunicacao-visual/api/apontamentos/iniciar          → inicia apontamento
 *   POST /comunicacao-visual/api/apontamentos/{id}/finalizar   → finaliza
 *   POST /comunicacao-visual/api/apontamentos/{id}/cancelar    → cancela
 *   GET  /comunicacao-visual/api/apontamentos/em-andamento     → ativo do user atual
 *
 * operador_id é resolvido de auth()->id() — nunca do body da request.
 *
 * Multi-tenant Tier 0 ([ADR 0093](memory/decisions/0093-multi-tenant-isolation-tier-0.md)):
 * business_id resolvido via session('user.business_id') — nunca do input.
 *
 * @see Modules\ComunicacaoVisual\Services\ApontamentoTracker
 * @see memory/requisitos/ComunicacaoVisual/SPEC.md US-COMVIS-004
 */
class ApontamentoController extends Controller
{
    public function __construct(
        private readonly ApontamentoTracker $tracker
    ) {}

    // ------------------------------------------------------------------
    // GET /comunicacao-visual/api/apontamentos
    // Lista paginada com filtros opcionais (os_id, operador_id, data_inicio, data_fim)
    // ------------------------------------------------------------------

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'os_id'       => ['nullable', 'integer', 'min:1'],
            'operador_id' => ['nullable', 'integer', 'min:1'],
            'data_inicio' => ['nullable', 'date'],
            'data_fim'    => ['nullable', 'date', 'after_or_equal:data_inicio'],
        ]);

        $query = Apontamento::with(['os', 'orcamentoItem', 'operador'])
            ->orderByDesc('iniciado_em');

        if ($request->filled('os_id')) {
            $query->where('comvis_apontamentos.os_id', $request->integer('os_id'));
        }

        if ($request->filled('operador_id')) {
            $query->where('comvis_apontamentos.operador_id', $request->integer('operador_id'));
        }

        if ($request->filled('data_inicio')) {
            $query->where('comvis_apontamentos.iniciado_em', '>=', $request->input('data_inicio') . ' 00:00:00');
        }

        if ($request->filled('data_fim')) {
            $query->where('comvis_apontamentos.iniciado_em', '<=', $request->input('data_fim') . ' 23:59:59');
        }

        $apontamentos = $query->paginate(25);

        return response()->json($apontamentos, 200);
    }

    // ------------------------------------------------------------------
    // POST /comunicacao-visual/api/apontamentos/iniciar
    // Inicia novo apontamento (operador_id = auth user — não enviado no body)
    // ------------------------------------------------------------------

    public function iniciar(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'os_id'             => ['required', 'integer', 'min:1', 'exists:comvis_os,id'],
            'orcamento_item_id' => ['nullable', 'integer', 'min:1', 'exists:comvis_orcamento_itens,id'],
            'maquina'           => ['nullable', 'string', 'max:80'],
        ], [
            'os_id.required' => 'A OS é obrigatória.',
            'os_id.exists'   => 'OS não encontrada.',
            'maquina.max'    => 'O nome da máquina deve ter no máximo 80 caracteres.',
        ]);

        try {
            $apontamento = $this->tracker->iniciar(
                osId:            $validated['os_id'],
                operadorId:      auth()->id(),
                orcamentoItemId: $validated['orcamento_item_id'] ?? null,
                maquina:         $validated['maquina'] ?? null,
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($apontamento->load(['os', 'orcamentoItem']), 201);
    }

    // ------------------------------------------------------------------
    // POST /comunicacao-visual/api/apontamentos/{apontamento}/finalizar
    // ------------------------------------------------------------------

    public function finalizar(Request $request, int $apontamento): JsonResponse
    {
        $validated = $request->validate([
            'm2_produzido' => ['required', 'numeric', 'gte:0'],
            'observacoes'  => ['nullable', 'string', 'max:500'],
        ], [
            'm2_produzido.required' => 'Informe os m² produzidos.',
            'm2_produzido.gte'      => 'Os m² produzidos não podem ser negativos.',
        ]);

        try {
            $result = $this->tracker->finalizar(
                apontamentoId: $apontamento,
                m2Produzido:   (float) $validated['m2_produzido'],
                observacoes:   $validated['observacoes'] ?? null,
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($result, 200);
    }

    // ------------------------------------------------------------------
    // POST /comunicacao-visual/api/apontamentos/{apontamento}/cancelar
    // ------------------------------------------------------------------

    public function cancelar(Request $request, int $apontamento): JsonResponse
    {
        $validated = $request->validate([
            'motivo' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'motivo.required' => 'O motivo do cancelamento é obrigatório.',
            'motivo.min'      => 'O motivo deve ter pelo menos 5 caracteres.',
        ]);

        try {
            $result = $this->tracker->cancelar(
                apontamentoId: $apontamento,
                motivo:        $validated['motivo'],
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($result, 200);
    }

    // ------------------------------------------------------------------
    // GET /comunicacao-visual/api/apontamentos/em-andamento
    // Retorna apontamento ativo do usuário autenticado (helper widget UI)
    // ------------------------------------------------------------------

    public function emAndamento(): JsonResponse
    {
        $ativo = $this->tracker->emAndamento(auth()->id());

        if ($ativo === null) {
            return response()->json(null, 200);
        }

        return response()->json($ativo, 200);
    }
}
