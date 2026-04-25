<?php

namespace Modules\Copiloto\Entities;

use Illuminate\Database\Eloquent\Model;

/**
 * MetaApuracao — registro append-only do realizado.
 *
 * Idempotência garantida por unique (meta_id, data_ref, fonte_query_hash).
 * Ver adr/tech/0001-drivers-apuracao-plugaveis.md.
 */
class MetaApuracao extends Model
{
    protected $table = 'copiloto_meta_apuracoes';

    protected $fillable = [
        'meta_id', 'data_ref', 'valor_realizado', 'calculado_em', 'fonte_query_hash',
    ];

    protected $casts = [
        'data_ref' => 'date',
        'calculado_em' => 'datetime',
        'valor_realizado' => 'decimal:2',
    ];

    public function meta()
    {
        return $this->belongsTo(Meta::class, 'meta_id');
    }
}
