<?php

namespace Modules\Ponto\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Ciência do colaborador no espelho de uma competência fechada — append-only (US-PONTO-020).
 *
 * Gravada uma vez. update/delete lançam, inclusive `save()` em registro existente (lição
 * US-PONTO-011); em MySQL há ainda os triggers `trg_ponto_espelho_ciencias_no_*`.
 *
 * Multi-tenant Tier 0 (ADR 0093): HasBusinessScope aplica o global scope por business_id.
 *
 * @property int $id
 * @property int $business_id
 * @property int $colaborador_config_id
 * @property \Illuminate\Support\Carbon $competencia
 * @property string $hash_espelho
 * @property \Illuminate\Support\Carbon $ciente_em
 */
class EspelhoCiencia extends Model
{
    use HasBusinessScope;

    protected $table = 'ponto_espelho_ciencias';

    public $timestamps = false;

    protected $fillable = ['business_id', 'colaborador_config_id', 'competencia', 'hash_espelho', 'ciente_em'];

    protected $casts = [
        'competencia' => 'date',
        'ciente_em'   => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::updating(function () {
            throw new RuntimeException('Ciência do espelho é append-only: a ciência dada não muda.');
        });

        static::deleting(function () {
            throw new RuntimeException('Ciência do espelho é append-only: a ciência dada não é apagada.');
        });
    }

    public function update(array $attributes = [], array $options = [])
    {
        throw new RuntimeException('Ciência do espelho é append-only.');
    }
}
