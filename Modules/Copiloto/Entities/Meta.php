<?php

namespace Modules\Copiloto\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\Copiloto\Scopes\ScopeByBusiness;

/**
 * Meta — um KPI com alvo.
 *
 * Tenancy híbrida: business_id NULL = meta da plataforma (superadmin-only).
 * Ver adr/arq/0001-tenancy-hibrida.md.
 */
class Meta extends Model
{
    protected $table = 'copiloto_metas';

    protected $fillable = [
        'business_id',
        'slug',
        'nome',
        'unidade',
        'tipo_agregacao',
        'ativo',
        'criada_por_user_id',
        'origem',
    ];

    protected $casts = [
        'ativo' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new ScopeByBusiness);
    }

    public function periodos(): HasMany
    {
        return $this->hasMany(MetaPeriodo::class, 'meta_id');
    }

    public function periodoAtual(): HasOne
    {
        return $this->hasOne(MetaPeriodo::class, 'meta_id')
            ->where('data_ini', '<=', now())
            ->where('data_fim', '>=', now());
    }

    public function fonte(): HasOne
    {
        return $this->hasOne(MetaFonte::class, 'meta_id');
    }

    public function apuracoes(): HasMany
    {
        return $this->hasMany(MetaApuracao::class, 'meta_id');
    }

    public function ultimaApuracao(): HasOne
    {
        return $this->hasOne(MetaApuracao::class, 'meta_id')->latestOfMany('data_ref');
    }
}
