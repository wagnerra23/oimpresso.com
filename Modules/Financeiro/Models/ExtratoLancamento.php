<?php

declare(strict_types=1);

namespace Modules\Financeiro\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Financeiro\Models\Concerns\BusinessScope;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Lançamento de extrato bancário sincronizado via API do banco.
 *
 * Append-only: a tabela tem UNIQUE `(conta_bancaria_id, idempotency_key)`
 * — re-sync gera UPDATE no mesmo registro, não cria duplicata. Job
 * `SyncBankStatementsJob` faz upsert.
 *
 * @see US-RB-046
 */
class ExtratoLancamento extends Model
{
    use HasFactory, BusinessScope, LogsActivity;

    /**
     * Wave 17 D7 — audit trail conciliação bancária (CTN Art. 195 5 anos +
     * BCB 3.978/2020 audit trail). Extrato é base da conciliação tributária;
     * mudanças em valor/tipo/data DEVEM ser audit-trailed. NÃO loga descricao
     * (PII contraparte sem necessidade — privacidade by-design LGPD Art. 6º VII).
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['conta_bancaria_id', 'data', 'valor', 'tipo', 'idempotency_key'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('financeiro.extrato_lancamento');
    }

    protected $table = 'fin_extrato_lancamentos';

    protected $fillable = [
        'business_id',
        'conta_bancaria_id',
        'data',
        'valor',
        'tipo',
        'descricao',
        'contraparte_documento',
        'contraparte_nome',
        'idempotency_key',
        'raw_payload',
    ];

    protected $casts = [
        'data'        => 'date',
        'valor'       => 'float',
        'raw_payload' => 'array',
    ];

    public function contaBancaria(): BelongsTo
    {
        return $this->belongsTo(ContaBancaria::class, 'conta_bancaria_id');
    }
}
