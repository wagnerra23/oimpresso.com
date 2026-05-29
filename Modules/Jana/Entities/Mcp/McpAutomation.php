<?php

namespace Modules\Jana\Entities\Mcp;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ADR 0234 (Onda 1.1) — entidade canônica de automação (DB primary).
 *
 * Registry de hooks/crons/rotinas governados. Espelha a anatomia de McpSkill
 * (ADR 0076): DB primary, filesystem fonte, AutomationRegistrySync espelha,
 * drift detection em mcp_alertas_eventos.
 *
 * Sem business_id by design — infra de plataforma (ADR 0093 exceção, igual
 * McpSkill / mcp_governance_rules). Hooks (.claude/hooks/), crons (Kernel.php)
 * e rotinas (.claude/*.json) rodam no runtime do oimpresso e governam o repo
 * inteiro, não dados de tenant. A coluna business_id é mantida nullable no
 * schema (não removida) por simetria com mcp_skills e pra abrir porta a
 * automações custom por-tenant no futuro, SEM scope global aplicado aqui:
 * o registry nunca lê dados de negócio, só arquivos do repo — uma query
 * scoped esconderia rows globais no contexto do daemon MCP (errado).
 */
class McpAutomation extends Model
{
    protected $table = 'mcp_automations';

    protected $fillable = [
        'slug',
        'business_id',
        'tipo',
        'gatilho',
        'descricao',
        'arquivo',
        'owner',
        'governed_by_adr',
        'enabled',
        'last_run_at',
        'last_status',
        'last_detail',
    ];

    protected $casts = [
        'enabled'     => 'boolean',
        'last_run_at' => 'datetime',
    ];

    /** Execuções append-only (audit). */
    public function runs(): HasMany
    {
        return $this->hasMany(McpAutomationRun::class, 'automation_id');
    }

    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    public function scopeTipo($query, string $tipo)
    {
        return $query->where('tipo', $tipo);
    }
}
