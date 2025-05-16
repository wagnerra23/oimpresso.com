<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class NfNaturezaOperacao extends Model
{
    use SoftDeletes;

    protected $table = 'nf_natureza_operacao';

    protected $fillable = [
        'descricao',
        'tipo_nf',
        'nfse_codigo',
        'dt_alteracao',
        'consumidor_final',
        'entrada_saida',
        'operacao',
        'tem_tributacao_padrao',
        'created_by',
        'officeimpresso_codigo',
        'officeimpresso_dt_alteracao',
        'business_id'
    ];
}

