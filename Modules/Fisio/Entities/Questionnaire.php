<?php

declare(strict_types=1);

namespace Modules\Fisio\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Fisio\Entities\Concerns\SemExclusaoDeDadoClinico;

/**
 * `fisio_questionnaires` — respostas de questionário clínico de uso livre (EVA, RMDQ, PSFS,
 * LEFS, SPADI; ADR 0437 §8). Enum fechado no banco: escala bloqueada por licença não entra.
 * Escopo por empresa (ADR 0093). Sem DELETE (dado clínico).
 *
 * @property int $id
 * @property int $business_id
 * @property int $patient_id
 * @property string $instrument
 * @property array $answers
 * @property ?string $score
 * @property \Illuminate\Support\Carbon $answered_at
 */
class Questionnaire extends Model
{
    use HasBusinessScope;
    use SemExclusaoDeDadoClinico;

    protected $table = 'fisio_questionnaires';

    protected $guarded = ['id'];

    protected $casts = [
        'answers' => 'array',
        'answered_at' => 'datetime',
    ];

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }
}