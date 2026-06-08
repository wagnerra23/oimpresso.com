<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Models;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Log de eventos de webhook recebidos.
 *
 * Função principal: IDEMPOTÊNCIA — (business_id, gateway_key,
 * gateway_event_id) UNIQUE. Webhook duplicado é detectado pelo banco
 * antes de processar.
 *
 * Multi-tenant Tier 0 — global scope via HasBusinessScope (ADR 0093).
 *
 * SEM LogsActivity — esta tabela JÁ É audit log (registra TUDO que chegou
 * do gateway). Append-only por convenção; só `processed_at` + `error_message`
 * podem ser atualizados via UPDATE.
 *
 * ADR 0170 Onda 2.
 */
class GatewayWebhookEvent extends Model
{
    use HasBusinessScope;

    protected $table = 'gateway_webhook_events';

    protected $fillable = [
        'business_id',
        'payment_gateway_credential_id',
        'gateway_key',
        'evento',
        'gateway_event_id',
        'cobranca_id',
        'payload',
        'signature_valid',
        'processed_at',
        'error_message',
    ];

    protected $casts = [
        'payload'         => 'array',
        'signature_valid' => 'boolean',
        'processed_at'    => 'datetime',
    ];

    public function credential(): BelongsTo
    {
        return $this->belongsTo(PaymentGatewayCredential::class, 'payment_gateway_credential_id');
    }

    public function cobranca(): BelongsTo
    {
        return $this->belongsTo(Cobranca::class, 'cobranca_id');
    }
}
