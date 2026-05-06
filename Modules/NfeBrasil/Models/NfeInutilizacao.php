<?php

namespace Modules\NfeBrasil\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Inutilização de range de numeração — fechamento legal de "lacuna" na sequência.
 *
 * Quando emite ex. notas 1, 2, 5 (perdeu 3 e 4), tem que inutilizar [3..4]
 * via processo SEFAZ. Sem isso, ano fiscal não fecha.
 *
 * Status: pendente → enviado → autorizado | rejeitado.
 */
class NfeInutilizacao extends Model
{
    protected $table = 'nfe_inutilizacoes';

    protected $fillable = [
        'business_id', 'modelo', 'serie',
        'numero_de', 'numero_ate',
        'justificativa',
        'status', 'cstat', 'autorizada_em', 'payload_json',
    ];

    protected $casts = [
        'numero_de'      => 'integer',
        'numero_ate'     => 'integer',
        'autorizada_em'  => 'datetime',
        'payload_json'   => 'array',
    ];

    public function quantidadeNumeros(): int
    {
        return ($this->numero_ate - $this->numero_de) + 1;
    }
}
