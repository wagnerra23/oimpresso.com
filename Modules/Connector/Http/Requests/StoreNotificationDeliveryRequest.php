<?php

declare(strict_types=1);

namespace Modules\Connector\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

/**
 * StoreNotificationDeliveryRequest — Wave 23 D8 SECURITY.
 *
 * FormRequest pro endpoint de registro de delivery de notificações WhatsApp/Email
 * vindas do daemon Baileys CT 100 (webhook → /api/connector/notification-delivery).
 * Hoje delivery é gravada inline em controller — esta classe formaliza o contrato.
 *
 * Casos de uso:
 *   - Daemon Baileys reporta status pra Hostinger via API
 *   - Asaas webhook delivery confirmation
 *   - Email bounce-back report
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093):
 *   - business_id resolvido via authenticated Passport user (`$user->business_id`)
 *   - NÃO aceita business_id no payload (anti-spoofing — Tier 0)
 *   - external_message_id é per-business unique (FK natural)
 *
 * Custo IA tracking: NÃO se aplica (delivery webhook não chama LLM).
 * Latência: ~50ms adicional pra validação + DB insert.
 *
 * @see Modules\Connector\Http\Requests\StoreContactApiRequest (pattern referência)
 * @see Modules\Whatsapp\Http\Controllers\* (ingest pattern)
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
class StoreNotificationDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Passport auth via middleware `auth:api`; defesa em profundidade
        // garante user + business_id presentes pra anti-spoofing Tier 0.
        $user = Auth::user();

        return $user !== null && ! empty($user->business_id);
    }

    public function rules(): array
    {
        return [
            // channel: canal de delivery (whatsapp_baileys|whatsapp_meta|email|sms)
            'channel' => ['required', 'string', 'in:whatsapp_baileys,whatsapp_meta,email,sms'],

            // status: estado canônico da entrega
            'status' => ['required', 'string', 'in:queued,sent,delivered,read,failed,banned'],

            // external_message_id: ID do provider (Baileys/Asaas/Meta) — anti-duplicate
            'external_message_id' => ['required', 'string', 'max:255'],

            // recipient: número/email destino (validation leve — provider valida deep)
            'recipient' => ['required', 'string', 'max:255'],

            // delivered_at: timestamp (ISO 8601 opcional — default now())
            'delivered_at' => ['nullable', 'date'],

            // error_code: código de falha do provider (banido, fora-do-ar, etc)
            'error_code' => ['nullable', 'string', 'max:120'],

            // error_message: descrição livre (sem PII por contrato — provider responsabilidade)
            'error_message' => ['nullable', 'string', 'max:500'],

            // metadata: payload provider raw (sem PII tier 0 — caller normaliza upstream)
            'metadata' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'channel.required' => 'Canal obrigatório (whatsapp_baileys|whatsapp_meta|email|sms).',
            'channel.in'       => 'Canal inválido — use um dos suportados.',
            'status.required'  => 'Status obrigatório.',
            'status.in'        => 'Status inválido (queued|sent|delivered|read|failed|banned).',
            'external_message_id.required' => 'external_message_id obrigatório (anti-duplicate).',
            'recipient.required' => 'Recipient obrigatório.',
            'delivered_at.date' => 'delivered_at deve ser ISO 8601 válido.',
        ];
    }
}
