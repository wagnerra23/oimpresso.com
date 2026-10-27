<?php

namespace Modules\Connector\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uma entrega de webhook de saída (US-CONN-021): o que foi enviado, para qual destino, quantas
 * tentativas e com que resultado. O `entrega_uuid` vai no cabeçalho para o destino deduplicar.
 *
 * @property int $id
 * @property int $business_id
 * @property int $destino_id
 * @property string $entrega_uuid
 * @property string $evento
 * @property int|null $recurso_id
 * @property array<string, mixed> $payload
 * @property string $status
 * @property int $tentativas
 * @property int|null $http_status
 * @property int|null $duracao_ms
 * @property string|null $ultimo_erro
 * @property \Illuminate\Support\Carbon|null $entregue_em
 * @property-read WebhookDestino|null $destino
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

    /** @return BelongsTo<WebhookDestino, $this> */
    public function destino(): BelongsTo
    {
        return $this->belongsTo(WebhookDestino::class, 'destino_id');
    }
}
