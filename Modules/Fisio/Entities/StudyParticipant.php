<?php

declare(strict_types=1);

namespace Modules\Fisio\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Fisio\Entities\Concerns\SemExclusaoDeDadoClinico;

/**
 * `fisio_study_participants` — paciente dentro do estudo (etapa 07 P-04). Escopo por empresa.
 * A saída é registrada (data, tipo, motivo) e a linha nunca é apagada.
 *
 * @property int $id
 * @property int $business_id
 * @property int $study_id
 * @property int $patient_id
 * @property string $code
 * @property \Illuminate\Support\Carbon $entered_on
 * @property ?\Illuminate\Support\Carbon $exited_on
 * @property ?string $exit_type
 * @property ?string $exit_reason
 */
class StudyParticipant extends Model
{
    use HasBusinessScope;
    use SemExclusaoDeDadoClinico;

    public const SAIDA_PERDA_SEGUIMENTO = 'perda_seguimento';

    public const SAIDA_REVOGACAO = 'revogacao';

    public const SAIDA_CONCLUSAO = 'conclusao';

    public const SAIDA_OUTRO = 'outro';

    public const SAIDAS = [self::SAIDA_PERDA_SEGUIMENTO, self::SAIDA_REVOGACAO, self::SAIDA_CONCLUSAO, self::SAIDA_OUTRO];

    protected $table = 'fisio_study_participants';

    protected $guarded = ['id'];

    protected $casts = [
        'entered_on' => 'date',
        'exited_on' => 'date',
    ];

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    /** @return BelongsTo<Study, $this> */
    public function study(): BelongsTo
    {
        return $this->belongsTo(Study::class, 'study_id');
    }
}
