<?php

namespace Modules\Associacoes\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Vínculo usuário → associação que ele representa (assoc_representantes; ADR 0451 D4).
 * Quem não tem `associacoes.rede` só vê as associações em que aparece aqui.
 *
 * @property int $id
 * @property int $business_id
 * @property int $associacao_id
 * @property int $user_id
 */
class Representante extends Model
{
    use HasBusinessScope;

    protected $table = 'assoc_representantes';

    protected $fillable = ['business_id', 'associacao_id', 'user_id'];

    public function associacao(): BelongsTo
    {
        return $this->belongsTo(Associacao::class, 'associacao_id');
    }
}
