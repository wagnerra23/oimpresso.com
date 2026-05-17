<?php

namespace Modules\Jana\Entities\Mcp;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * MEM-MCP-1.a (ADR 0053) — Alerta configurável sobre uso MCP.
 *
 * Multi-tenant Tier 0 (ADR 0093) — Wave 15: business_id direto + scope global.
 *
 * D7 LGPD audit trail — Wave 18 SATURATION (2026-05-16): LogsActivity registra
 * mudanças no contrato do alerta (kind, threshold, canal, ativo) — base do
 * audit de governança de alertas MCP. Sem PII direta.
 */
class McpAlerta extends Model
{
    use HasBusinessScope;
    use LogsActivity;

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

    /**
     * D7 LGPD audit — mudanças no contrato do alerta (kind, threshold, canal, ativo).
     * NÃO loga config_extra (pode conter parâmetros customizáveis livres).
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('mcp_alerta')
            ->logOnly(['kind', 'threshold', 'canal', 'ativo'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function scopeAtivos($query)
    {
        return $query->where('ativo', true);
    }

    public function scopeKind($query, string $kind)
    {
        return $query->where('kind', $kind);
    }
}
