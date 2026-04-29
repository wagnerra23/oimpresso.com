<?php

namespace Modules\Copiloto\Entities\Mcp;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * MEM-CC-1 — Session do Claude Code de algum dev do time.
 */
class McpCcSession extends Model
{
    use SoftDeletes;

    protected $table = 'mcp_cc_sessions';

    protected $fillable = [
        'session_uuid', 'user_id', 'business_id',
        'project_path', 'git_branch', 'cc_version', 'entrypoint',
        'started_at', 'ended_at',
        'total_messages', 'total_tokens', 'total_cost_usd', 'total_cost_brl',
        'status', 'metadata', 'summary_auto',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'metadata' => 'array',
        'total_messages' => 'integer',
        'total_tokens' => 'integer',
        'total_cost_usd' => 'float',
        'total_cost_brl' => 'float',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(McpCcMessage::class, 'session_id');
    }

    public function scopeDoUser($q, int $userId)
    {
        return $q->where('user_id', $userId);
    }

    public function scopeAcessivelPara($q, ?\App\User $user)
    {
        if ($user === null) return $q->whereRaw('1=0');

        // copiloto.cc.read.all → vê de todo time
        if (method_exists($user, 'can') && $user->can('copiloto.cc.read.all')) {
            return $q;
        }
        // copiloto.cc.read.self → vê só as próprias
        return $q->where('user_id', $user->id);
    }
}
