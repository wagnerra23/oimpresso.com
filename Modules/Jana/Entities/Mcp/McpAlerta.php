<?php

namespace Modules\Jana\Entities\Mcp;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;

/**
 * MEM-MCP-1.a (ADR 0053) — Alerta configurável sobre uso MCP.
 *
 * Multi-tenant Tier 0 (ADR 0093) — Wave 15: business_id direto + scope global.
 */
class McpAlerta extends Model
{
    use HasBusinessScope;

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
