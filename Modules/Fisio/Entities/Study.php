<?php

declare(strict_types=1);

namespace Modules\Fisio\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Modules\Fisio\Entities\Concerns\SemExclusaoDeDadoClinico;

/**
 * `fisio_studies` — estudo de pesquisa conduzido pela clínica (etapa 07, ADR 0437 §7). Escopo
 * por empresa (ADR 0093): estudo de uma clínica nunca recebe paciente de outra.
 *
 * `tcle_version` é a versão do TCLE aprovada pelo CEP. O consentimento `pesquisa` grava esta
 * versão; ela nunca vem do request. Estudo não é apagado: sai de uso por `status`.
 *
 * @property int $id
 * @property int $business_id
 * @property string $title
 * @property string $cep_opinion_number
 * @property string $protocol_version
 * @property string $tcle_version
 * @property \Illuminate\Support\Carbon $starts_on
 * @property ?\Illuminate\Support\Carbon $ends_on
 * @property ?int $responsible_user_id
 * @property string $status
 */
class Study extends Model
{
    use HasBusinessScope;
    use SemExclusaoDeDadoClinico;

    public const STATUS_RASCUNHO = 'rascunho';

    public const STATUS_ATIVO = 'ativo';

    public const STATUS_ENCERRADO = 'encerrado';

    protected $table = 'fisio_studies';

    protected $guarded = ['id'];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
    ];

    /** @return \Illuminate\Database\Eloquent\Relations\HasMany<StudyMoment, $this> */
    public function moments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(StudyMoment::class, 'study_id')->orderBy('sort')->orderBy('day_offset');
    }

    /** @return \Illuminate\Database\Eloquent\Relations\HasMany<StudyParticipant, $this> */
    public function participants(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(StudyParticipant::class, 'study_id');
    }
}
