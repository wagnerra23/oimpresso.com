<?php

namespace Modules\PontoWr2\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BancoHorasSaldo extends Model
{
    protected $table = 'ponto_banco_horas_saldo';

    protected $fillable = [
        'business_id',
        'colaborador_config_id',
        'saldo_minutos',
        'ultima_movimentacao',
    ];

    protected $casts = [
        'ultima_movimentacao' => 'date',
    ];

    public function colaborador(): BelongsTo
    {
        return $this->belongsTo(Colaborador::class, 'colaborador_config_id');
    }

    public function movimentos(): HasMany
    {
        return $this->hasMany(
            BancoHorasMovimento::class,
            'colaborador_config_id',
            'colaborador_config_id'
        );
    }

    public function saldoFormatado(): string
    {
        $min = abs($this->saldo_minutos);
        $sinal = $this->saldo_minutos >= 0 ? '+' : '-';
        $h = intdiv($min, 60);
        $m = $min % 60;
        return sprintf('%s%02d:%02d', $sinal, $h, $m);
    }
}
