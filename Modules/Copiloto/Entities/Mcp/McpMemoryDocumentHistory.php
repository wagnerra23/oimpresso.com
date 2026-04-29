<?php

namespace Modules\Copiloto\Entities\Mcp;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * MEM-MCP-1.a (ADR 0053) — History append-only de mcp_memory_documents.
 *
 * Cada UPDATE no documento gera snapshot aqui. Tabela IMUTÁVEL.
 */
class McpMemoryDocumentHistory extends Model
{
    public $timestamps = false; // só created_at via default useCurrent()

    protected $table = 'mcp_memory_documents_history';

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
