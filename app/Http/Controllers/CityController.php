<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\City;
class CityController extends Controller
{
    public function lista(){
    	$cidades = City::all();

    	foreach($city as $c){
    		echo "<strong>$c->id</strong> - $c->nome ($c->uf)<br>";
    	}
    }
}
