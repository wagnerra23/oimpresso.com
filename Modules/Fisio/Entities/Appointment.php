<?php

declare(strict_types=1);

namespace Modules\Fisio\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Fisio\Entities\Concerns\SemExclusaoDeDadoClinico;

/**
 * `fisio_appointments` — atendimento na agenda (etapa 04 A1–A10, US-FISIO-013). Não é apagado:
 * desmarcar é `cancelado`, faltar é `faltou`. O recado e a nota do paciente (A20) só a clínica vê.
 * Escopo por empresa (ADR 0093).
 *
 * @property int $id
 * @property int $business_id
 * @property int $patient_id
 * @property int $professional_id users.id
 * @property int $type_id
 * @property \Illuminate\Support\Carbon $starts_at
 * @property \Illuminate\Support\Carbon $ends_at
 * @property string $status
 * @property bool $is_overbook
 * @property string $source clinica | online | lista_espera
 * @property ?\Illuminate\Support\Carbon $confirmed_at
 * @property ?string $confirmation_channel
 * @property ?\Illuminate\Support\Carbon $arrived_at
 * @property ?\Illuminate\Support\Carbon $cancelled_at
 * @property ?string $cancelled_by clinica | paciente
 * @property ?string $cancel_reason
 * @property bool $late_cancel
 * @property ?int $rescheduled_from_id
 * @property ?string $patient_note
 * @property ?int $patient_rating
 * @property ?int $record_id
 * @property ?int $policy_version
 * @property ?int $created_by
 */
class Appointment extends Model
{
    use HasBusinessScope;
    use SemExclusaoDeDadoClinico;

    public const STATUS = ['agendado', 'confirmado', 'chegou', 'em_atendimento', 'realizado', 'faltou', 'cancelado'];

    /** Status que ocupam o horário (conflito de profissional). */
    public const OCUPAM = ['agendado', 'confirmado', 'chegou', 'em_atendimento', 'realizado'];

    protected $table = 'fisio_appointments';

    protected $guarded = ['id'];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'arrived_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'is_overbook' => 'boolean',
        'late_cancel' => 'boolean',
        'patient_rating' => 'integer',
    ];

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    /** @return BelongsTo<AppointmentType, $this> */
    public function type(): BelongsTo
    {
        return $this->belongsTo(AppointmentType::class, 'type_id');
    }
}
