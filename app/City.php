<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class City extends Model
{
    public static function getCidadeCod($codMun){
    	return City::
    	where('codigo', $codMun)
    	->first();
	}
}
