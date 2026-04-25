<?php

namespace Modules\Copiloto\Entities;

use Illuminate\Database\Eloquent\Model;

/**
 * Sugestao — proposta de meta gerada pela IA.
 *
 * Quando o gestor escolhe, `meta_id` é preenchido com a Meta criada.
 * Quando rejeita, `rejeitada_em` marca — feedback passivo pro prompt futuro.
 */
class Sugestao extends Model
{
    protected $table = 'copiloto_sugestoes';

    protected $fillable = [
        'conversa_id', 'meta_id', 'payload_json', 'escolhida_em', 'rejeitada_em',
    ];

    protected $casts = [
        'payload_json' => 'array',
        'escolhida_em' => 'datetime',
        'rejeitada_em' => 'datetime',
    ];

    public function conversa()
    {
        return $this->belongsTo(Conversa::class, 'conversa_id');
    }

    public function meta()
    {
        return $this->belongsTo(Meta::class, 'meta_id');
    }
}
