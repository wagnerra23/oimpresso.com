<?php

namespace Modules\Financeiro\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * AdvisorBusinessAccess — grant de acesso advisor → business (Onda 31 #57).
 *
 * Tabela multi-tenant: tem business_id. NÃO aplica BusinessScope global —
 * advisor olha N businesses (que é EXATAMENTE o que esse grant existe pra
 * permitir). Em vez disso, AdvisorViewScope middleware valida cada request:
 * - User UPos normal não enxerga essa tabela (não usa)
 * - Advisor logado só pode requisitar ?advisor_view=1&business_id=X
 *   se existir row aqui com (advisor_id=advisor logado, business_id=X,
 *   revoked_at IS NULL, deleted_at IS NULL).
 */
class AdvisorBusinessAccess extends Model
{
    use SoftDeletes;

    protected $table = 'advisor_business_access';

    protected $fillable = [
        'advisor_id', 'business_id', 'granted_at', 'revoked_at',
        'granted_by', 'revoked_by', 'scope_json',
    ];

    protected $casts = [
        'granted_at' => 'datetime',
        'revoked_at' => 'datetime',
        'scope_json' => 'array',
    ];

    public function advisor(): BelongsTo
    {
        return $this->belongsTo(Advisor::class);
    }

    /**
     * Scope helper: apenas grants ativos (não revogados, não soft-deleted).
     */
    public function scopeAtivo(Builder $query): Builder
    {
        return $query->whereNull('revoked_at');
    }

    /**
     * Pode ver dashboard unificado? (default true se scope_json ausente.)
     */
    public function canViewUnificado(): bool
    {
        $scope = $this->scope_json ?? [];
        return (bool) ($scope['can_view_unificado'] ?? true);
    }

    /**
     * Pode ver relatórios? (default true se scope_json ausente.)
     */
    public function canViewReports(): bool
    {
        $scope = $this->scope_json ?? [];
        return (bool) ($scope['can_view_reports'] ?? true);
    }

    /**
     * LGPD: consent registrado?
     */
    public function hasConsent(): bool
    {
        $scope = $this->scope_json ?? [];
        return ! empty($scope['consented_at']);
    }
}
