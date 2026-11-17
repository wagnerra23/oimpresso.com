<?php

declare(strict_types=1);

namespace Modules\Fisio\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Fisio\Entities\Concerns\SemExclusaoDeDadoClinico;

/**
 * `fisio_patient_social` — dados sociais do paciente, 1:1 e todos opcionais (US-FISIO-061).
 * Dado sensível (LGPD art. 11): só a ficha e o relatório agregado leem esta tabela.
 * Escopo por empresa (ADR 0093). Sem exclusão pelo model: apagar um campo é gravar nulo.
 *
 * @property int $id
 * @property int $business_id
 * @property int $patient_id
 * @property ?string $sexo
 * @property ?string $genero
 * @property ?string $raca_cor
 * @property ?string $cns
 * @property ?string $renda_familiar
 * @property ?string $cadunico
 * @property ?string $situacao_trabalho
 * @property ?string $escolaridade
 * @property ?string $diagnostico_cid
 * @property ?string $diagnostico_por
 * @property ?\Illuminate\Support\Carbon $diagnostico_em
 * @property ?string $sintomas_ha
 * @property ?array $comorbidades
 * @property ?string $comorbidades_outras
 * @property ?string $como_chegou
 * @property ?string $cipf
 * @property ?int $updated_by
 */
class PatientSocial extends Model
{
    use HasBusinessScope;
    use SemExclusaoDeDadoClinico;

    protected $table = 'fisio_patient_social';

    protected $guarded = ['id'];

    protected $casts = [
        'comorbidades' => 'array',
        'diagnostico_em' => 'date',
    ];

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }
}
