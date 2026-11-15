<?php

declare(strict_types=1);

namespace Modules\Fisio\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Modules\Fisio\Entities\Concerns\SemExclusaoDeDadoClinico;

/**
 * `fisio_study_adverse_events` — evento adverso registrado para o estudo (etapa 07 P-06).
 * Escopo por empresa. Não é apagado. Não tem gravidade: quem avalia é a pesquisadora.
 *
 * @property int $id
 * @property int $business_id
 * @property int $study_id
 * @property int $participant_id
 * @property string $origin
 * @property \Illuminate\Support\Carbon $started_on
 * @property \Illuminate\Support\Carbon $ended_on
 * @property ?array $detail
 * @property ?\Illuminate\Support\Carbon $reviewed_at
 * @property ?int $reviewed_by
 * @property ?string $review_note
 */
class StudyAdverseEvent extends Model
{
    use HasBusinessScope;
    use SemExclusaoDeDadoClinico;

    public const ORIGEM_CRISE = 'crise_48h';

    public const ORIGEM_ALERTA_DOR = 'alerta_dor';

    protected $table = 'fisio_study_adverse_events';

    protected $guarded = ['id'];

    protected $casts = [
        'started_on' => 'date',
        'ended_on' => 'date',
        'detail' => 'array',
        'reviewed_at' => 'datetime',
    ];
}
