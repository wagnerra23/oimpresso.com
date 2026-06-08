<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Cidades;


class CidadesController extends Controller
{
    public function lista(){
    	$cidades = Cidades::all();

    	foreach($cidades as $cidade){
    		echo "<strong>$cidade->id</strong> - $cidade->nome ($cidade->uf)<br>";
    	}
    }
}
