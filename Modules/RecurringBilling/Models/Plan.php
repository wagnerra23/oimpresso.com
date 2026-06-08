<?php

namespace Modules\RecurringBilling\Models;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Plano de assinatura — define preço + ciclo + trial.
 *
 * Multi-tenant via business_id. Toda query DEVE escopear (skill multi-tenant-patterns).
 */
class Plan extends Model
{
    use HasBusinessScope;

    use SoftDeletes;
    use LogsActivity; // D7 LGPD audit trail (Wave 14) — preço histórico

    protected $table = 'rb_plans';

    /**
     * Auditoria LGPD (D7) — registra alterações de preço/ciclo do plano.
     * Sem PII direta mas regra comercial sensível (mudança preço → impacto contratual).
     *
     * @see Modules\RecurringBilling\Config\retention.php
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'name', 'slug', 'valor', 'ciclo', 'ciclo_dias',
                'trial_days', 'ativo', 'fiscal_type',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('recurringbilling.plan');
    }

    protected $fillable = [
        'business_id', 'name', 'slug', 'description',
        'valor', 'ciclo', 'ciclo_dias', 'trial_days',
        'ativo', 'metadata',
        // v9,75 — Onda 1 schema aditivo
        'descricao_curta', 'fiscal_type', 'fiscal_cfop', 'fiscal_servico',
    ];

    protected $casts = [
        'valor'      => 'decimal:2',
        'ciclo_dias' => 'integer',
        'trial_days' => 'integer',
        'ativo'      => 'boolean',
        'metadata'   => 'array',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'plan_id');
    }

    public function scopeAtivos(Builder $q): Builder
    {
        return $q->where('ativo', true);
    }
}
