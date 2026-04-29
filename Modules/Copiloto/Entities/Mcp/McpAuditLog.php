<?php

namespace Modules\Copiloto\Entities\Mcp;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * MEM-MCP-1.a (ADR 0053) — Audit log IMUTÁVEL de chamadas MCP.
 *
 * Append-only: nunca UPDATE/DELETE. Para LGPD, retenção mínima 1 ano.
 * Cada chamada gera 1 linha com request_id UUID. Usar `registrar()`
 * factory pra criação consistente; nunca instanciar direto.
 */
class McpAuditLog extends Model
{
    protected $table = 'mcp_audit_log';

    public $timestamps = false; // só created_at, sem updated_at

    protected $fillable = [
        'request_id', 'user_id', 'business_id', 'ts',
        'endpoint', 'tool_or_resource', 'scope_required',
        'status', 'error_code', 'error_message',
        'tokens_in', 'tokens_out', 'cache_read', 'cache_write',
        'custo_brl', 'duration_ms',
        'ip', 'user_agent', 'claude_code_session', 'mcp_token_id',
        'payload_summary', 'created_at',
    ];

    protected $casts = [
        'ts'              => 'datetime',
        'created_at'      => 'datetime',
        'tokens_in'       => 'integer',
        'tokens_out'      => 'integer',
        'cache_read'      => 'integer',
        'cache_write'     => 'integer',
        'custo_brl'       => 'float',
        'duration_ms'     => 'integer',
        'payload_summary' => 'array',
    ];

    /**
     * Factory canônica: única forma autorizada de gravar audit log.
     * Bloqueia INSERT direto sem request_id ou status.
     */
    public static function registrar(array $atributos): self
    {
        $atributos['request_id']  = $atributos['request_id'] ?? (string) Str::uuid();
        $atributos['ts']          = $atributos['ts'] ?? now();
        $atributos['created_at']  = now();

        if (! isset($atributos['endpoint'], $atributos['status'], $atributos['user_id'])) {
            throw new \InvalidArgumentException(
                'McpAuditLog::registrar exige endpoint, status, user_id'
            );
        }

        return static::create($atributos);
    }

    public function isErro(): bool
    {
        return in_array($this->status, ['error', 'denied', 'quota_exceeded'], true);
    }

    public function totalTokens(): int
    {
        return ($this->tokens_in ?? 0)
            + ($this->tokens_out ?? 0)
            + ($this->cache_read ?? 0)
            + ($this->cache_write ?? 0);
    }
}
