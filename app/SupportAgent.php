<?php

namespace App;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Capability "suporte" do Modo Suporte (ADR 0305) — concedida/revogada por conta.
 *
 * CROSS-TENANT (sem `business_id`): um agente de suporte é global, como o superadmin.
 * NÃO recebe global scope de business — de propósito (a tabela não é dado de negócio,
 * é um registro de capability). Ativo = `revoked_at IS NULL`.
 *
 * @see App\Services\Support\SupportAccessService — resolve QUEM o agente pode acessar.
 * @see memory/decisions/0305-modo-suporte-cross-tenant-exceto-operador.md
 */
class SupportAgent extends Model
{
    protected $table = 'support_agents';

    protected $fillable = [
        'user_id',
        'granted_by',
        'reason',
        'granted_at',
        'revoked_at',
    ];

    protected $casts = [
        'granted_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    /** Concessões ativas (não revogadas). */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
