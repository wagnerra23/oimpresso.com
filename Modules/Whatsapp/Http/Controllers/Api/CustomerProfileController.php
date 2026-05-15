<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Http\Controllers\Api;

use App\Contact;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\CustomerMemory;
use Modules\Whatsapp\Jobs\RebuildCustomerMemoryJob;
use Modules\Whatsapp\Services\CustomerMemory\CustomerMemoryRebuilder;

/**
 * US-WA-VOZ-001 — Endpoint Customer Profile (Sidebar Customer 360).
 *
 * `GET /atendimento/customer/{external_id}/profile`
 *
 * Retorna JSON consumido pelo componente `<CustomerSidebar>` (Inertia React)
 * com toda memória do cliente: identity, stats, inferências, contact CRM,
 * últimas conversas, e flag LGPD.
 *
 * Tier 0 multi-tenant: `business_id` resolvido via session() do user autenticado
 * (NÃO trusta query string). Acesso requer permission `whatsapp.access`.
 *
 * Auto-rebuild lazy: se customer_memory não existe ainda OU é stale,
 * dispatcha job em background. Endpoint sempre responde rápido com o
 * que tem (best-effort).
 *
 * @see Modules/Whatsapp/Services/CustomerMemory/CustomerMemoryRebuilder.php
 */
class CustomerProfileController extends Controller
{
    public function __construct(
        protected readonly CustomerMemoryRebuilder $rebuilder,
    ) {
    }

    /**
     * GET /atendimento/customer/{external_id}/profile
     *
     * Path param `external_id` é o E.164 sem '+' (ex: '5548999872822').
     * Sidebar passa o número canônico da Conversation.
     */
    public function show(Request $request, string $externalId): JsonResponse
    {
        $businessId = (int) ($request->session()->get('business.id') ?? session('business')?->id ?? 0);
        if ($businessId <= 0) {
            return response()->json(['error' => 'no_business_context'], 401);
        }

        $extId = ltrim(trim($externalId), '+');
        if ($extId === '' || ! preg_match('/^\d{8,20}$/', $extId)) {
            return response()->json(['error' => 'invalid_external_id'], 422);
        }

        $memory = CustomerMemory::query()
            ->withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $businessId)
            ->where('customer_external_id', $extId)
            ->first();

        // Lazy create if missing — dispatcha rebuild assíncrono
        if ($memory === null) {
            RebuildCustomerMemoryJob::dispatch($businessId, $extId, CustomerMemory::REBUILT_VIA_WEBHOOK);

            return response()->json([
                'state' => 'building',
                'message' => 'Memória sendo construída. Tente novamente em alguns segundos.',
                'customer_external_id' => $extId,
            ], 202);
        }

        // LGPD — cliente pediu erasure: retorna profile minimalista
        if ($memory->isErasureRequested()) {
            return response()->json([
                'state' => 'erasure_requested',
                'customer_external_id' => $extId,
                'erasure_requested_at' => $memory->erasure_requested_at->toIso8601String(),
                'message' => 'Cliente exerceu direito de apagamento (LGPD Art. 18).',
            ]);
        }

        // Stale (>24h) — refresca em background, mas responde com cache
        $staleHours = (int) config('whatsapp.customer_memory.rebuild_after_hours', 6);
        if ($memory->last_rebuilt_at === null
            || $memory->last_rebuilt_at->lt(now()->subHours($staleHours))) {
            RebuildCustomerMemoryJob::dispatch($businessId, $extId, CustomerMemory::REBUILT_VIA_WEBHOOK);
        }

        // Payload completo
        $contact = null;
        if ($memory->contact_id !== null) {
            $contact = Contact::query()
                ->withoutGlobalScope(ScopeByBusiness::class)
                ->where('business_id', $businessId)
                ->where('id', $memory->contact_id)
                ->first([
                    'id', 'name', 'email', 'cpf_cnpj', 'mobile', 'landline',
                    'alternate_number', 'address_line_1', 'city', 'state',
                    'whatsapp_consent', 'crm_source', 'crm_life_stage',
                    'customer_group_id', 'balance', 'credit_limit',
                ]);
        }

        $recentConversations = DB::table('conversations')
            ->where('business_id', $businessId)
            ->where(function ($q) use ($extId) {
                $q->where('customer_external_id', $extId)
                  ->orWhere('customer_external_id', '+' . $extId);
            })
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get([
                'id', 'channel_id', 'status', 'last_message_at',
                'last_message_preview', 'last_message_direction', 'unread_count',
                'created_at',
            ]);

        return response()->json([
            'state' => 'ok',
            'customer_external_id' => $extId,
            'memory' => [
                'id' => $memory->id,
                'display_name' => $memory->display_name,
                'phone_normalized' => $memory->phone_normalized,
                'identity' => [
                    'contact_id' => $memory->contact_id,
                    'method' => $memory->identity_match_method,
                    'confidence' => $memory->identity_match_confidence,
                    'matched_at' => optional($memory->identity_match_at)->toIso8601String(),
                ],
                'stats' => [
                    'n_conversations' => $memory->n_conversations,
                    'n_msgs_inbound' => $memory->n_msgs_inbound,
                    'n_msgs_outbound' => $memory->n_msgs_outbound,
                    'n_msgs_total' => $memory->n_msgs_total,
                    'first_interaction_at' => optional($memory->first_interaction_at)->toIso8601String(),
                    'last_interaction_at' => optional($memory->last_interaction_at)->toIso8601String(),
                    'days_since_last' => $memory->daysSinceLastInteraction(),
                ],
                'inferences' => [
                    'temas_recorrentes' => $memory->temas_recorrentes,
                    'sentimento_score' => $memory->sentimento_score,
                    'churn_risk_score' => $memory->churn_risk_score,
                    'comunicacao_preferida' => $memory->comunicacao_preferida,
                ],
                'notes' => [
                    'notas_jana' => $memory->notas_jana,
                    'atualizada_em' => optional($memory->notas_atualizada_em)->toIso8601String(),
                ],
                'flags' => $memory->flags ?? [],
                'consent' => [
                    'status' => $memory->consent_status,
                    'erasure_requested_at' => optional($memory->erasure_requested_at)->toIso8601String(),
                ],
                'last_rebuilt_at' => optional($memory->last_rebuilt_at)->toIso8601String(),
                'rebuilt_via' => $memory->rebuilt_via,
            ],
            'contact' => $contact,
            'recent_conversations' => $recentConversations,
        ]);
    }
}
