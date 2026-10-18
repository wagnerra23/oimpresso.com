<?php

declare(strict_types=1);

namespace Modules\Fisio\Entities;

use App\Concerns\HasBusinessScope;
use App\Contact;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Fisio\Database\Factories\PatientFactory;
use Modules\Fisio\Entities\Concerns\SemExclusaoDeDadoClinico;

/**
 * `fisio_patients` — perfil clínico 1:1 de um `contact` (o paciente É um contact; ADR 0437 §3).
 * Escopo por empresa (ADR 0093). "Excluir" = `blocked_at` (Lei 13.787/2018): sem DELETE.
 *
 * @property int $id
 * @property int $business_id
 * @property int $contact_id
 * @property ?string $main_complaint
 * @property ?array $goals
 * @property int $sessions_per_week
 * @property string $status
 * @property ?array $condition_flags
 * @property ?\Illuminate\Support\Carbon $blocked_at
 */
class Patient extends Model
{
    use HasBusinessScope;
    /** @use HasFactory<PatientFactory> */
    use HasFactory;
    use SemExclusaoDeDadoClinico;

    protected $table = 'fisio_patients';

    protected $guarded = ['id'];

    protected $casts = [
        'goals' => 'array',
        'condition_flags' => 'array',
        'sessions_per_week' => 'integer',
        'blocked_at' => 'datetime',
    ];

    protected static function newFactory(): PatientFactory
    {
        return PatientFactory::new();
    }

    public function estaBloqueado(): bool
    {
        return $this->blocked_at !== null;
    }

    /** @return BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    /** @return HasMany<Record, $this> */
    public function records(): HasMany
    {
        return $this->hasMany(Record::class, 'patient_id');
    }

    /** @return HasMany<Program, $this> */
    public function programs(): HasMany
    {
        return $this->hasMany(Program::class, 'patient_id');
    }

    /** @return HasMany<Checkin, $this> */
    public function checkins(): HasMany
    {
        return $this->hasMany(Checkin::class, 'patient_id');
    }

    /** @return HasMany<Consent, $this> */
    public function consents(): HasMany
    {
        return $this->hasMany(Consent::class, 'patient_id');
    }

    /** @return HasMany<Questionnaire, $this> */
    public function questionnaires(): HasMany
    {
        return $this->hasMany(Questionnaire::class, 'patient_id');
    }

    /** @return HasMany<PortalToken, $this> */
    public function portalTokens(): HasMany
    {
        return $this->hasMany(PortalToken::class, 'patient_id');
    }

    /** @return HasMany<GameLedger, $this> */
    public function ledger(): HasMany
    {
        return $this->hasMany(GameLedger::class, 'patient_id');
    }
}