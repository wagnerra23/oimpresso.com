<?php

namespace Modules\Financeiro\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Financeiro\Models\Concerns\BusinessScope;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Ledger de movimentação. Imutável.
 * Toda baixa de título cria 1+ movimentos.
 */
class CaixaMovimento extends Model
{
    use HasFactory, BusinessScope, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['conta_bancaria_id', 'tipo', 'valor', 'data', 'origem_tipo', 'origem_id'])
            ->dontSubmitEmptyLogs()
            ->useLogName('financeiro.caixa_movimento');
    }

    protected $table = 'fin_caixa_movimentos';

    public $timestamps = false;  // só created_at

    protected $fillable = [
        'business_id', 'conta_bancaria_id',
        'tipo', 'valor', 'data', 'saldo_apos',
        'origem_tipo', 'origem_id',
        'descricao', 'metadata', 'created_by',
    ];

    protected $casts = [
        'valor' => 'decimal:4',
        'saldo_apos' => 'decimal:4',
        'data' => 'date',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function contaBancaria(): BelongsTo
    {
        return $this->belongsTo(ContaBancaria::class, 'conta_bancaria_id');
    }

    public function delete()
    {
        throw new \DomainException(
            'fin_caixa_movimentos é append-only. Para corrigir, lance movimento de ajuste oposto.'
        );
    }
}
