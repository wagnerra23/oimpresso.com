<?php

namespace Modules\Jana\Entities\Mcp;

use Carbon\Carbon;
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
 *
 * @property int     $id
 * @property string  $slug             basename do hook / comando do cron / slug do manifesto
 * @property ?int    $business_id      NULL = global (infra de plataforma, ADR 0093)
 * @property string  $tipo             hook_sessionstart|hook_pretooluse|hook_posttooluse|cron|routine|webhook
 * @property string  $gatilho          matcher do hook / expressão cron / descrição do trigger
 * @property ?string $descricao
 * @property string  $arquivo          path relativo ao repo
 * @property ?string $owner
 * @property ?string $governed_by_adr  slug do ADR que governa esta automação
 * @property bool    $enabled
 * @property ?Carbon $last_run_at
 * @property ?string $last_status      ok|warn|fail|skip
 * @property ?string $last_detail
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
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
