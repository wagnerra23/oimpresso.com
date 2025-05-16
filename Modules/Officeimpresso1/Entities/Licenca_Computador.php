<?php

namespace Modules\Officeimpresso\Entities;

use Illuminate\Database\Eloquent\Model;

class Licenca_Computador extends Model
{
    protected $table = 'licenca_computador';

    protected $fillable = [
        'business_id',
        'hd',
        'user_win',
        'bloqueado',
        'tipodeacesso',
        'conexao',
        'usuario',
        'senha',
        'sistema_operacional',
        'ip_interno',
        'antivirus',
        'pasta_instalacao',
        'versao_exe',
        'versao_banco',
        'data',
        'dt_ultima_assistencia',
        'backup_automatico',
        'paf',
        'processador',
        'memoria',
        'velocidade_conexao',
        'impressora_fiscal',
        'leitor_barras',
        'gera_mensalidade',
        'hostname',
        'liberado',
        'dt_validade',
        'serial',
        'contra_senha',
        'oculto',
        'valor',
        'motivo',
        'caminho_banco',
        'dt_ultimo_acesso',
        'token',
        'dt_cadastro',
        'descricao',
        'exe_path',
        'exe_caminho_banco',
        'exe_versao',   
        'updated_at',
        'created_at'
        // Adicione outros campos conforme necessário
    ];

    // Defina relações se houver
    public function business()
    {
        return $this->belongsTo('App\Business');
    }
}
