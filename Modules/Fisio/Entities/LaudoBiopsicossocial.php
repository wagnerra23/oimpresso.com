<?php

declare(strict_types=1);

namespace Modules\Fisio\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Fisio\Entities\Concerns\SemExclusaoDeDadoClinico;

/**
 * `fisio_laudos_bps` — laudo biopsicossocial integrado (US-FISIO-060; Lei 15.176/2025 e
 * art. 2º, §1º, da Lei 13.146/2015). O documento só agrupa as quatro seções; quem escreve e
 * assina é cada seção (LaudoBiopsicossocialSecao). Escopo por empresa (ADR 0093). Sem DELETE.
 *
 * O laudo descreve impedimentos e limitações. Não conclui que o paciente é pessoa com
 * deficiência: quem enquadra é a perícia (ADR 0437 §5).
 *
 * @property int $id
 * @property int $business_id
 * @property int $patient_id
 * @property int $created_by
 * @property \Illuminate\Support\Carbon $created_at
 */
class LaudoBiopsicossocial extends Model
{
    use HasBusinessScope;
    use SemExclusaoDeDadoClinico;

    public const UPDATED_AT = null;

    protected $table = 'fisio_laudos_bps';

    protected $guarded = ['id'];

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    /** @return HasMany<LaudoBiopsicossocialSecao, $this> */
    public function secoes(): HasMany
    {
        return $this->hasMany(LaudoBiopsicossocialSecao::class, 'laudo_id');
    }
}
