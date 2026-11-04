<?php

declare(strict_types=1);

namespace Modules\Fisio\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * `fisio_waitlist` — paciente esperando vaga (A7), com preferência de dias, turnos e prazo.
 * Escopo por empresa (ADR 0093). US-FISIO-013.
 */
class WaitlistEntry extends Model
{
    use HasBusinessScope;

    protected $table = 'fisio_waitlist';

    protected $guarded = ['id'];

    protected $casts = ['preferences' => 'array', 'offered_starts_at' => 'datetime', 'offer_expires_at' => 'datetime'];

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }
}
