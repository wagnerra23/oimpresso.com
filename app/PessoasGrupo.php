<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PessoasGrupo extends Model
{
    use SoftDeletes;

    // Nome da tabela no banco de dados
    protected $table = 'pessoas_grupo';

    // Campos que podem ser atribuídos em massa
    protected $fillable = [
        'id',
        'descricao',
        'officeimpresso_codigo',
        'officeimpresso_dt_alteracao',
        'business_id',
    ];

    // Campos de soft delete e timestamps automáticos já estão ativados pelo Laravel
}
