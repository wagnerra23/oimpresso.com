<?php

namespace Modules\Jana\Entities\Mcp;

use App\Concerns\BelongsToBusinessViaParent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * MEM-MCP-1.a (ADR 0053) — History append-only de mcp_memory_documents.
 *
 * Cada UPDATE no documento gera snapshot aqui. Tabela IMUTÁVEL.
 *
 * Multi-tenant Tier 0 (ADR 0093) — Wave 15: tenancy herdada via parent
 * `document` (mcp_memory_documents.business_id). Nota: parent McpMemoryDocument
 * tem tenancy híbrida (NULL = global) com scopes próprios — o scope via parent
 * aqui filtra apenas history cujo document.business_id casa session.
 */
class McpMemoryDocumentHistory extends Model
{
    use BelongsToBusinessViaParent;

    public $timestamps = false; // só created_at via default useCurrent()

    protected $table = 'mcp_memory_documents_history';

    /** Relação parent que carrega business_id (usada por ScopeByBusinessViaParent). */
    protected string $businessParentRelation = 'document';

    protected $fillable = [
        'document_id', 'slug', 'git_sha', 'title', 'content_md',
        'metadata', 'changed_at', 'changed_by_user_id', 'change_reason',
        'created_at',
    ];

    protected $casts = [
        'metadata'   => 'array',
        'changed_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(McpMemoryDocument::class, 'document_id');
    }
}
