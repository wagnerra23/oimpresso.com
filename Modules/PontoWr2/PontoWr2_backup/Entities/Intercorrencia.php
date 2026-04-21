<?php

namespace Modules\PontoWr2\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Intercorrencia extends Model
{
    use SoftDeletes;

    protected $table = 'ponto_intercorrencias';

    public $incrementing = false;
    protected $keyType = 'string';

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    protected $fillable = [
        'business_id',
        'colaborador_config_id',
        'codigo',
        'tipo',
        'data',
        'intervalo_inicio',
        'intervalo_fim',
        'dia_todo',
        'justificativa',
        'anexo_path',
        'estado',
        'prioridade',
        'impacta_apuracao',
        'descontar_banco_horas',
        'solicitante_id',
        'aprovador_id',
        'aprovado_em',
        'motivo_rejeicao',
    ];

    protected $casts = [
        'data'                  => 'date',
        'aprovado_em'           => 'datetime',
        'dia_todo'              => 'boolean',
        'impacta_apuracao'      => 'boolean',
        'descontar_banco_horas' => 'boolean',
    ];

    public const ESTADO_RASCUNHO  = 'RASCUNHO';
    public const ESTADO_PENDENTE  = 'PENDENTE';
    public const ESTADO_APROVADA  = 'APROVADA';
    public const ESTADO_REJEITADA = 'REJEITADA';
    public const ESTADO_APLICADA  = 'APLICADA';
    public const ESTADO_CANCELADA = 'CANCELADA';

    public function colaborador(): BelongsTo
    {
        return $this->belongsTo(Colaborador::class, 'colaborador_config_id');
    }

    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(config('pontowr2.ultimatepos.user_model'), 'solicitante_id');
    }

    public function aprovador(): BelongsTo
    {
        return $this->belongsTo(config('pontowr2.ultimatepos.user_model'), 'aprovador_id');
    }

    public function scopePendentes($query)
    {
        return $query->where('estado', self::ESTADO_PENDENTE);
    }

    public function scopeUrgentes($query)
    {
        return $query->where('prioridade', 'URGENTE');
    }

    /**
     * Duração em minutos (considerando dia_todo ou intervalo).
     */
    public function duracaoMinutos(): int
    {
        if ($this->dia_todo) {
            return optional(optional($this->colaborador)->escalaAtual)->carga_diaria_minutos ?: 480;
        }

        if ($this->intervalo_inicio && $this->intervalo_fim) {
            return $this->intervalo_inicio->diffInMinutes($this->intervalo_fim);
        }

        return 0;
    }
}
