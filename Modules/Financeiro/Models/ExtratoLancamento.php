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
            // Fase 1 ADR 0236: status/titulo_id auditados (conciliação muda fato
            // tributário — quem conciliou o quê precisa de trilha, igual valor/data).
            ->logOnly(['conta_bancaria_id', 'data', 'valor', 'tipo', 'idempotency_key', 'status', 'titulo_id'])
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
        // Fase 1 ADR 0236 — workflow de conciliação na própria linha de extrato.
        'status',
        'titulo_id',
        'match_score',
        'conciliado_by',
        'conciliado_at',
    ];

    protected $casts = [
        'data'          => 'date',
        'valor'         => 'float',
        'raw_payload'   => 'array',
        'match_score'   => 'float',
        'conciliado_at' => 'datetime',
    ];

    public function contaBancaria(): BelongsTo
    {
        return $this->belongsTo(ContaBancaria::class, 'conta_bancaria_id');
    }
}
