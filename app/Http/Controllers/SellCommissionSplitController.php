<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Transaction;
use App\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Onda 2 follow-up — Editor UI de `commission_split` (ADR 0192).
 *
 * Endpoint dedicado `PATCH /sells/{id}/commission-split` que persiste apenas o
 * campo `transactions.commission_split` (JSON shape canon abaixo). Mantém SoC
 * brutal — não cruza com `SellPosController::update` (que é ~600 LOC e cobre
 * o fluxo POS completo). Aceita também `null` pra limpar o split (venda direta
 * sem comissão).
 *
 * Shape canon (ADR 0192):
 *   {
 *     "mecanico_id": int,           // FK users.id (business-scoped) · obrigatório
 *     "mecanico_pct": float,        // 0-100
 *     "balcao_id": int | null,      // FK users.id (business-scoped) · null quando 100% mecânico
 *     "balcao_pct": float           // 0-100 · soma com mecanico_pct === 100
 *   }
 *
 * Multi-tenant Tier 0 ADR 0093:
 *   - Transaction scoped via where('business_id', session('user.business_id'))
 *   - Validation custom valida que mecanico_id + balcao_id pertencem ao mesmo
 *     business (users.business_id) — bloqueia cross-tenant injection
 *   - Audit log via Spatie ActivityLog (já configurado em Transaction model)
 *
 * Permissão: `sell.update` (mesmo do fluxo POS update).
 *
 * @see memory/decisions/0192-auto-faturar-os-venda-jobsheet-observer.md
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
class SellCommissionSplitController extends Controller
{
    /**
     * PATCH /sells/{id}/commission-split
     *
     * Body:
     *   { "commission_split": { mecanico_id, mecanico_pct, balcao_id, balcao_pct } | null }
     *
     * Respostas:
     *   - 200 + { success: 1, commission_split: <persisted-value> }
     *   - 422 + { errors: ... } (validação)
     *   - 403 (sem permissão)
     *   - 404 (transaction inexistente OU outro business)
     */
    public function update(Request $request, int $id): JsonResponse
    {
        if (! auth()->user()->can('sell.update')) {
            abort(403, 'Sem permissão pra editar comissão da venda.');
        }

        $businessId = (int) $request->session()->get('user.business_id');

        // Tier 0 multi-tenant ADR 0093 — scope rígido pelo business da sessão.
        $transaction = Transaction::where('business_id', $businessId)
            ->whereIn('type', ['sell', 'sales_order'])
            ->findOrFail($id);

        $rawInput = $request->input('commission_split');

        // Aceita NULL explícito pra limpar (venda direta sem split).
        if ($rawInput === null) {
            $transaction->commission_split = null;
            $transaction->save();

            Log::info('commission_split limpado', [
                'transaction_id' => $transaction->id,
                'business_id' => $businessId,
                'actor_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => 1,
                'commission_split' => null,
                'msg' => 'Split de comissão removido.',
            ]);
        }

        $payload = $this->validateSplit($request, $businessId);

        $transaction->commission_split = $payload;
        $transaction->save();

        Log::info('commission_split atualizado', [
            'transaction_id' => $transaction->id,
            'business_id' => $businessId,
            'actor_id' => auth()->id(),
            'mecanico_id' => $payload['mecanico_id'],
            'balcao_id' => $payload['balcao_id'],
        ]);

        return response()->json([
            'success' => 1,
            'commission_split' => $payload,
            'msg' => 'Split de comissão salvo.',
        ]);
    }

    /**
     * Validação canon — shape + total=100 + multi-tenant ownership.
     *
     * @throws ValidationException
     */
    private function validateSplit(Request $request, int $businessId): array
    {
        // Garante que ids referenciados pertencem ao mesmo business — Tier 0 ADR 0093.
        $userExistsInBusiness = Rule::exists('users', 'id')->where(fn ($q) => $q->where('business_id', $businessId));

        $validated = $request->validate([
            'commission_split' => ['required', 'array'],
            'commission_split.mecanico_id' => ['required', 'integer', $userExistsInBusiness],
            'commission_split.mecanico_pct' => ['required', 'numeric', 'between:0,100'],
            'commission_split.balcao_id' => ['nullable', 'integer', $userExistsInBusiness],
            'commission_split.balcao_pct' => ['required', 'numeric', 'between:0,100'],
        ], [
            'commission_split.mecanico_id.exists' => 'Mecânico não pertence a este business.',
            'commission_split.balcao_id.exists' => 'Balconista não pertence a este business.',
        ]);

        $split = $validated['commission_split'];

        // Regra de negócio (ADR 0192): total === 100, com tolerância 0.01 pra floats.
        $total = (float) $split['mecanico_pct'] + (float) $split['balcao_pct'];
        if (abs($total - 100.0) > 0.01) {
            throw ValidationException::withMessages([
                'commission_split.balcao_pct' => "Total da comissão deve somar 100% (atual: {$total}%).",
            ]);
        }

        // Regra de negócio (ADR 0192): balcao_id NULL quando 100% mecânico.
        $mecanicoPct = (float) $split['mecanico_pct'];
        $balcaoPct = (float) $split['balcao_pct'];

        if ($mecanicoPct >= 100.0 - 0.01 && $balcaoPct > 0.01) {
            throw ValidationException::withMessages([
                'commission_split.balcao_pct' => 'Quando mecânico tem 100%, balcão deve ser 0.',
            ]);
        }

        if ($mecanicoPct < 100.0 - 0.01 && ($split['balcao_id'] === null || $balcaoPct <= 0.01)) {
            throw ValidationException::withMessages([
                'commission_split.balcao_id' => 'Quando mecânico tem menos de 100%, informe um balconista.',
            ]);
        }

        // Mecânico e balcão não podem ser a mesma pessoa.
        if ($split['balcao_id'] !== null && (int) $split['mecanico_id'] === (int) $split['balcao_id']) {
            throw ValidationException::withMessages([
                'commission_split.balcao_id' => 'Mecânico e balconista não podem ser a mesma pessoa.',
            ]);
        }

        // Normalize shape canon (cast tipos consistentes ADR 0192).
        return [
            'mecanico_id' => (int) $split['mecanico_id'],
            'mecanico_pct' => round($mecanicoPct, 2),
            'balcao_id' => $split['balcao_id'] !== null ? (int) $split['balcao_id'] : null,
            'balcao_pct' => round($balcaoPct, 2),
        ];
    }
}
