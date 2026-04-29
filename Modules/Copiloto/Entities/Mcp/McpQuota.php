<?php

namespace Modules\Copiloto\Entities\Mcp;

use Illuminate\Database\Eloquent\Model;

/**
 * MEM-MCP-1.a (ADR 0053) — Quota de uso MCP por usuário e período.
 *
 * Quando current_usage >= limit, MCP retorna 429 (block_on_exceed=true)
 * ou só dispara alerta (false). Reset automático em reset_at.
 */
class McpQuota extends Model
{
    protected $table = 'mcp_quotas';

    protected $fillable = [
        'user_id', 'period', 'kind', 'limit', 'current_usage',
        'reset_at', 'block_on_exceed', 'ativo',
    ];

    protected $casts = [
        'limit'           => 'float',
        'current_usage'   => 'float',
        'reset_at'        => 'datetime',
        'block_on_exceed' => 'boolean',
        'ativo'           => 'boolean',
    ];

    /**
     * @return bool true se passou da quota
     */
    public function excedeu(): bool
    {
        return $this->current_usage >= $this->limit;
    }

    /**
     * @return float % de uso (0.0 a 1.0+ se excedeu)
     */
    public function percentualUso(): float
    {
        return $this->limit > 0 ? $this->current_usage / $this->limit : 0;
    }

    public function resetar(): void
    {
        $this->update([
            'current_usage' => 0,
            'reset_at'      => $this->calcularProximoReset(),
        ]);
    }

    public function calcularProximoReset(): \Carbon\Carbon
    {
        return match ($this->period) {
            'daily'   => now()->addDay()->startOfDay(),
            'weekly'  => now()->addWeek()->startOfWeek(),
            'monthly' => now()->addMonth()->startOfMonth(),
            default   => now()->addMonth()->startOfMonth(),
        };
    }

    public function incrementar(float $valor): void
    {
        $this->increment('current_usage', $valor);
    }
}
