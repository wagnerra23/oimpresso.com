<?php

namespace Modules\Financeiro\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Financeiro\Models\Concerns\BusinessScope;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Título financeiro (a receber ou a pagar).
 * Idempotência via UNIQUE (business_id, origem, origem_id, parcela_numero).
 *
 * Status lifecycle:
 *   aberto (valor_aberto = valor_total)
 *     → parcial (0 < valor_aberto < valor_total)
 *     → quitado (valor_aberto = 0)
 *     → cancelado (em qualquer momento via ContractCanceled / TransactionCancelled)
 */
class Titulo extends Model
{
    use HasFactory, SoftDeletes, BusinessScope, LogsActivity;

    /**
     * Formas de pagamento canônicas — espelha o enum de
     * fin_titulo_baixas.meio_pagamento. Reutilizado nas Requests (validação)
     * e na migration que cria a coluna forma_pagamento. Fonte única da verdade.
     */
    public const FORMAS_PAGAMENTO = [
        'dinheiro',
        'pix',
        'boleto',
        'cartao_credito',
        'cartao_debito',
        'transferencia',
        'cheque',
        'compensacao',
        'outro',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'valor_total', 'valor_aberto', 'vencimento', 'cliente_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('financeiro.titulo');
    }

    protected $table = 'fin_titulos';

    protected $fillable = [
        'business_id', 'numero', 'tipo', 'status',
        'cliente_id', 'cliente_descricao',
        'valor_total', 'valor_aberto', 'moeda',
        'emissao', 'vencimento', 'competencia_mes',
        'origem', 'origem_id', 'parcela_numero', 'parcela_total', 'titulo_pai_id',
        'plano_conta_id', 'categoria_id',
        'forma_pagamento',
        'observacoes', 'metadata',
        'created_by', 'updated_by',
        'conferido_by', 'conferido_at',
    ];

    protected $casts = [
        'valor_total' => 'decimal:4',
        'valor_aberto' => 'decimal:4',
        'emissao' => 'date',
        'vencimento' => 'date',
        'metadata' => 'array',
        'conferido_at' => 'datetime',
    ];

    public function conferidoPor(): BelongsTo
    {
        return $this->belongsTo(\App\User::class, 'conferido_by');
    }

    public function planoConta(): BelongsTo
    {
        return $this->belongsTo(PlanoConta::class, 'plano_conta_id');
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'categoria_id');
    }

    public function tituloPai(): BelongsTo
    {
        return $this->belongsTo(Titulo::class, 'titulo_pai_id');
    }

    public function parcelas(): HasMany
    {
        return $this->hasMany(Titulo::class, 'titulo_pai_id');
    }

    public function baixas(): HasMany
    {
        return $this->hasMany(TituloBaixa::class, 'titulo_id');
    }

    /**
     * NÃO permite hard delete; status='cancelado' é a forma correta.
     */
    public function delete()
    {
        throw new \DomainException(
            'fin_titulos não permite delete. Use cancelar() ou status=cancelado.'
        );
    }

    public function isVencido(): bool
    {
        return $this->status !== 'quitado'
            && $this->status !== 'cancelado'
            && $this->vencimento->isPast();
    }

    public function agingBucket(): string
    {
        if (! $this->isVencido()) {
            return 'em_dia';
        }

        // Carbon 3: diffInDays() é signed por padrão — o flag `true` força absoluto.
        // vencimento está no passado aqui (isVencido); sem o flag o diff vem negativo
        // e o match(true) colapsaria TODO título vencido em '<30'.
        $dias = (int) now()->diffInDays($this->vencimento, true);

        return match (true) {
            $dias < 30 => '<30',
            $dias < 60 => '30-60',
            $dias < 90 => '60-90',
            $dias < 180 => '90-180',
            default => '>180',
        };
    }
}
