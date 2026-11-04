<?php

declare(strict_types=1);

namespace Modules\Fisio\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;

/**
 * `fisio_appointment_types` — tipo de atendimento da agenda (A2): duração, modalidade e se o
 * paciente pode marcar pelo app. Escopo por empresa (ADR 0093). US-FISIO-013.
 */
class AppointmentType extends Model
{
    use HasBusinessScope;

    protected $table = 'fisio_appointment_types';

    protected $guarded = ['id'];

    protected $casts = ['duration_min' => 'integer', 'online_bookable' => 'boolean', 'active' => 'boolean'];
}
