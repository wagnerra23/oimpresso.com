<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transportadora extends Model
{
    protected $fillable = [
		'razao_social', 'cnpj_cpf', 'logradouro', 'cidade_id', 'business_id'
	];

	/** @return BelongsTo<City, $this> */
	public function cidade(): BelongsTo
	{
		return $this->belongsTo(City::class, 'cidade_id');
	}
}
