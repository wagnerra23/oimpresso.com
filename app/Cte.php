<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use App\ConfigNota;
use App\Cidades;

class Cte extends Model
{
    protected $fillable = [
        'business_id', 'chave_nfe', 'remetente_id', 'destinatario_id', 'usuario_id', 'natureza_id', 'tomador',
        'municipio_envio', 'municipio_inicio', 'municipio_fim', 'logradouro_tomador', 'numero_tomador', 'bairro_tomador', 'cep_tomador', 'municipio_tomador',
        'valor_transporte', 'valor_receber', 'valor_carga', 
        'produto_predominante', 'data_previsata_entrega', 'observacao',
        'sequencia_cce', 'cte_numero', 'chave', 'path_xml', 'estado', 'retira', 'detalhes_retira',
        'modal', 'veiculo_id', 'tpDoc', 'descOutros', 'nDoc', 'vDocFisc'
    ];

    // 0-Remetente; 1-Expedidor; 2-Recebedor; 3-Destinatário

    public function getTomador(){
        if($this->tomador == 0) return 'Remetente';
        else if($this->tomador == 1) return 'Expedidor';
        else if($this->tomador == 2) return 'Recebedor';
        else if($this->tomador == 3) return 'Destinatário';
    }

    public function componentes(){
        return $this->hasMany('App\ComponenteCte', 'cte_id', 'id');
    }

    public function medidas(){
        return $this->hasMany('App\MedidaCte', 'cte_id', 'id');
    }

    public function natureza(){
        return $this->belongsTo(NaturezaOperacao::class, 'natureza_id');
    }

    public function somaDespesa(){
        $total = 0;
        foreach($this->despesas as $d){
            $total += $d->valor;
        }
        return $total;
    }

    public function somaReceita(){
        $total = 0;
        foreach($this->receitas as $r){
            $total += $r->valor;
        }
        return $total;
    }

    public function destinatario(){
        return $this->belongsTo(Contact::class, 'destinatario_id');
    }

    public function veiculo(){
        return $this->belongsTo(Veiculo::class, 'veiculo_id');
    }

    public function remetente(){
        return $this->belongsTo(Contact::class, 'remetente_id');
    }

    public function municipioTomador(){
        return $this->belongsTo(Cidades::class, 'municipio_tomador');
    }

    public function municipioEnvio(){
        return $this->belongsTo(Cidades::class, 'municipio_envio');
    }

    public function lastCTe(){
        $cte = Cte::
        where('cte_numero', '>', 0)
        ->where('business_id', $this->business_id)
        ->orderBy('cte_numero', 'desc')
        ->first();

        $config = Business::find($this->business_id);
        
        if($cte == null){
            return $config->ultimo_numero_cte;
        }

        if($config->ultimo_numero_cte > $cte->cte_numero){
            return $config->ultimo_numero_nfe;
        }else{
            return $cte->cte_numero;
        }
    }

    public static function lastCTeAux($business_id){
        $cte = Cte::
        where('cte_numero', '>', 0)
        ->where('business_id', $business_id)
        ->orderBy('cte_numero', 'desc')
        ->first();

        $config = Business::find($business_id);

        if($cte == null){
            return $config->ultimo_numero_cte;
        }

        if($config->ultimo_numero_cte > $cte->cte_numero){
            return $config->ultimo_numero_nfe;
        }else{
            return $cte->cte_numero;
        }
    }

    public static function unidadesMedida(){
        return [
            '00' => 'M3',
            '01' => 'KG',
            '02' => 'TON',
            '03' => 'UNIDADE',
            '04' => 'M2',
        ];
    }

    public static function modals(){
        return [
            '01' => 'RODOVIARIO',
            '02' => 'AEREO',
            '03' => 'AQUAVIARIO',
            '04' => 'FERROVIARIO', 
            '05' => 'DUTOVIARIO', 
            '06' => 'MULTIMODAL',
        ];
    }

    public static function tiposMedida(){
        return [
            'PESO BRUTO' => 'PESO BRUTO',
            'PESO DECLARADO' => 'PESO DECLARADO',
            'PESO CUBADO' => 'PESO CUBADO',
            'PESO AFORADO' => 'PESO AFORADO', 
            'PESO AFERIDO' => 'PESO AFERIDO',
            'LITRAGEM' => 'LITRAGEM', 
            'CAIXAS' => 'CAIXAS'
        ];
    }

    public static function tiposTomador(){
        return [
            '0' => 'Remetente',
            '1' => 'Expedidor', 
            '2' => 'Recebedor',
            '3' => 'Destinatário'
        ];
    }

    public static function gruposCte(){
        return [
            'ide',
            'toma03',
            'toma04',
            'enderToma',
            'autXML',
            'compl',
            'ObsCont',
            'ObsFisco',
            'emit',
            'enderEmit',
            'rem',
            'enderReme',
            'infNF',
            'infOutros',
            'infUnidTransp',
            'IacUnidCarga',
            'infUnidCarga',
            'exped',
            'enderExped',
            'receb',
            'enderReceb',
            'dest',
            'enderDest',
            'vPrest',
            'Comp',
            'imp',
            'ICMS',
            'infQ',
            'docAnt'
        ];
    }

    public static function filtroData($dataInicial, $dataFinal, $estado){
        $c = Cte::
        whereBetween('data_registro', [$dataInicial, 
            $dataFinal]);

        if($estado != 'TODOS') $c->where('ctes.estado', $estado);

        return $c->get();
    }

    public static function filtroDataCliente($cliente, $dataInicial, $dataFinal, $estado){
        $c = Cte::
        select('ctes.*')
        ->join('clientes', 'clientes.id' , '=', 'ctes.cliente_id')
        ->where('clientes.razao_social', 'LIKE', "%$cliente%")

        ->whereBetween('data_registro', [$dataInicial, 
            $dataFinal]);

        if($estado != 'TODOS') $c->where('ctes.estado', $estado);
        return $c->get();
    }

    public static function filtroCliente($cliente, $estado){
        $c = Cte::
        select('ctes.*')
        ->join('clientes', 'clientes.id' , '=', 'ctes.cliente_id')
        ->where('clientes.razao_social', 'LIKE', "%$cliente%");

        if($estado != 'TODOS') $c->where('ctes.estado', $estado);

        return $c->get();
    }

    public static function filtroEstado($estado){
        $c = Cte::
        where('ctes.estado', $estado);

        return $c->get();
    }
}
