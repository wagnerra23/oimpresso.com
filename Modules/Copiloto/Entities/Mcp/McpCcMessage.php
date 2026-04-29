<?php

namespace Modules\Copiloto\Entities\Mcp;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * MEM-CC-1 — Mensagem individual de uma session Claude Code.
 */
class McpCcMessage extends Model
{
    protected $table = 'mcp_cc_messages';

    protected $fillable = [
        'session_id', 'msg_uuid', 'parent_uuid',
        'user_id', 'business_id',
        'msg_type', 'role', 'tool_name',
        'content_text', 'content_json', 'blob_id',
        'tokens_in', 'tokens_out', 'cache_read', 'cache_write', 'cost_usd',
        'ts',
    ];

    protected $casts = [
        'content_json' => 'array',
        'tokens_in' => 'integer',
        'tokens_out' => 'integer',
        'cache_read' => 'integer',
        'cache_write' => 'integer',
        'cost_usd' => 'float',
        'ts' => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(McpCcSession::class, 'session_id');
    }

    public function blob(): BelongsTo
    {
        return $this->belongsTo(McpCcBlob::class, 'blob_id');
    }

    /**
     * Full-text search no content_text + filtro RBAC.
     */
    public function scopeBuscarTexto($q, string $termo)
    {
        if (trim($termo) === '') return $q;
        return $q->whereRaw(
            'MATCH(content_text) AGAINST(? IN NATURAL LANGUAGE MODE)',
            [$termo]
        );
    }

    public function scopeAcessivelPara($q, ?\App\User $user)
    {
        if ($user === null) return $q->whereRaw('1=0');
        if (method_exists($user, 'can') && $user->can('copiloto.cc.read.all')) {
            return $q;
        }
        return $q->where('user_id', $user->id);
    }
}
