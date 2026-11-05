<?php

declare(strict_types=1);

namespace Modules\Fisio\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Fisio\Entities\Concerns\SemExclusaoDeDadoClinico;

/**
 * `fisio_packages` — pacote de sessões (US-FISIO-020). Guarda só sessões, ritmo e validade;
 * o preço é da venda do núcleo (`transaction_id`). O saldo sai do `PacoteLancamento`, nunca
 * de coluna. Escopo por empresa (ADR 0093). Sai de uso por `status = encerrado`.
 *
 * @property int $id
 * @property int $business_id
 * @property int $patient_id
 * @property string $title
 * @property int $sessions_total
 * @property int|null $sessions_per_week
 * @property \Illuminate\Support\Carbon|null $valid_until
 * @property int|null $transaction_id
 * @property string $status
 * @property int $created_by
 */
class Pacote extends Model
{
    use HasBusinessScope;
    use SemExclusaoDeDadoClinico;

    public const STATUS_ATIVO = 'ativo';

    public const STATUS_ENCERRADO = 'encerrado';

    protected $table = 'fisio_packages';

    protected $guarded = ['id'];

    protected $casts = [
        'valid_until' => 'date:Y-m-d',
        'sessions_total' => 'integer',
        'sessions_per_week' => 'integer',
        'transaction_id' => 'integer',
    ];

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    /** @return HasMany<PacoteLancamento, $this> */
    public function lancamentos(): HasMany
    {
        return $this->hasMany(PacoteLancamento::class, 'package_id');
    }
}
