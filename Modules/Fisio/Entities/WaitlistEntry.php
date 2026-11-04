<?php

declare(strict_types=1);

namespace Modules\Fisio\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * `fisio_waitlist` — paciente esperando vaga (A7), com preferência de dias, turnos e prazo.
 * Escopo por empresa (ADR 0093). US-FISIO-013.
 *
 * @property int $id
 * @property int $business_id
 * @property int $patient_id
 * @property ?int $professional_id
 * @property ?int $type_id
 * @property ?array{dias?: list<int>, turnos?: list<string>, ate?: string} $preferences
 * @property string $status
 * @property ?\Illuminate\Support\Carbon $offered_starts_at
 * @property ?\Illuminate\Support\Carbon $offer_expires_at
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
