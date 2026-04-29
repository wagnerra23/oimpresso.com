<?php

namespace Modules\Copiloto\Entities\Mcp;

use Illuminate\Database\Eloquent\Model;

/**
 * MEM-MCP-1.a (ADR 0053) — Alerta configurável sobre uso MCP.
 */
class McpAlerta extends Model
{
    protected $table = 'mcp_alertas';

    protected $fillable = [
        'user_id', 'business_id', 'kind',
        'threshold', 'canal', 'ativo', 'config_extra',
    ];

    protected $casts = [
        'ativo'        => 'boolean',
        'threshold'    => 'float',
        'config_extra' => 'array',
    ];

    public function scopeAtivos($query)
    {
        return $query->where('ativo', true);
    }

    public function scopeKind($query, string $kind)
    {
        return $query->where('kind', $kind);
    }
}
