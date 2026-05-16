<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Http\Controllers;

use App\Domain\Fsm\Exceptions\UnauthorizedActionException;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Modules\NfeBrasil\Http\Requests\CancelarNfeRequest;
use Modules\NfeBrasil\Models\NfeInutilizacao;
use Modules\NfeBrasil\Services\NfeInutilizacaoService;

/**
 * US-SELL-030 — Endpoint admin pra inutilização de faixa fiscal NFe.
 *
 * Quando emissão `rejeitada/denegada/erro_envio` deixa "buraco" no sequencial
 * fiscal (número pego mas não autorizado), inutilização SEFAZ formal é o
 * único caminho legal pra fechar o ano fiscal sem multa.
 *
 * Endpoints:
 *   GET  /nfe-brasil/inutilizacoes              — lista inutilizações do business
 *   POST /nfe-brasil/inutilizacoes              — dispara inutilização SEFAZ
 *
 * Permissão: `fiscal.inutilizar` (role per-business — seeder NfeFiscalActionsSeeder).
 *
 * Multi-tenant Tier 0 (ADR 0093):
 *   - business_id sempre da sessão (nunca request param)
 *   - Service tem cross-tenant guard (defesa em profundidade)
 *
 * Refs:
 *   - SPEC.md US-SELL-030
 *   - CONFAZ Ajuste SINIEF 07/2005 Art. 14
 */
class NfeInutilizacaoController extends Controller
{
    public function __construct(
        private readonly NfeInutilizacaoService $service,
    ) {}

    /**
     * GET /nfe-brasil/inutilizacoes
     *
     * Lista inutilizações do business autenticado (paginadas, mais recentes primeiro).
     * Filtros opcionais: ?modelo=55&serie=1&status=autorizado.
     */
    public function index(Request $request): JsonResponse
    {
        $businessId = (int) $request->session()->get('business.id', 0);
        if ($businessId === 0) {
            return response()->json(['error' => 'no_business_context'], 400);
        }

        $query = NfeInutilizacao::where('business_id', $businessId);

        if ($modelo = $request->query('modelo')) {
            $query->where('modelo', (string) $modelo);
        }
        if ($serie = $request->query('serie')) {
            $query->where('serie', (string) $serie);
        }
        if ($status = $request->query('status')) {
            $query->where('status', (string) $status);
        }

        $items = $query->orderByDesc('id')->paginate(50);

        return response()->json([
            'data' => $items->getCollection()->map(fn (NfeInutilizacao $i) => $this->serialize($i)),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    /**
     * POST /nfe-brasil/inutilizacoes
     *
     * Dispara inutilização SEFAZ pra uma faixa de números.
     *
     * Body:
     *   modelo:        '55' | '65'
     *   serie:         string (1-3 chars)
     *   numero_de:     int >= 1
     *   numero_ate:    int >= numero_de
     *   justificativa: string 15-255 chars (regra SEFAZ ABRASF)
     *
     * Service valida tudo. Erros mapeados pra HTTP:
     *   InvalidArgumentException → 422
     *   UnauthorizedActionException → 403
     *   Falha SEFAZ (RuntimeException) → 502 (Bad Gateway)
     */
    public function store(CancelarNfeRequest $request): JsonResponse
    {
        $businessId = (int) $request->session()->get('business.id', 0);
        if ($businessId === 0) {
            return response()->json(['error' => 'no_business_context'], 400);
        }

        $validated = $request->validated();

        try {
            $inut = $this->service->inutilizar(
                businessId: $businessId,
                modelo: $validated['modelo'],
                serie: $validated['serie'],
                numeroDe: $validated['numero_de'],
                numeroAte: $validated['numero_ate'],
                justificativa: $validated['justificativa'],
            );
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'error' => 'validation_failed',
                'message' => $e->getMessage(),
            ], 422);
        } catch (UnauthorizedActionException $e) {
            Log::warning('[NfeInutilizacao] cross-tenant attempt bloqueado', [
                'business_id_session' => $businessId,
                'erro' => $e->getMessage(),
            ]);
            return response()->json([
                'error' => 'unauthorized',
                'message' => $e->getMessage(),
            ], 403);
        } catch (\Throwable $e) {
            Log::error('[NfeInutilizacao] falha SEFAZ', [
                'business_id' => $businessId,
                'modelo' => $validated['modelo'],
                'serie' => $validated['serie'],
                'numero_de' => $validated['numero_de'],
                'numero_ate' => $validated['numero_ate'],
                'erro' => $e->getMessage(),
            ]);
            return response()->json([
                'error' => 'sefaz_failure',
                'message' => $e->getMessage(),
            ], 502);
        }

        return response()->json([
            'inutilizacao' => $this->serialize($inut),
            'message' => $inut->status === 'autorizado'
                ? "Faixa [{$inut->numero_de}..{$inut->numero_ate}] inutilizada com sucesso (SEFAZ cstat={$inut->cstat})."
                : "SEFAZ rejeitou inutilização (cstat={$inut->cstat}). Veja payload_json pra detalhes.",
        ], $inut->status === 'autorizado' ? 201 : 422);
    }

    private function serialize(NfeInutilizacao $i): array
    {
        return [
            'id' => $i->id,
            'business_id' => $i->business_id,
            'modelo' => $i->modelo,
            'serie' => $i->serie,
            'numero_de' => $i->numero_de,
            'numero_ate' => $i->numero_ate,
            'quantidade_numeros' => $i->quantidadeNumeros(),
            'justificativa' => $i->justificativa,
            'status' => $i->status,
            'cstat' => $i->cstat,
            'autorizada_em' => $i->autorizada_em?->toIso8601String(),
            'created_at' => $i->created_at?->toIso8601String(),
        ];
    }
}
