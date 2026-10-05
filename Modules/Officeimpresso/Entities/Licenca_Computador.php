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
     * licenças desktop bridge Delphi (block/unblock, troca de serial, alteração
     * de versão_exe, validade) via Spatie ActivityLog (logFillable).
     * Os segredos do desktop (`senha`, `contra_senha`) ficam FORA do `fillable`
     * desde 2026-10-05 (Officeimpresso thread 03): não entram por mass-assignment
     * e não chegam ao activity_log. As colunas seguem na tabela por decisão [W] D4
     * (NÃO dropar); o servidor não as grava desde a thread 02 (#8365).
     * ([ADR 0094](../../../../memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md) §LGPD Art. 6).
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
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
