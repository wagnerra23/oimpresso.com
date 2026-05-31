<?php

namespace Modules\Financeiro\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Financeiro\Models\Concerns\BusinessScope;

/**
 * Linha de extrato bancário importada (OFX/CNAB) — Conciliação OFX, Onda 19 #49.
 *
 * Mapeia a tabela EXISTENTE `fin_bank_statement_lines` (migration
 * 2026_05_19_220000). NÃO cria schema — só dá a esta tabela o mesmo
 * tratamento Eloquent das demais Models do Financeiro.
 *
 * MOTIVO (B3 · Tier 0 hardening, ADR 0093): antes a tabela só era acessada via
 * `DB::table(...)` cru no ConciliacaoController, sem o NET do global scope
 * BusinessScope. O isolamento multi-tenant dependia 100% de cada query repetir
 * `where('business_id', …)` na mão — qualquer query futura que esquecesse
 * vazaria cross-tenant. Esta Model adiciona o global scope por business_id por
 * cima (defesa em profundidade), mantendo o `where('business_id', …)` explícito
 * dos callers como segunda camada (padrão do módulo).
 *
 * Lifecycle do status: pendente → sugerido → conciliado | ignorado
 * (reabrir() volta pra pendente). Tabela append-only de auditoria de import.
 */
class BankStatementLine extends Model
{
    use HasFactory, SoftDeletes, BusinessScope;

    protected $table = 'fin_bank_statement_lines';

    protected $fillable = [
        'business_id',
        'conta_bancaria_id',
        'fitid',
        'data_movimento',
        'descricao',
        'valor',
        'tipo',
        'memo',
        'status',
        'titulo_id',
        'conciliado_by',
        'conciliado_at',
        'match_score',
        'source_file',
        'uploaded_by',
    ];

    protected $casts = [
        'data_movimento' => 'date',
        'valor' => 'decimal:4',       // decimal(15,4) — mesmo padrão de Titulo.valor_total
        'match_score' => 'decimal:2', // decimal(5,2) 0.00-1.00 confiança do match
        'conciliado_at' => 'datetime',
    ];

    /** Título conciliado a esta linha (FK fin_titulos.id, nullable). */
    public function titulo(): BelongsTo
    {
        return $this->belongsTo(Titulo::class, 'titulo_id');
    }
}
