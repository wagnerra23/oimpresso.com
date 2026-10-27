<?php

namespace Modules\Connector\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;

/**
 * Destino de webhook de saída de uma empresa (US-CONN-021). O segredo assina cada entrega
 * (HMAC-SHA256); fica cifrado no banco e fora do JSON.
 *
 * @property int $id
 * @property int $business_id
 * @property string $url
 * @property string $segredo
 * @property array<int, string>|null $eventos
 * @property bool $ativo
 * @property int|null $created_by
 */
class WebhookDestino extends Model
{
    use HasBusinessScope;

    protected $table = 'connector_webhook_destinos';

    protected $fillable = ['business_id', 'url', 'segredo', 'eventos', 'ativo', 'created_by'];

    protected $hidden = ['segredo'];

    protected $casts = [
        'segredo' => 'encrypted',
        'eventos' => 'array',
        'ativo' => 'boolean',
    ];

    public function assina(string $evento): bool
    {
        return $this->ativo && in_array($evento, (array) $this->eventos, true);
    }
}
