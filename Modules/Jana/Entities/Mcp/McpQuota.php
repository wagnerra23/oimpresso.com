<?php

namespace Modules\Jana\Entities\Mcp;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * MEM-MCP-1.a (ADR 0053) — Quota de uso MCP por usuário e período.
 *
 * Quando current_usage >= limit, MCP retorna 429 (block_on_exceed=true)
 * ou só dispara alerta (false). Reset automático em reset_at.
 *
 * REPO-WIDE: ADR 0053 quota per-user (isolamento via user_id, não business_id).
 * Sem `business_id` by design. Wave 25 SATURATION marker explícito pra rubrica
 * D1.c v3.2 hardened.
 *
 * D7 LGPD audit trail — Wave 18 SATURATION (2026-05-16): LogsActivity registra
 * mudanças no contrato da quota (period, kind, limit, block_on_exceed, ativo)
 * — auditoria de quem mexeu nas regras de billing/cap. NÃO loga current_usage
 * (incremento diário causa flood).
 */
class McpQuota extends Model
{
    use LogsActivity;

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
     * D7 LGPD audit — logga apenas o contrato da quota, NÃO current_usage
     * (que muda toda chamada MCP — log flood). Reset_at também fica fora.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('mcp_quota')
            ->logOnly(['user_id', 'period', 'kind', 'limit', 'block_on_exceed', 'ativo'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

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
