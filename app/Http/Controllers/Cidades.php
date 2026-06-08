<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Cidades extends Model
{

    // Defina os campos que podem ser atribuídos em massa
    protected $fillable = [
        'descricao',
        'uf',
        'officeimpresso_codigo',
		'officeimpresso_dt_alteracao',
        'business_id', // Adicionado para permitir atribuição em massa
    ];

    public static function getCidadeCod($codMun){
    	return Cidades::
    	where('codigo', $codMun)
    	->first();
	}
}
