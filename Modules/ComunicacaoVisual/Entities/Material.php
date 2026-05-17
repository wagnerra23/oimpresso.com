<?php

namespace Modules\ComunicacaoVisual\Entities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Material — catálogo de materiais para comunicação visual.
 *
 * Cada business mantém seu próprio catálogo de materiais (lona, vinil adesivo,
 * ACM, MDF, plotter de vinil, etc.) com preços de custo e venda por m².
 *
 * Usado pelo cálculo de orçamento (US-COMVIS-001): ao selecionar um material,
 * o preco_venda_m2 é copiado pro item do orçamento (pode ser sobrescrito).
 *
 * Multi-tenant Tier 0 ([ADR 0093](memory/decisions/0093-multi-tenant-isolation-tier-0.md)):
 * global scope obrigatório — filtra por business_id da sessão automaticamente.
 *
 * @property int         $id
 * @property int         $business_id
 * @property string      $nome
 * @property string      $categoria
 * @property string      $unidade           m2|unidade|metro_linear
 * @property int|null    $gramatura_g_m2
 * @property float       $preco_custo_m2
 * @property float       $preco_venda_m2
 * @property float|null  $estoque_minimo_m2
 * @property bool        $ativo
 * @property string|null $observacoes
 *
 * @see memory/requisitos/ComunicacaoVisual/SPEC.md US-COMVIS-001
 */
class Material extends Model
{
    use SoftDeletes;
    use LogsActivity;

    /**
     * Audit trail Spatie ActivityLog (Wave 26 D7 LGPD compliance).
     * Loga apenas mudanças preço/ativo/estoque — catálogo sem PII.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['nome', 'categoria', 'preco_venda_m2', 'preco_custo_m2', 'estoque_minimo_m2', 'ativo'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('comvis.material');
    }

    protected $table = 'comvis_materiais';

    protected $fillable = [
        'business_id',
        'nome',
        'categoria',
        'unidade',
        'gramatura_g_m2',
        'preco_custo_m2',
        'preco_venda_m2',
        'estoque_minimo_m2',
        'ativo',
        'observacoes',
    ];

    protected $casts = [
        'business_id'       => 'integer',
        'gramatura_g_m2'    => 'integer',
        'preco_custo_m2'    => 'decimal:2',
        'preco_venda_m2'    => 'decimal:2',
        'estoque_minimo_m2' => 'decimal:2',
        'ativo'             => 'boolean',
    ];

    // ------------------------------------------------------------------
    // Global scope multi-tenant Tier 0 (ADR 0093)
    // ------------------------------------------------------------------

    protected static function booted(): void
    {
        static::addGlobalScope('business_id', function (Builder $query) {
            $businessId = session('user.business_id') ?? session('business.id');
            if ($businessId !== null) {
                $query->where('comvis_materiais.business_id', $businessId);
            }
        });

        static::creating(function (Material $row) {
            if ($row->business_id === null) {
                $row->business_id = session('user.business_id') ?? session('business.id') ?? 0;
            }
        });
    }

    // ------------------------------------------------------------------
    // Relations
    // ------------------------------------------------------------------

    /**
     * Itens de orçamento que usam este material.
     */
    public function orcamentoItens(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(OrcamentoItem::class, 'material_id');
    }

    // ------------------------------------------------------------------
    // Scopes
    // ------------------------------------------------------------------

    /**
     * Apenas materiais ativos.
     */
    public function scopeAtivos(Builder $query): Builder
    {
        return $query->where('comvis_materiais.ativo', true);
    }

    /**
     * Filtrar por categoria.
     */
    public function scopeCategoria(Builder $query, string $categoria): Builder
    {
        return $query->where('comvis_materiais.categoria', $categoria);
    }
}
