<?php

namespace Modules\ComunicacaoVisual\Entities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Acabamento — catálogo (corte, ilhós, costura, perfuração, laminação).
 *
 * Schema SPEC §12.1: tipo ENUM determina como preço escala
 * (m_linear, unitario, m2, fixo). Itens são referenciados em
 * `cv_ordens_producao.acabamento_json` (snapshot momento orçamento).
 *
 * Multi-tenant Tier 0 ([ADR 0093](memory/decisions/0093-multi-tenant-isolation-tier-0.md)):
 * global scope obrigatório.
 *
 * @property int    $id
 * @property int    $business_id
 * @property string $nome
 * @property string $tipo          m_linear|unitario|m2|fixo
 * @property float  $preco
 * @property bool   $ativo
 *
 * @see memory/requisitos/ComunicacaoVisual/SPEC.md §12.1
 */
class Acabamento extends Model
{
    use SoftDeletes;

    protected $table = 'cv_acabamentos';

    protected $fillable = [
        'business_id',
        'nome',
        'tipo',
        'preco',
        'ativo',
        'observacoes',
    ];

    protected $casts = [
        'business_id' => 'integer',
        'preco'       => 'decimal:2',
        'ativo'       => 'boolean',
    ];

    // ------------------------------------------------------------------
    // Global scope multi-tenant Tier 0 (ADR 0093)
    // ------------------------------------------------------------------

    protected static function booted(): void
    {
        static::addGlobalScope('business_id', function (Builder $query) {
            $businessId = session('user.business_id') ?? session('business.id');
            if ($businessId !== null) {
                $query->where('cv_acabamentos.business_id', $businessId);
            }
        });

        static::creating(function (Acabamento $row) {
            if ($row->business_id === null) {
                $row->business_id = session('user.business_id') ?? session('business.id') ?? 0;
            }
        });
    }

    public function scopeAtivos(Builder $query): Builder
    {
        return $query->where('cv_acabamentos.ativo', true);
    }
}
