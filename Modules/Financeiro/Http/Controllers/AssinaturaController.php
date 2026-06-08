<?php

declare(strict_types=1);

namespace Modules\Financeiro\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Modules\RecurringBilling\Http\Requests\UpdateAssinaturaRequest;
use Modules\RecurringBilling\Models\Subscription;
use Modules\RecurringBilling\Services\AssinaturaCobrancaService;

/**
 * FIN-004 — Atualizar cobranca de assinatura recorrente.
 *
 * Controller thin HTTP-only. Toda logica de gateway / persistencia /
 * recalculo de proxima cobranca vive em AssinaturaCobrancaService (SoC brutal
 * Constituicao v2 §5).
 *
 * Multi-tenant Tier 0 (ADR 0093): businessId SEMPRE da session, NUNCA do request.
 * Permissao: recurringbilling.assinatura.update (skill multi-tenant-patterns).
 *
 * Cuidado: este Controller serve cliente piloto ROTA LIVRE (biz=4) em prod.
 * Toda alteracao requer canary + aprovacao Wagner (publication-policy).
 */
class AssinaturaController extends Controller
{
    public function __construct(
        private readonly AssinaturaCobrancaService $cobrancas,
    ) {}

    /**
     * GET /financeiro/assinaturas/atualizar
     *
     * UI minimal pra Wagner/admin selecionar assinatura ativa e editar
     * valor / ciclo / forma_pagamento. Lista vem scoped por business_id
     * via HasBusinessScope (global scope ADR 0093).
     */
    public function showAtualizar(): InertiaResponse
    {
        $assinaturas = Subscription::query()
            ->ativas()
            ->with('plan')
            ->orderBy('id', 'desc')
            ->limit(50)
            ->get(['id', 'plan_id', 'contact_id', 'status', 'next_due_date', 'metadata']);

        return Inertia::render('Financeiro/AssinaturaAtualizar', [
            'assinaturas' => $assinaturas->map(fn ($s) => [
                'id' => $s->id,
                'plano' => $s->plan?->name,
                'status' => $s->status,
                'next_due_date' => $s->next_due_date?->toDateString(),
                'valor_atual' => $s->metadata['valor'] ?? $s->plan?->valor,
                'ciclo_atual' => $s->metadata['ciclo'] ?? $s->plan?->ciclo,
                'forma_pagamento_atual' => $s->metadata['forma_pagamento'] ?? 'boleto',
            ])->all(),
        ]);
    }

    /**
     * PATCH /financeiro/assinaturas/{assinatura}
     *
     * Atualiza valor / ciclo / forma_pagamento. Cuidado biz=4 prod —
     * controller log NUNCA imprime valor real, apenas IDs/flags.
     */
    public function atualizar(UpdateAssinaturaRequest $request, int $assinatura): JsonResponse
    {
        if (! auth()->user()?->can('recurringbilling.assinatura.update')) {
            return response()->json([
                'ok' => false,
                'error' => 'Sem permissao recurringbilling.assinatura.update',
            ], 403);
        }

        $businessId = (int) $request->session()->get('business.id');

        $result = $this->cobrancas->atualizarCobrancaAssinatura(
            $businessId,
            $assinatura,
            $request->validated(),
        );

        if ($result['ok']) {
            return response()->json(array_filter([
                'ok' => true,
                'gateway_call' => $result['gateway_call'] ?? false,
                'skipped' => $result['skipped'] ?? null,
                'gateway_warning' => $result['gateway_warning'] ?? null,
            ], fn ($v) => $v !== null));
        }

        return response()->json([
            'ok' => false,
            'error' => $result['error'] ?? 'Erro desconhecido.',
        ], $result['http_status'] ?? 500);
    }
}
