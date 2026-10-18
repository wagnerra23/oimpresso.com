<?php

declare(strict_types=1);

namespace Modules\Fisio\Entities;

use App\Concerns\HasBusinessScope;
use App\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Fisio\Database\Factories\ProgramFactory;
use Modules\Fisio\Entities\Concerns\SemExclusaoDeDadoClinico;

/**
 * `fisio_programs` — programa de exercícios prescrito. Escopo por empresa (ADR 0093).
 * Sai de uso por `status` (encerrado), nunca por DELETE: é parte do registro clínico.
 *
 * `stop_instruction` e `crisis_items` são texto do PROFISSIONAL: com dor ≥ limiar ou sinal de
 * alerta o paciente vê só isso, nunca conduta calculada pelo software (ADR 0437 §5).
 *
 * @property int $id
 * @property int $business_id
 * @property int $patient_id
 * @property ?string $phase
 * @property string $status
 * @property string $origin
 * @property ?string $stop_instruction
 * @property ?int $pain_alert_threshold
 * @property string $pain_alert_mode
 * @property ?array $crisis_items
 * @property int $created_by
 * @property ?\Illuminate\Support\Carbon $starts_at
 */
class Program extends Model
{
    use HasBusinessScope;
    /** @use HasFactory<ProgramFactory> */
    use HasFactory;
    use SemExclusaoDeDadoClinico;

    public const STATUS_ATIVO = 'ativo';

    public const STATUS_ENCERRADO = 'encerrado';

    protected $table = 'fisio_programs';

    protected $guarded = ['id'];

    protected $casts = [
        'crisis_items' => 'array',
        'pain_alert_threshold' => 'integer',
        'starts_at' => 'date:Y-m-d',
    ];

    protected static function newFactory(): ProgramFactory
    {
        return ProgramFactory::new();
    }

    /** Limiar de dor efetivo: o do programa ou o padrão de `config/fisio.php`. */
    public function limiarDeDor(): int
    {
        return $this->pain_alert_threshold ?? (int) config('fisio.pain_alert_threshold_padrao', 7);
    }

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<ProgramItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(ProgramItem::class, 'program_id')->orderBy('position');
    }

    /** @return HasMany<Checkin, $this> */
    public function checkins(): HasMany
    {
        return $this->hasMany(Checkin::class, 'program_id');
    }
}