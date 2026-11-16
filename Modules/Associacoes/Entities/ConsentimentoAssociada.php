<?php

namespace Modules\Associacoes\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Consentimento LGPD da associada, por finalidade e versão (assoc_consentimentos; ADR 0451 D5).
 * É prova: o banco recusa DELETE (trigger) e o Model também. Revogar preenche `revoked_at`.
 *
 * @property int $id
 * @property int $business_id
 * @property int $associada_id
 * @property string $finalidade
 * @property string $versao
 * @property \Illuminate\Support\Carbon $concedido_em
 * @property string $canal
 * @property \Illuminate\Support\Carbon|null $revoked_at
 */
class ConsentimentoAssociada extends Model
{
    use HasBusinessScope;

    protected $table = 'assoc_consentimentos';

    protected $fillable = ['business_id', 'associada_id', 'finalidade', 'versao', 'concedido_em', 'canal', 'revoked_at'];

    protected $casts = [
        'concedido_em' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(static fn () => throw new LogicException('Consentimento é prova LGPD: revogue preenchendo revoked_at.'));
    }

    public function associada(): BelongsTo
    {
        return $this->belongsTo(Associada::class, 'associada_id');
    }
}
