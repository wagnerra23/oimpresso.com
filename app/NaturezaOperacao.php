<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class NaturezaOperacao extends Model
{
    protected $fillable = [
		'natureza', 'cfop_entrada_estadual', 'cfop_entrada_inter_estadual',
		'cfop_saida_estadual', 'cfop_saida_inter_estadual', 'business_id'
	];
}
