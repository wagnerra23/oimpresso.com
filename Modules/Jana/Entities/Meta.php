<?php

namespace Modules\Jana\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Meta — um KPI com alvo.
 *
 * Tenancy híbrida: business_id NULL = meta da plataforma (superadmin-only).
 * Ver adr/arq/0001-tenancy-hibrida.md.
 *
 * D7 LGPD audit trail — Wave 10 (2026-05-16): LogsActivity registra
 * mudanças no contrato (ativo/inativo, slug, nome, unidade) — base do
 * audit de governança de metas. Stack IA canônica preservada ([ADR 0035]).
 */
class Meta extends Model
{
    use HasBusinessScope;
    use LogsActivity;

    protected $table = 'jana_metas';

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

    /**
     * D7 LGPD audit — meta é entidade de governança (KPI). Logga ciclo
     * de vida (ativo/origem) + mudanças no slug (chave estável usada por
     * agregadores externos). Sem PII direta.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('jana_meta')
            ->logOnly(['slug', 'nome', 'unidade', 'tipo_agregacao', 'ativo', 'origem'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
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
