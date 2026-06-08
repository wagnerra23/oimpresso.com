<?php

namespace Modules\Jana\Entities\Mcp;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ADR 0234 (Onda 1.1) — execução append-only de automação (audit).
 *
 * Espelha McpSkillVersion (append-only puro): cada run é um INSERT, nunca
 * UPDATE/DELETE. Segue a imutabilidade de audit (ENFORCEMENT.md §L7).
 *
 * Sem business_id by design — herda a natureza global de McpAutomation (infra
 * de plataforma, ADR 0093 exceção). O registry não lê dados de tenant.
 *
 * @property int     $id
 * @property int     $automation_id
 * @property Carbon  $ran_at
 * @property string  $status          ok|warn|fail|skip
 * @property ?string $detail
 * @property ?string $actor           scheduler|claude-code:SessionStart|username...
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 */
class McpAutomationRun extends Model
{
    protected $table = 'mcp_automation_runs';

    protected $fillable = [
        'automation_id',
        'ran_at',
        'status',
        'detail',
        'actor',
    ];

    protected $casts = [
        'ran_at' => 'datetime',
    ];

    public function automation(): BelongsTo
    {
        return $this->belongsTo(McpAutomation::class, 'automation_id');
    }
}
