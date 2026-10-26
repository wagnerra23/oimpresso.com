<?php

namespace Modules\Connector\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uma entrega de webhook de saída (US-CONN-021): o que foi enviado, para qual destino, quantas
 * tentativas e com que resultado. O `entrega_uuid` vai no cabeçalho para o destino deduplicar.
 */
class WebhookEntrega extends Model
{
    use HasBusinessScope;

    public const PENDENTE = 'pendente';
    public const ENTREGUE = 'entregue';
    public const FALHOU = 'falhou';

    protected $table = 'connector_webhook_entregas';

    protected $fillable = [
        'business_id', 'destino_id', 'entrega_uuid', 'evento', 'recurso_id', 'payload', 'status',
        'tentativas', 'http_status', 'duracao_ms', 'ultimo_erro', 'entregue_em',
    ];

    protected $casts = [
        'payload' => 'array',
        'entregue_em' => 'datetime',
    ];

    public function destino(): BelongsTo
    {
        return $this->belongsTo(WebhookDestino::class, 'destino_id');
    }
}
