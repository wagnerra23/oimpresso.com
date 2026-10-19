<?php

declare(strict_types=1);

namespace Modules\Fisio\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * `fisio_program_items` — exercício dentro do programa, com dose e ordem. Escopo por empresa
 * (ADR 0093): `business_id` é cópia do business do programa, preenchida aqui ao criar — quem
 * cria passa só `program_id`, e um business divergente do programa é recusado.
 *
 * Contraindicação AVISA, não bloqueia (ADR 0437 §13.2): `contraindication_ack_*` guardam quem
 * confirmou, quais marcadores, quando e a justificativa escrita.
 *
 * @property int $id
 * @property int $business_id
 * @property int $program_id
 * @property int $exercise_id
 * @property ?int $sets
 * @property ?int $reps
 * @property ?int $hold_s
 * @property int $times_per_day
 * @property ?string $notes
 * @property int $position
 * @property ?array $contraindication_ack_flags
 * @property ?int $contraindication_ack_by
 * @property ?\Illuminate\Support\Carbon $contraindication_ack_at
 * @property ?string $contraindication_justification
 */
class ProgramItem extends Model
{
    use HasBusinessScope;

    protected $table = 'fisio_program_items';

    protected $guarded = ['id'];

    protected $casts = [
        'contraindication_ack_flags' => 'array',
        'contraindication_ack_at' => 'datetime',
        'sets' => 'integer',
        'reps' => 'integer',
        'hold_s' => 'integer',
        'times_per_day' => 'integer',
        'position' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $item): void {
            // SUPERADMIN: lê só o business do PRÓPRIO programa do item, para copiá-lo (fonte da verdade).
            $doPrograma = Program::withoutGlobalScopes()->whereKey($item->program_id)->value('business_id');
            if ($doPrograma === null) {
                throw new LogicException('fisio_program_items: programa inexistente.');
            }
            $informado = $item->getAttribute('business_id'); // nulo antes do create: quem cria passa só program_id
            if ($informado !== null && (int) $informado !== (int) $doPrograma) {
                throw new LogicException('fisio_program_items: business do item diverge do business do programa.');
            }
            $item->business_id = (int) $doPrograma;
        });
    }

    /** @return BelongsTo<Program, $this> */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class, 'program_id');
    }

    /** @return BelongsTo<Exercise, $this> */
    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class, 'exercise_id');
    }
}