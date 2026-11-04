<?php

declare(strict_types=1);

namespace Modules\Fisio\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;

/**
 * `fisio_appointment_types` — tipo de atendimento da agenda (A2): duração, modalidade e se o
 * paciente pode marcar pelo app. Escopo por empresa (ADR 0093). US-FISIO-013.
 *
 * @property int $id
 * @property int $business_id
 * @property string $name
 * @property int $duration_min
 * @property string $modality presencial | teleconsulta
 * @property bool $online_bookable
 * @property bool $active
 */
class AppointmentType extends Model
{
    use HasBusinessScope;

    protected $table = 'fisio_appointment_types';

    protected $guarded = ['id'];

    protected $casts = ['duration_min' => 'integer', 'online_bookable' => 'boolean', 'active' => 'boolean'];
}
