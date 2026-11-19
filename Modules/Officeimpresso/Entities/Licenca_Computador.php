<?php

namespace Modules\Officeimpresso\Entities;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Licenca_Computador extends Model
{
    use LogsActivity;

    protected $table = 'licenca_computador';

    /**
     * Auditoria LGPD Tier 0 (Wave 10 D7.b — 2026-05-16): registra mudanças em
     * licenças desktop bridge Delphi (block/unblock, alteração de versão_exe,
     * validade) via Spatie ActivityLog.
     *
     * Credenciais ficam FORA do activity_log (auditoria Officeimpresso 2026-11-05,
     * eixo 5, M2): até aqui o `logFillable()` copiava senha, contra-senha, token,
     * serial, usuário e conexão em claro para `activity_log.properties` a cada
     * save que os sujasse. O histórico registra que a licença mudou, nunca o valor
     * do segredo.
     *
     * Model sem escopo automático de business por decisão [W] 2026-11-19: ver
     * governance/multi-tenant-global-model-contract.json.
     */
    public const SEGREDOS_FORA_DO_HISTORICO = ['senha', 'contra_senha', 'token', 'serial', 'usuario', 'conexao'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logExcept(self::SEGREDOS_FORA_DO_HISTORICO)
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

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
