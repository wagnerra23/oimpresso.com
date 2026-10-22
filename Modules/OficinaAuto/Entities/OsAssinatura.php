<?php

declare(strict_types=1);

namespace Modules\OficinaAuto\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Arquivos\Concerns\HasArquivos;

/**
 * Assinatura do cliente na entrega da OS (UC-FLX-22 · US-OFICINA-048). Uma por OS.
 *
 * O PNG é anexo DESTA linha (HasArquivos), nunca da OS: `$order->arquivos()` é a galeria do laudo e
 * conta como "foto do serviço" no gate da entrega.
 *
 * Multi-tenant Tier 0 (ADR 0093): trait canônica HasBusinessScope. O gate também é chamado pelo app
 * (token, sem sessão) — ali a consulta filtra o business_id da OS explicitamente.
 *
 * @property int $id
 * @property int $business_id
 * @property int $service_order_id
 * @property string|null $recebido_por
 * @property int|null $user_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class OsAssinatura extends Model
{
    use HasArquivos;
    use HasBusinessScope;

    protected $table = 'oficina_os_assinaturas';

    protected $fillable = [
        'business_id',
        'service_order_id',
        'recebido_por',
        'user_id',
    ];

    protected $casts = [
        'business_id' => 'integer',
        'service_order_id' => 'integer',
        'user_id' => 'integer',
    ];

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class, 'service_order_id');
    }
}
