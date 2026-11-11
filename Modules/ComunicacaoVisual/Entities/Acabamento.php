<?php

namespace Modules\ComunicacaoVisual\Entities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\ComunicacaoVisual\Entities\Concerns\EscopoPorNegocioDaSessao;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

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
    use EscopoPorNegocioDaSessao; // Tier 0 ADR 0093 — mesmo filtro da closure antiga, reconhecido pelo MultiTenantScopeArchitectureTest
    use SoftDeletes;
    use LogsActivity;

    /**
     * Audit trail Spatie ActivityLog (Wave 26 D7 LGPD compliance).
     * Loga apenas mudanças preço/ativo — catálogo sem PII.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['nome', 'tipo', 'preco', 'ativo'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('comvis.acabamento');
    }

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
        static::creating(function (Acabamento $row) {
            if ($row->business_id === null) {
                $row->business_id = session('user.business_id') ?? session('business.id')
                    ?? throw new \LogicException('Comunicação Visual: sem business_id para criar '.$row::class.' (sem sessão). Passe o business_id explícito.');
            }
        });
    }

    public function scopeAtivos(Builder $query): Builder
    {
        return $query->where('cv_acabamentos.ativo', true);
    }
}
