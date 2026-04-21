<?php

namespace Modules\PontoWr2\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Movimentos de banco de horas — append-only.
 */
class BancoHorasMovimento extends Model
{
    protected $table = 'ponto_banco_horas_movimentos';

    public $incrementing = false;
    protected $keyType = 'string';

    public $timestamps = false;

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
        'business_id', 'colaborador_config_id', 'data_referencia',
        'tipo', 'minutos', 'multiplicador', 'saldo_posterior_minutos',
        'apuracao_dia_id', 'intercorrencia_id', 'observacao',
        'usuario_id', 'created_at',
    ];

    protected $casts = [
        'data_referencia' => 'date',
        'multiplicador'   => 'decimal:2',
        'created_at'      => 'datetime',
    ];

    public const TIPO_CREDITO   = 'CREDITO';
    public const TIPO_DEBITO    = 'DEBITO';
    public const TIPO_PAGAMENTO = 'PAGAMENTO';
    public const TIPO_EXPIRACAO = 'EXPIRACAO';
    public const TIPO_AJUSTE    = 'AJUSTE';

    public function colaborador(): BelongsTo
    {
        return $this->belongsTo(Colaborador::class, 'colaborador_config_id');
    }

    public function update(array $attributes = [], array $options = [])
    {
        throw new RuntimeException('Movimentos de banco de horas são append-only.');
    }

    public function delete()
    {
        throw new RuntimeException('Movimentos de banco de horas não podem ser deletados.');
    }
}
