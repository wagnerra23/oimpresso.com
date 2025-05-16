<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Transportadora extends Model
{
    protected $fillable = [
		'razao_social', 'cnpj_cpf', 'logradouro', 'cidade_id', 'business_id'
	];

	public function cidade(){
		return $this->belongsTo(City::class, 'cidade_id');
	}
}
