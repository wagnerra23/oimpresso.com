<?php

declare(strict_types=1);

namespace Modules\Fisio\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Modules\Fisio\Entities\Concerns\SemExclusaoDeDadoClinico;

/**
 * `fisio_patient_medications` — medicamento em uso do paciente (US-FISIO-063, parte 2).
 * Escopo por empresa (ADR 0093). Sem DELETE: tirar da lista é `removed_at` com quem e por quê.
 *
 * @property int $id
 * @property int $business_id
 * @property int $patient_id
 * @property string $name
 * @property ?string $dose
 * @property ?string $frequency
 * @property ?\Illuminate\Support\Carbon $started_on
 * @property ?\Illuminate\Support\Carbon $ended_on
 * @property string $source
 * @property ?int $document_id
 * @property int $created_by
 * @property ?int $confirmed_by
 * @property ?\Illuminate\Support\Carbon $confirmed_at
 * @property ?\Illuminate\Support\Carbon $removed_at
 * @property ?int $removed_by
 * @property ?string $removed_reason
 */
class MedicamentoPaciente extends Model
{
    use HasBusinessScope;
    use SemExclusaoDeDadoClinico;

    public const MANUAL = 'manual';

    public const TRANSCRICAO = 'transcricao';

    protected $table = 'fisio_patient_medications';

    protected $guarded = ['id'];

    protected $casts = [
        'started_on' => 'date',
        'ended_on' => 'date',
        'confirmed_at' => 'datetime',
        'removed_at' => 'datetime',
    ];
}
