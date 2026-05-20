<?php

namespace Modules\Financeiro\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Financeiro\Models\Concerns\BusinessScope;

/**
 * Log de chamadas IA — Onda 23 (2026-05-20) US-FIN-029.
 *
 * Multi-tenant Tier 0 via BusinessScope.
 * NÃO usa SoftDeletes — audit log append-only.
 */
class AiUsageLog extends Model
{
    use BusinessScope;

    protected $table = 'ai_usage_log';

    protected $fillable = [
        'business_id', 'feature', 'provider', 'model', 'operation',
        'input_tokens', 'output_tokens', 'cost_usd', 'idempotency_hash',
        'status', 'error_message', 'metadata', 'user_id',
    ];

    protected $casts = [
        'input_tokens' => 'integer',
        'output_tokens' => 'integer',
        'cost_usd' => 'decimal:6',
        'metadata' => 'array',
    ];

    public static function lookupByHash(int $businessId, string $feature, string $hash): ?self
    {
        return static::query()
            ->where('business_id', $businessId)
            ->where('feature', $feature)
            ->where('idempotency_hash', $hash)
            ->where('status', 'ok')
            ->orderByDesc('id')
            ->first();
    }
}
