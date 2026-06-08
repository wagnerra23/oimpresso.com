<?php

declare(strict_types=1);

namespace Modules\TeamMcp\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * ADR 0081 — Identity Mesh actor (humano ou IA conectada).
 *
 * Cada actor tem manifest declarado: trust_level, modules_write/read/blocked,
 * skills_required, actions_blocked, audit_required, parent_actor.
 *
 * Tokens MCP bind a actor_id; resolver canonical em ActorResolver service.
 *
 * **D7 LGPD (Wave 15 governance v3 RESCUE):**
 * - PII surface: `slug` (identificador pessoal), `display_name` (nome completo),
 *   `user_id` (link pra users → email/CPF), `notes` (texto livre).
 * - Auditoria via Spatie LogsActivity (audit_log append-only, não purgada).
 * - Retention em `Modules/TeamMcp/Config/retention.php` (LGPD Art. 16).
 */
class McpActor extends Model
{
    use LogsActivity;

    protected $table = 'mcp_actors';

    protected $fillable = [
        'slug', 'type', 'trust_level', 'parent_actor_id',
        'modules_write', 'modules_read', 'modules_blocked',
        'skills_required', 'actions_blocked',
        'audit_required',
        'user_id', 'display_name',
        'created_by_actor_id', 'revoked_at', 'revoked_by_actor_id',
        'notes',
    ];

    protected $casts = [
        'modules_write'   => 'array',
        'modules_read'    => 'array',
        'modules_blocked' => 'array',
        'skills_required' => 'array',
        'actions_blocked' => 'array',
        'audit_required'  => 'boolean',
        'revoked_at'      => 'datetime',
    ];

    /**
     * Auditoria LGPD — registra mudanças sensíveis em actor (Identity Mesh).
     * D7 LGPD compliance (audit trail append-only via activity_log).
     *
     * `notes` NÃO é logado (texto livre — risco PII bruta no audit log; basta
     * o `dirty` indicar que foi tocado). Senhas/tokens NUNCA passam por aqui
     * (Tier 0 segredo — ADR 0081: token raw nem persiste, hash em outra tabela).
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'slug', 'type', 'trust_level', 'parent_actor_id',
                'modules_write', 'modules_read', 'modules_blocked',
                'skills_required', 'actions_blocked',
                'audit_required', 'user_id', 'display_name',
                'revoked_at', 'revoked_by_actor_id',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('teammcp.actor');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_actor_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_actor_id');
    }

    public function isAi(): bool
    {
        return $this->type === 'ai_agent';
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    /**
     * Pra IA-pareada com humano, retorna o slug do humano que ela representa.
     * Pra humanos diretos, retorna próprio slug.
     */
    public function effectiveHumanSlug(): string
    {
        if ($this->isAi() && $this->parent_actor_id) {
            $parent = $this->parent;
            if ($parent && !$parent->isRevoked()) {
                return $parent->slug;
            }
        }
        return $this->slug;
    }

    /**
     * Pra IA-pareada, retorna user_id do parent humano. Pra humanos, próprio user_id.
     */
    public function effectiveHumanUserId(): ?int
    {
        if ($this->isAi() && $this->parent_actor_id) {
            $parent = $this->parent;
            if ($parent && !$parent->isRevoked() && $parent->user_id) {
                return (int) $parent->user_id;
            }
        }
        return $this->user_id ? (int) $this->user_id : null;
    }

    public function canWriteModule(string $module): bool
    {
        $writes = $this->modules_write ?? [];
        $blocked = $this->modules_blocked ?? [];

        if (in_array($module, $blocked, true)) {
            return false;
        }
        if (in_array('*', $writes, true)) {
            return true;
        }
        return in_array($module, $writes, true);
    }

    public function canReadModule(string $module): bool
    {
        $reads = $this->modules_read ?? [];
        $blocked = $this->modules_blocked ?? [];

        if (in_array($module, $blocked, true)) {
            return false;
        }
        if (in_array('*', $reads, true)) {
            return true;
        }
        return in_array($module, $reads, true);
    }

    public function isActionBlocked(string $action): bool
    {
        return in_array($action, $this->actions_blocked ?? [], true);
    }
}
