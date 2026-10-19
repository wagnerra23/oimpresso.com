<?php

declare(strict_types=1);

namespace Modules\Fisio\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Arquivos\Concerns\HasArquivos;
use Modules\Fisio\Database\Factories\ExerciseFactory;
use Modules\Fisio\Entities\Concerns\EscopoBibliotecaFisio;

/**
 * `fisio_exercises` — biblioteca. `business_id` nulo = global do oimpresso; preenchido =
 * exercício próprio da clínica. A foto/vídeo do exercício próprio vive no backbone
 * `Modules/Arquivos` (`HasArquivos`, ADR 0123) — ver `Services/MidiaExercicio`. Escopo próprio (`EscopoBibliotecaFisio`): a sessão vê os
 * globais e os dela, nunca os de outra empresa (ADR 0093).
 *
 * @property int $id
 * @property ?int $business_id
 * @property string $name
 * @property string $region
 * @property string $type
 * @property ?string $pose
 * @property ?array $equipment
 * @property array $steps
 * @property ?array $synonyms
 * @property ?int $default_sets
 * @property ?int $default_reps
 * @property ?int $default_hold_s
 * @property int $times_per_day
 * @property ?array $safety_notes
 * @property ?array $contraindication_flags
 * @property bool $needs_support
 * @property bool $is_custom
 */
class Exercise extends Model
{
    use EscopoBibliotecaFisio;
    use HasArquivos;
    /** @use HasFactory<ExerciseFactory> */
    use HasFactory;

    protected $table = 'fisio_exercises';

    protected $guarded = ['id'];

    protected $casts = [
        'steps' => 'array',
        'synonyms' => 'array',
        'equipment' => 'array',
        'safety_notes' => 'array',
        'contraindication_flags' => 'array',
        'needs_support' => 'boolean',
        'is_custom' => 'boolean',
        'default_sets' => 'integer',
        'default_reps' => 'integer',
        'default_hold_s' => 'integer',
        'times_per_day' => 'integer',
    ];

    protected static function newFactory(): ExerciseFactory
    {
        return ExerciseFactory::new();
    }

    public function ehGlobal(): bool
    {
        return $this->business_id === null;
    }
}