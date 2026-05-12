<?php

namespace Modules\ComunicacaoVisual\Entities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Substrato — catálogo SPEC §12.1 (lona/vinil/adesivo/ACM/tela/MDF/neon/letra_caixa).
 *
 * Diferente de `Material` (legacy comvis_materiais) — Substrato é o catálogo canon
 * conforme SPEC §12.1 + ROADMAP Fase 1, com tributação CNAE 1813 (NCM/CFOP/CSOSN)
 * pra wizard onboarding (US-COMVIS-006). Material legacy continua existindo até
 * migration factory unificar (Sprint 2+).
 *
 * Multi-tenant Tier 0 ([ADR 0093](memory/decisions/0093-multi-tenant-isolation-tier-0.md)):
 * global scope obrigatório.
 *
 * @property int         $id
 * @property int         $business_id
 * @property string      $nome
 * @property string      $categoria          lona|vinil|adesivo|acm|tela|mdf|neon|letra_caixa|outro
 * @property int|null    $gramatura_g_m2
 * @property float       $preco_custo_m2
 * @property float       $preco_venda_m2
 * @property float|null  $minimo_m2
 * @property string|null $ncm
 * @property string|null $cfop_padrao
 * @property string|null $csosn_padrao
 * @property int|null    $fornecedor_id
 * @property bool        $ativo
 * @property string|null $observacoes
 *
 * @see memory/requisitos/ComunicacaoVisual/SPEC.md §12.1
 */
class Substrato extends Model
{
    use SoftDeletes;

    protected $table = 'cv_substratos';

    protected $fillable = [
        'business_id',
        'nome',
        'categoria',
        'gramatura_g_m2',
        'preco_custo_m2',
        'preco_venda_m2',
        'minimo_m2',
        'ncm',
        'cfop_padrao',
        'csosn_padrao',
        'fornecedor_id',
        'ativo',
        'observacoes',
    ];

    protected $casts = [
        'business_id'    => 'integer',
        'gramatura_g_m2' => 'integer',
        'fornecedor_id'  => 'integer',
        'preco_custo_m2' => 'decimal:2',
        'preco_venda_m2' => 'decimal:2',
        'minimo_m2'      => 'decimal:3',
        'ativo'          => 'boolean',
    ];

    // ------------------------------------------------------------------
    // Global scope multi-tenant Tier 0 (ADR 0093)
    // ------------------------------------------------------------------

    protected static function booted(): void
    {
        static::addGlobalScope('business_id', function (Builder $query) {
            $businessId = session('user.business_id') ?? session('business.id');
            if ($businessId !== null) {
                $query->where('cv_substratos.business_id', $businessId);
            }
        });

        static::creating(function (Substrato $row) {
            if ($row->business_id === null) {
                $row->business_id = session('user.business_id') ?? session('business.id') ?? 0;
            }
        });
    }

    // ------------------------------------------------------------------
    // Relations
    // ------------------------------------------------------------------

    public function ordensProducao(): HasMany
    {
        return $this->hasMany(OrdemProducao::class, 'substrato_id');
    }

    // ------------------------------------------------------------------
    // Scopes
    // ------------------------------------------------------------------

    public function scopeAtivos(Builder $query): Builder
    {
        return $query->where('cv_substratos.ativo', true);
    }

    public function scopeCategoria(Builder $query, string $categoria): Builder
    {
        return $query->where('cv_substratos.categoria', $categoria);
    }
}
