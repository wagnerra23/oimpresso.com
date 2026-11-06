<?php

declare(strict_types=1);

namespace Modules\Fisio\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Fisio\Entities\Concerns\SemExclusaoDeDadoClinico;
use Modules\Fisio\Exceptions\DadoClinicoImutavelException;

/**
 * `fisio_pre_anamneses` — relato do paciente antes da 1ª consulta, pelo portal (Lote 5,
 * US-FISIO-051). Uma por paciente (UNIQUE no banco). Escopo por empresa (ADR 0093).
 *
 * Imutável depois de enviada: o model recusa UPDATE e DELETE (auditoria do Fisio d10,
 * 2026-11-05). É relato do paciente, não registro assinado do prontuário: não tem adendo.
 *
 * @property int $id
 * @property int $business_id
 * @property int $patient_id
 * @property array $body
 * @property array $red_flags
 * @property string $channel
 * @property \Illuminate\Support\Carbon $created_at
 */
class PreAnamnese extends Model
{
    use HasBusinessScope;
    use SemExclusaoDeDadoClinico;

    public const UPDATED_AT = null;

    protected $table = 'fisio_pre_anamneses';

    protected $guarded = ['id'];

    protected $casts = [
        'body' => 'array',
        'red_flags' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $pre): void {
            throw DadoClinicoImutavelException::alteracao($pre->getTable());
        });
    }

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }
}
