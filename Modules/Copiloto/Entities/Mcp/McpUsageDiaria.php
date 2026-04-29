<?php

namespace Modules\Copiloto\Entities\Mcp;

use Illuminate\Database\Eloquent\Model;

/**
 * MEM-MCP-1.a (ADR 0053) — Agregação diária de uso MCP por usuário.
 *
 * Materializada a partir de mcp_audit_log via job MCP-USAGE-AGG-1.
 * Idempotente: 1 linha/user/dia/business.
 */
class McpUsageDiaria extends Model
{
    protected $table = 'mcp_usage_diaria';

    protected $fillable = [
        'dia', 'user_id', 'business_id',
        'total_calls', 'calls_ok', 'calls_denied', 'calls_quota_exceeded', 'calls_error',
        'total_tokens_in', 'total_tokens_out', 'total_cache_read', 'total_cache_write',
        'custo_brl', 'top_tools', 'alertas_disparados', 'excedeu_quota',
    ];

    protected $casts = [
        'dia'                  => 'date:Y-m-d',
        'top_tools'            => 'array',
        'custo_brl'            => 'float',
        'excedeu_quota'        => 'boolean',
        'total_calls'          => 'integer',
        'calls_ok'             => 'integer',
        'calls_denied'         => 'integer',
        'calls_quota_exceeded' => 'integer',
        'calls_error'          => 'integer',
        'total_tokens_in'      => 'integer',
        'total_tokens_out'     => 'integer',
        'total_cache_read'     => 'integer',
        'total_cache_write'    => 'integer',
        'alertas_disparados'   => 'integer',
    ];

    public function scopeDoUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeUltimosDias($query, int $dias = 30)
    {
        return $query
            ->where('dia', '>=', now()->subDays($dias)->toDateString())
            ->orderByDesc('dia');
    }

    public function totalTokens(): int
    {
        return $this->total_tokens_in + $this->total_tokens_out
             + $this->total_cache_read + $this->total_cache_write;
    }

    public function taxaErro(): float
    {
        if ($this->total_calls === 0) {
            return 0.0;
        }
        return ($this->calls_denied + $this->calls_error + $this->calls_quota_exceeded)
             / $this->total_calls;
    }
}
