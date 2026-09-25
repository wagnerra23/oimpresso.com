<?php

namespace Modules\Ponto\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * Competência fechada — append-only (ADR 0413 W1).
 *
 * A linha é gravada UMA vez, no fechamento. "Reabrir" não existe na v1 (ADR 0413 D1):
 * correção depois de fechada é anulação com trilha em ponto_marcacoes
 * (`Marcacao::anular()`, Portaria MTP 671/2021). Por isso update/delete lançam —
 * inclusive `save()` em registro existente, que chama `performUpdate()` direto e
 * escaparia de um override só de `update()` (lição US-PONTO-011 no BancoHorasMovimento).
 *
 * Em MySQL há ainda os triggers `trg_ponto_competencias_no_{update,delete}` — defesa dupla.
 *
 * Multi-tenant Tier 0 ([ADR 0093]): HasBusinessScope aplica o global scope por business_id.
 */
class Competencia extends Model
{
    use HasBusinessScope;

    protected $table = 'ponto_competencias';

    public $timestamps = false;  // só created_at, via default DB

    protected $fillable = [
        'business_id',
        'competencia',
        'fechada_por',
        'fechada_em',
        'bloqueios_aceitos',
    ];

    protected $casts = [
        'competencia'       => 'date',
        'fechada_em'        => 'datetime',
        'created_at'        => 'datetime',
        'bloqueios_aceitos' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::updating(function () {
            throw new RuntimeException(
                'Competência fechada é append-only e não reabre na v1 (ADR 0413 D1). '
                . 'Correção é anulação da marcação com trilha (Portaria MTP 671/2021).'
            );
        });

        static::deleting(function () {
            throw new RuntimeException('Competência fechada não pode ser apagada (ADR 0413 D1).');
        });
    }

    public function fechador(): BelongsTo
    {
        return $this->belongsTo(\App\User::class, 'fechada_por');
    }

    public function update(array $attributes = [], array $options = [])
    {
        throw new RuntimeException('Competência fechada é append-only (ADR 0413 D1).');
    }
}
