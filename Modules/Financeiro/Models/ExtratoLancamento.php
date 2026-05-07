<?php

declare(strict_types=1);

namespace Modules\Financeiro\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Financeiro\Models\Concerns\BusinessScope;

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
    use HasFactory, BusinessScope;

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
