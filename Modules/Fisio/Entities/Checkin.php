<?php

declare(strict_types=1);

namespace Modules\Fisio\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Fisio\Database\Factories\CheckinFactory;
use Modules\Fisio\Entities\Concerns\SemExclusaoDeDadoClinico;

/**
 * `fisio_checkins` — "como foi hoje" do paciente. Um por paciente e data (UNIQUE no banco):
 * reenvio do mesmo dia atualiza a linha, e o prontuário guarda o relato pelo telemonitoramento
 * + adendo (US-FISIO-007). Escopo por empresa (ADR 0093). Sem DELETE (dado clínico).
 *
 * @property int $id
 * @property int $business_id
 * @property int $patient_id
 * @property ?int $program_id
 * @property \Illuminate\Support\Carbon $date
 * @property ?array $done_item_ids
 * @property ?int $pain
 * @property ?string $effort
 * @property ?string $note
 * @property ?array $red_flags
 * @property bool $crisis paciente escolheu a versão de crise do programa (protocolo 11): conta para a sequência
 * @property ?string $client_key chave do último envio aplicado (fila offline do portal, idempotência)
 */
class Checkin extends Model
{
    use HasBusinessScope;
    /** @use HasFactory<CheckinFactory> */
    use HasFactory;
    use SemExclusaoDeDadoClinico;

    protected $table = 'fisio_checkins';

    protected $guarded = ['id'];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'done_item_ids' => 'array',
        'red_flags' => 'array',
        'pain' => 'integer',
        'crisis' => 'boolean',
    ];

    protected static function newFactory(): CheckinFactory
    {
        return CheckinFactory::new();
    }

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    /** @return BelongsTo<Program, $this> */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class, 'program_id');
    }
}