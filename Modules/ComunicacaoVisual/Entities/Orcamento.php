<?php

namespace Modules\ComunicacaoVisual\Entities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Orcamento — orçamento de serviços de comunicação visual.
 *
 * Cabeçalho do orçamento: cliente, vendedor, totais, status e validade.
 * Os itens com dimensões m² ficam em OrcamentoItem.
 *
 * Fluxo de status:
 *   rascunho → enviado → aprovado (gera OS) / recusado
 *   qualquer → expirado (quando data_validade < hoje)
 *
 * Totais são recalculados pelo Controller ao salvar (US-COMVIS-001).
 *
 * Multi-tenant Tier 0 ([ADR 0093](memory/decisions/0093-multi-tenant-isolation-tier-0.md)):
 * global scope obrigatório — filtra por business_id da sessão automaticamente.
 *
 * @property int         $id
 * @property int         $business_id
 * @property string      $numero            ORC-YYYY-NNNNN
 * @property int|null    $contato_id
 * @property int|null    $vendedor_id
 * @property string      $data_emissao
 * @property string|null $data_validade
 * @property string      $status            rascunho|enviado|aprovado|recusado|expirado
 * @property float       $subtotal
 * @property float       $desconto
 * @property float       $extras
 * @property float       $custo_instalacao
 * @property float       $custo_entrega
 * @property float       $total
 * @property string|null $observacoes
 *
 * @see memory/requisitos/ComunicacaoVisual/SPEC.md US-COMVIS-001
 */
class Orcamento extends Model
{
    use SoftDeletes;
    use LogsActivity;

    /**
     * Audit trail Spatie ActivityLog (Wave 18 D7 LGPD compliance).
     *
     * Loga apenas mudanças de status + totais (não polui DB com toda update).
     * Tabela `activity_log` shared entre módulos.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'total', 'subtotal', 'data_validade'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('comvis.orcamento');
    }


    protected $table = 'comvis_orcamentos';

    protected $fillable = [
        'business_id',
        'numero',
        'contato_id',
        'vendedor_id',
        'data_emissao',
        'data_validade',
        'status',
        'subtotal',
        'desconto',
        'extras',
        'custo_instalacao',
        'custo_entrega',
        'total',
        'observacoes',
    ];

    protected $casts = [
        'business_id'      => 'integer',
        'contato_id'       => 'integer',
        'vendedor_id'      => 'integer',
        'data_emissao'     => 'date',
        'data_validade'    => 'date',
        'subtotal'         => 'decimal:2',
        'desconto'         => 'decimal:2',
        'extras'           => 'decimal:2',
        'custo_instalacao' => 'decimal:2',
        'custo_entrega'    => 'decimal:2',
        'total'            => 'decimal:2',
    ];

    // ------------------------------------------------------------------
    // Global scope multi-tenant Tier 0 (ADR 0093)
    // ------------------------------------------------------------------

    protected static function booted(): void
    {
        static::addGlobalScope('business_id', function (Builder $query) {
            $businessId = session('user.business_id') ?? session('business.id');
            if ($businessId !== null) {
                $query->where('comvis_orcamentos.business_id', $businessId);
            }
        });

        static::creating(function (Orcamento $row) {
            if ($row->business_id === null) {
                $row->business_id = session('user.business_id') ?? session('business.id') ?? 0;
            }
        });
    }

    // ------------------------------------------------------------------
    // Relations
    // ------------------------------------------------------------------

    /**
     * Itens (linhas) deste orçamento.
     */
    public function itens(): HasMany
    {
        return $this->hasMany(OrcamentoItem::class, 'orcamento_id');
    }

    /**
     * OS gerada a partir deste orçamento aprovado.
     */
    public function os(): HasOne
    {
        return $this->hasOne(Os::class, 'orcamento_id');
    }

    // ------------------------------------------------------------------
    // Scopes
    // ------------------------------------------------------------------

    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('comvis_orcamentos.status', $status);
    }

    public function scopeAprovados(Builder $query): Builder
    {
        return $query->where('comvis_orcamentos.status', 'aprovado');
    }
}
