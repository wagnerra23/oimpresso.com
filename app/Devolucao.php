<?php

namespace App;
use App\Transaction;

use Illuminate\Database\Eloquent\Model;

class Devolucao extends Model
{
    protected $fillable = [
		'contact_id', 'natureza_id', 'valor_integral', 
		'valor_devolvido', 'motivo', 'observacao', 'estado', 'devolucao_parcial', 
		'chave_nf_entrada', 'nNf', 'vFrete', 'vDesc', 'chave_gerada', 'numero_gerado', 'business_id'
	];

    public static function lastNFe($business_id){
        $transation = Transaction::
        where('numero_nfe', '>', 0)
        ->where('business_id', $business_id)
        ->orderBy('numero_nfe', 'desc')
        ->first();

        $devolucao = Devolucao::
        where('numero_gerado', '>', 0)
        ->where('business_id', $business_id)
        ->orderBy('numero_gerado', 'desc')
        ->first();

        $config = Business::find($business_id);

        $numero_saida = $transation != null ? $transation->numero_nfe : 0;
        $numero_devolucao = $devolucao != null ? $devolucao->numero_gerado : 0;

        
        if($numero_saida > $config->ultimo_numero_nfe && $numero_saida > $numero_devolucao){
            return $numero_saida;
        }
        else if($numero_devolucao > $config->ultimo_numero_nfe && $numero_devolucao > $numero_saida){
            return $numero_devolucao;
        }
        else{
            return $config->ultimo_numero_nfe;
        } 
    }

	public function contact(){
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function natureza(){
        return $this->belongsTo(NaturezaOperacao::class, 'natureza_id');
    }

    public function itens(){
        return $this->hasMany('App\ItemDevolucao', 'devolucao_id', 'id');
    }

    public function estado(){
        if($this->estado == 0){
            return 'NOVO';
        }
        else if($this->estado == 1){
            return 'APROVADO';
        }
        else if($this->estado == 2){
            return 'REJEITADO';
        }else{
            return 'CANCELADO';
        }
    }
}
