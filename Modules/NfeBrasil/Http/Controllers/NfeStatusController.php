<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\NfeBrasil\Models\NfeEmissao;

/**
 * US-NFE-002 fase 2C · Endpoint JSON pra polling de status NFC-e pós-venda.
 *
 * **Por que polling em vez de broadcast (Centrifugo/Reverb):**
 *   - ADR 0058 + 0062: Hostinger NÃO roda daemons (Reverb/Centrifugo)
 *   - Centrifugo vive no CT 100, mas integração HTTP-bridge precisa decisão
 *     arquitetural separada (Wagner)
 *   - Polling 2s no front cobre US-NFE-002 AC #5 ("toast pós-emissão") sem
 *     bloquear shipping; UI escuta endpoint, troca pra channel real depois
 *     sem refazer componente (hook React abstrai a fonte)
 *
 * Endpoint: `GET /nfe-brasil/api/transactions/{tx}/nfe-status`
 *
 * Auth: stack web normal (auth + business scope). Cross-tenant guard valida
 * `transaction.business_id === session('business.id')`. Sem auth → 401 do
 * middleware. Cross-tenant ou tx inexistente → 404.
 *
 * Response (ainda emitindo / não emitida):
 * ```json
 * { "transaction_id": 12345, "status": null, "message": "..." }
 * ```
 *
 * Response (emitida):
 * ```json
 * {
 *   "transaction_id": 12345,
 *   "status": "autorizada",  // pendente | autorizada | rejeitada | denegada
 *   "modelo": "65",
 *   "cstat": "100",
 *   "chave_44": "352101...19",
 *   "numero": 42,
 *   "serie": "1",
 *   "motivo": null,
 *   "valor_total": 100.0,
 *   "emitido_em": "2026-05-07T20:00:00Z"
 * }
 * ```
 *
 * Front polling cadence: 2s × 30 max = 1 min total (cobre p99 SEFAZ ~30s).
 * Quando status === 'autorizada' OU 'rejeitada' OU 'denegada', UI para o
 * polling — esses são estados terminais.
 */
class NfeStatusController extends Controller
{
    /**
     * GET /nfe-brasil/api/transactions/{tx}/nfe-status
     *
     * Retorna status atual da emissão NFC-e (modelo 65) pra a transaction.
     */
    public function show(Request $request, int $tx): JsonResponse
    {
        $businessId = (int) $request->session()->get('business.id', 0);
        if ($businessId === 0) {
            return response()->json(['error' => 'no_business_context'], 400);
        }

        // Cross-tenant guard: emissao deve pertencer ao business do usuário logado.
        $emissao = NfeEmissao::where('business_id', $businessId)
            ->where('transaction_id', $tx)
            ->where('modelo', 65)
            ->orderByDesc('id') // se houver múltiplas (retentativas), pega a mais recente
            ->first();

        if (! $emissao) {
            return response()->json([
                'transaction_id' => $tx,
                'status'         => null,
                'message'        => 'NFC-e ainda não foi emitida pra essa venda.',
            ]);
        }

        return response()->json([
            'transaction_id' => $tx,
            'emissao_id'     => $emissao->id,
            'status'         => $emissao->status,
            'modelo'         => (string) $emissao->modelo,
            'cstat'          => $emissao->cstat,
            'chave_44'       => $emissao->chave_44,
            'numero'         => $emissao->numero,
            'serie'          => $emissao->serie,
            'motivo'         => $emissao->motivo,
            'valor_total'    => (float) $emissao->valor_total,
            'emitido_em'     => optional($emissao->emitido_em)->toIso8601String(),
            // Estados terminais — UI usa pra parar polling:
            'is_terminal'    => in_array($emissao->status, ['autorizada', 'rejeitada', 'denegada'], true),
        ]);
    }

    /**
     * GET /nfe-brasil/transactions/{tx}/status
     *
     * Page Inertia demo: badge reativo de status NFC-e via polling do hook
     * `useNfceStatus`. Útil pra usuário consultar status de uma venda já
     * finalizada e pra dogfooding da PR D antes da integração na Blade POS.
     *
     * Acessível via stack web normal — não exige permissão extra (visualizar
     * status de própria venda do tenant).
     */
    public function showPage(Request $request, int $tx): Response
    {
        return Inertia::render('NfeBrasil/Transactions/NfceStatus', [
            'transaction_id' => $tx,
        ]);
    }
}
