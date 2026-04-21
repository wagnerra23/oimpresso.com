<?php

namespace Modules\PontoWr2\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApuracaoDia extends Model
{
    protected $table = 'ponto_apuracao_dia';

    protected $fillable = [
        'business_id', 'colaborador_config_id', 'data', 'escala_id',
        'prevista_entrada', 'prevista_saida', 'prevista_carga_minutos',
        'realizada_entrada', 'realizada_saida',
        'realizada_trabalhada_minutos', 'realizada_intrajornada_minutos',
        'atraso_minutos', 'saida_antecipada_minutos', 'falta_minutos',
        'he_diurna_minutos', 'he_noturna_minutos', 'adicional_noturno_minutos',
        'dsr_repercussao_minutos',
        'interjornada_violacao_minutos', 'intrajornada_violacao_minutos',
        'banco_horas_credito_minutos', 'banco_horas_debito_minutos',
        'estado', 'qtd_intercorrencias', 'qtd_marcacoes',
        'divergencias', 'calculado_em',
    ];

    protected $casts = [
        'data'         => 'date',
        'divergencias' => 'array',
        'calculado_em' => 'datetime',
    ];

    public const ESTADO_PENDENTE     = 'PENDENTE';
    public const ESTADO_CALCULADO    = 'CALCULADO';
    public const ESTADO_DIVERGENCIA  = 'DIVERGENCIA';
    public const ESTADO_AJUSTADO     = 'AJUSTADO';
    public const ESTADO_CONSOLIDADO  = 'CONSOLIDADO';
    public const ESTADO_FECHADO      = 'FECHADO';

    public function colaborador(): BelongsTo
    {
        return $this->belongsTo(Colaborador::class, 'colaborador_config_id');
    }

    public function escala(): BelongsTo
    {
        return $this->belongsTo(Escala::class);
    }
}
