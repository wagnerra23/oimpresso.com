<?php

declare(strict_types=1);

namespace Modules\Fisio\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Fisio\Entities\Concerns\SemExclusaoDeDadoClinico;

/**
 * `fisio_consents` — consentimentos separados e versionados (LGPD; ADR 0437 §7). Escopo por
 * empresa (ADR 0093). Revogar é preencher `revoked_at`; a linha fica como prova.
 * A versão vigente de cada finalidade vem de `config('fisio.consent_versions.<purpose>')`.
 *
 * @property int $id
 * @property int $business_id
 * @property int $patient_id
 * @property string $purpose
 * @property string $version
 * @property \Illuminate\Support\Carbon $granted_at
 * @property ?\Illuminate\Support\Carbon $revoked_at
 * @property ?string $ip
 * @property ?string $channel
 */
class Consent extends Model
{
    use HasBusinessScope;
    use SemExclusaoDeDadoClinico;

    protected $table = 'fisio_consents';

    protected $guarded = ['id'];

    protected $casts = [
        'granted_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }
}