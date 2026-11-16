<?php

namespace Modules\Associacoes\Entities;

use App\Concerns\HasBusinessScope;
use App\Contact;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Associada (assoc_associadas): pessoa com fibromialgia filiada a UMA associação. É um `contact`
 * do núcleo — nome e celular moram lá; aqui não há CPF nem dado clínico (ADR 0451 D5).
 *
 * @property int $id
 * @property int $business_id
 * @property int $associacao_id
 * @property int $contact_id
 * @property \Illuminate\Support\Carbon $entrou_em
 * @property string $situacao
 * @property \Illuminate\Support\Carbon|null $desligada_em
 */
class Associada extends Model
{
    use HasBusinessScope;

    public const SITUACOES = ['ativa', 'desligada'];

    protected $table = 'assoc_associadas';

    protected $fillable = ['business_id', 'associacao_id', 'contact_id', 'entrou_em', 'situacao', 'desligada_em'];

    protected $casts = [
        'entrou_em' => 'date',
        'desligada_em' => 'date',
    ];

    public function associacao(): BelongsTo
    {
        return $this->belongsTo(Associacao::class, 'associacao_id');
    }

    public function contato(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function consentimentos(): HasMany
    {
        return $this->hasMany(ConsentimentoAssociada::class, 'associada_id');
    }
}
