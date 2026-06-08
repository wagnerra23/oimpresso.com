<?php

namespace Modules\Financeiro\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Financeiro\Models\Concerns\BusinessScope;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Baixa (pagamento) de um título.
 * Imutável após criação — estorno cria nova row com estorno_de_id apontando.
 * Idempotência via UNIQUE (business_id, idempotency_key).
 */
class TituloBaixa extends Model
{
    use HasFactory, BusinessScope, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['valor_baixa', 'data_baixa', 'meio_pagamento', 'titulo_id', 'conta_bancaria_id', 'estorno_de_id'])
            ->dontSubmitEmptyLogs()
            ->useLogName('financeiro.baixa');
    }

    protected $table = 'fin_titulo_baixas';

    public $timestamps = false;  // só created_at

    protected $fillable = [
        'business_id', 'titulo_id', 'conta_bancaria_id',
        'valor_baixa', 'juros', 'multa', 'desconto',
        'data_baixa', 'meio_pagamento',
        'idempotency_key', 'transaction_payment_id', 'estorno_de_id',
        'observacoes', 'created_by',
    ];

    protected $casts = [
        'valor_baixa' => 'decimal:4',
        'juros' => 'decimal:4',
        'multa' => 'decimal:4',
        'desconto' => 'decimal:4',
        'data_baixa' => 'date',
        'created_at' => 'datetime',
    ];

    public function titulo(): BelongsTo
    {
        return $this->belongsTo(Titulo::class, 'titulo_id');
    }

    public function contaBancaria(): BelongsTo
    {
        return $this->belongsTo(ContaBancaria::class, 'conta_bancaria_id');
    }

    public function estornoDe(): BelongsTo
    {
        return $this->belongsTo(TituloBaixa::class, 'estorno_de_id');
    }

    public function delete()
    {
        throw new \DomainException(
            'fin_titulo_baixas é append-only. Use estorno (BaixaService::estornar).'
        );
    }
}
