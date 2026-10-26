<?php

namespace Modules\Marketplaces\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;

/**
 * Notificação recebida de um marketplace (mkt_webhook_log). APPEND-ONLY: o trigger MySQL recusa
 * UPDATE e DELETE. Processar a notificação grava linha nova apontando `superseded_id` para a
 * recebida.
 */
class WebhookRecebido extends Model
{
    use HasBusinessScope;

    public const PENDENTE = 'pending';
    public const PROCESSADO = 'processed';
    public const FALHOU = 'failed';
    public const IGNORADO = 'skipped';

    protected $table = 'mkt_webhook_log';

    protected $fillable = [
        'business_id', 'marketplace_id', 'topic', 'resource_external', 'payload',
        'signature_valid', 'processing_status', 'error_message', 'superseded_id', 'received_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'signature_valid' => 'boolean',
        'received_at' => 'datetime',
    ];
}
