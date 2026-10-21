<?php

declare(strict_types=1);

namespace Modules\Fisio\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * `fisio_exercise_favorites` — exercício favorito de um profissional, dentro da empresa da sessão.
 * Único por (business, usuário, exercício). Favoritar um exercício global grava a linha na empresa
 * da sessão: o exercício global não muda (ADR 0437 §4). Escopo por empresa (ADR 0093).
 *
 * @property int $id
 * @property int $business_id
 * @property int $user_id
 * @property int $exercise_id
 */
class ExerciseFavorite extends Model
{
    use HasBusinessScope;

    protected $table = 'fisio_exercise_favorites';

    protected $guarded = ['id'];

    protected $casts = [
        'business_id' => 'integer',
        'user_id' => 'integer',
        'exercise_id' => 'integer',
    ];

    /** @return BelongsTo<Exercise, $this> */
    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class, 'exercise_id');
    }
}
