<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Models;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Log/idempotência de webhooks PIX recebidos do Banco Inter.
 *
 * US-FIN-032 (Onda 26) — ADR 0170 ciclo dogfooding fechado.
 *
 * Status values:
 *   - `received`              — webhook recebido + signature válida, worker enfileirado
 *   - `processed`             — titulo encontrado + marcado quitado (FSM) + baixa criada
 *   - `titulo_nao_encontrado` — cobranca por txid não existe (log warning + 200 OK pro Inter)
 *   - `erro_fsm`              — FSM transition lançou UnauthorizedActionException
 *   - `erro_outro`            — exceção genérica no worker
 *
 * Multi-tenant Tier 0 (ADR 0093) — global scope via HasBusinessScope.
 *
 * SEM LogsActivity — esta tabela JÁ É audit log (append-only por convenção;
 * só `status`, `processed_at`, `error_message`, `cobranca_id`, `titulo_id`,
 * `gateway_webhook_event_id` podem ser UPDATE via worker).
 */
class InterWebhookLog extends Model
{
    use HasBusinessScope;

    protected $table = 'inter_webhook_log';

    protected $fillable = [
        'business_id',
        'payment_gateway_credential_id',
        'txid',
        'endToEndId',
        'cobranca_id',
        'titulo_id',
        'gateway_webhook_event_id',
        'valor_centavos',
        'payer_cpf_cnpj_redacted',
        'data_pagamento',
        'signature_valid',
        'status',
        'payload',
        'error_message',
        'processed_at',
    ];

    protected $casts = [
        'payload'         => 'array',
        'signature_valid' => 'boolean',
        'valor_centavos'  => 'integer',
        'data_pagamento'  => 'datetime',
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

    public function gatewayWebhookEvent(): BelongsTo
    {
        return $this->belongsTo(GatewayWebhookEvent::class, 'gateway_webhook_event_id');
    }
}
