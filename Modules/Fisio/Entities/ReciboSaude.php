<?php

declare(strict_types=1);

namespace Modules\Fisio\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;

/**
 * `fisio_health_receipts` — marca de envio do recibo do Receita Saúde por pagamento
 * (US-FISIO-023). Não copia valor nem data: esses vêm do `transaction_payments`.
 *
 * @property int $id
 * @property int $business_id
 * @property int $payment_id
 * @property string $marca
 * @property string|null $receipt_number
 * @property int $marked_by
 * @property \Illuminate\Support\Carbon $marked_at
 */
class ReciboSaude extends Model
{
    use HasBusinessScope;

    public const ENVIADO = 'enviado';

    public const RETIFICAR = 'retificar';

    protected $table = 'fisio_health_receipts';

    protected $guarded = ['id'];

    protected $casts = ['marked_at' => 'datetime', 'payment_id' => 'integer'];
}
