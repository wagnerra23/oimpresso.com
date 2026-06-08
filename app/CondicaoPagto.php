<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CondicaoPagto extends Model
{
    use SoftDeletes;

    protected $table = 'condicaopagto';

    protected $fillable = [
        'business_id',
        'descricao',
        'tipo',
        'parcelas',
        'intervalo',
        'entrada',
        'desconto_acrescimo',
        'tipopagto',
        'tipo_utilizacao',
        'perc_entrada',
        'codplanocontas',
        'codplanocontas_pagto',
        'fator_comercial',
        'intervalo_mensal',
        'is_cartao',
        'pode_substituir_desconto_venda',
        'officeimpresso_codigo',
        'officeimpresso_dt_alteracao',
    ];

    protected $dates = ['deleted_at'];
}
