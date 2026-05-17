<?php

namespace Modules\ComunicacaoVisual\Entities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * InstalacaoCatalogo — catálogo de tipos de instalação (US-COMVIS-007).
 *
 * Schema SPEC §12.1:
 * - preco_base + preco_m2 + preco_km cobrem composição completa
 * - exige_nr35 dispara checklist trabalho em altura ≥2m
 * - ferramentas_necessarias_json snapshot da lista (escada/andaime/parafusadeira/EPI)
 *
 * Multi-tenant Tier 0 ([ADR 0093](memory/decisions/0093-multi-tenant-isolation-tier-0.md)):
 * global scope obrigatório.
 *
 * @property int    $id
 * @property int    $business_id
 * @property string $nome
 * @property float  $preco_base
 * @property float  $preco_m2
 * @property float  $preco_km
 * @property bool   $exige_nr35
 * @property array|null $ferramentas_necessarias_json
 * @property bool   $ativo
 *
 * @see memory/requisitos/ComunicacaoVisual/SPEC.md §12.1 US-COMVIS-007
 */
class InstalacaoCatalogo extends Model
{
    use SoftDeletes;
    use LogsActivity;

    /**
     * Audit trail Spatie ActivityLog (Wave 26 D7 LGPD compliance).
     * Catálogo de tipos de instalação — sem PII. Loga preço/NR-35.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['nome', 'preco_base', 'preco_m2', 'preco_km', 'exige_nr35', 'ativo'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('comvis.instalacao_catalogo');
    }

    protected $table = 'cv_instalacoes_catalogo';

    protected $fillable = [
        'business_id',
        'nome',
        'preco_base',
        'preco_m2',
        'preco_km',
        'exige_nr35',
        'ferramentas_necessarias_json',
        'ativo',
        'observacoes',
    ];

    protected $casts = [
        'business_id'                  => 'integer',
        'preco_base'                   => 'decimal:2',
        'preco_m2'                     => 'decimal:2',
        'preco_km'                     => 'decimal:2',
        'exige_nr35'                   => 'boolean',
        'ferramentas_necessarias_json' => 'array',
        'ativo'                        => 'boolean',
    ];

    // ------------------------------------------------------------------
    // Global scope multi-tenant Tier 0 (ADR 0093)
    // ------------------------------------------------------------------

    protected static function booted(): void
    {
        static::addGlobalScope('business_id', function (Builder $query) {
            $businessId = session('user.business_id') ?? session('business.id');
            if ($businessId !== null) {
                $query->where('cv_instalacoes_catalogo.business_id', $businessId);
            }
        });

        static::creating(function (InstalacaoCatalogo $row) {
            if ($row->business_id === null) {
                $row->business_id = session('user.business_id') ?? session('business.id') ?? 0;
            }
        });
    }

    public function instalacoes(): HasMany
    {
        return $this->hasMany(Instalacao::class, 'catalogo_id');
    }

    public function scopeAtivos(Builder $query): Builder
    {
        return $query->where('cv_instalacoes_catalogo.ativo', true);
    }
}
