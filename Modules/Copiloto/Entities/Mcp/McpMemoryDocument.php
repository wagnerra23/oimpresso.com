<?php

namespace Modules\Copiloto\Entities\Mcp;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * MEM-MCP-1.a (ADR 0053) — Documento da memory/ cacheado em DB.
 *
 * Cache governado de memory/decisions/, memory/sessions/, memory/handoff,
 * CURRENT.md, TASKS.md. MCP server (Proxmox) só lê desta tabela —
 * NUNCA acessa filesystem direto.
 *
 * Sync via job IndexarMemoryGitParaDb (webhook GitHub OU cron 5min).
 */
class McpMemoryDocument extends Model
{
    use SoftDeletes;

    protected $table = 'mcp_memory_documents';

    protected $fillable = [
        'business_id', 'slug', 'type', 'module', 'title', 'content_md',
        'scope_required', 'admin_only', 'metadata',
        'git_sha', 'git_path', 'pii_redactions_count',
        'embedding', 'indexed_at',
    ];

    protected $casts = [
        'metadata'             => 'array',
        'admin_only'           => 'boolean',
        'pii_redactions_count' => 'integer',
        'indexed_at'           => 'datetime',
    ];

    protected $hidden = ['embedding']; // BLOB, não enviar nas APIs

    public function history(): HasMany
    {
        return $this->hasMany(McpMemoryDocumentHistory::class, 'document_id');
    }

    /**
     * Filtra por empresa dona do documento (multi-tenant).
     * NULL = global (legado pré-MEM-MULTI-1 ou docs compartilhados).
     */
    public function scopeDoBusiness($query, int $businessId)
    {
        return $query->where(function ($q) use ($businessId) {
            $q->where('business_id', $businessId)
              ->orWhereNull('business_id');
        });
    }

    public function scopeAcessiveisPara($query, ?\App\User $user)
    {
        if ($user === null) {
            // Sem auth, só docs públicas
            return $query->whereNull('scope_required')->where('admin_only', false);
        }

        // Admin (superadmin) vê tudo
        if (method_exists($user, 'hasRole') && $user->hasRole('superadmin')) {
            return $query;
        }

        // Demais: filtra por scope_required vs Spatie permissions do user
        return $query->where(function ($q) use ($user) {
            $q->whereNull('scope_required')
              ->orWhereIn('scope_required', $user->getAllPermissions()->pluck('name'));
        })->where('admin_only', false);
    }

    public function scopeDoTipo($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeDoModulo($query, string $module)
    {
        return $query->where('module', $module);
    }

    /**
     * Full-text search nativo MySQL via FULLTEXT index.
     */
    public function scopeBuscarTexto($query, string $termo)
    {
        if (trim($termo) === '') {
            return $query;
        }
        return $query->whereRaw(
            'MATCH(title, content_md) AGAINST(? IN NATURAL LANGUAGE MODE)',
            [$termo]
        );
    }

    /**
     * Cria snapshot history e atualiza atributos. Usar dentro de transaction.
     */
    public function snapshotEAtualizar(array $atributos, ?int $userId = null, string $reason = 'webhook'): void
    {
        // Snapshot da versão atual antes de atualizar
        if ($this->exists && ! empty($this->content_md)) {
            McpMemoryDocumentHistory::create([
                'document_id'         => $this->id,
                'slug'                => $this->slug,
                'git_sha'             => $this->git_sha,
                'title'               => $this->title,
                'content_md'          => $this->content_md,
                'metadata'            => $this->metadata,
                'changed_at'          => now(),
                'changed_by_user_id'  => $userId,
                'change_reason'       => $reason,
            ]);
        }

        $this->update($atributos);
    }
}
