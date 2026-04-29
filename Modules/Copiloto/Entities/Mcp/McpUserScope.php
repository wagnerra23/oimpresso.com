<?php

namespace Modules\Copiloto\Entities\Mcp;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * MEM-MCP-1.a (ADR 0053) — Mapping user → scope (com escopo opcional por business).
 */
class McpUserScope extends Model
{
    protected $table = 'mcp_user_scopes';

    protected $fillable = [
        'user_id', 'scope_id', 'business_id',
        'granted_by', 'granted_at', 'revoked_at', 'revoked_by',
    ];

    protected $casts = [
        'granted_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function scope(): BelongsTo
    {
        return $this->belongsTo(McpScope::class, 'scope_id');
    }

    public function scopeAtivos($query)
    {
        return $query->whereNull('revoked_at');
    }

    public function scopeDoUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeDoBusinessOuTodos($query, ?int $businessId)
    {
        return $query->where(function ($q) use ($businessId) {
            $q->whereNull('business_id'); // todos os businesses
            if ($businessId !== null) {
                $q->orWhere('business_id', $businessId);
            }
        });
    }

    public function isAtivo(): bool
    {
        return $this->revoked_at === null;
    }
}
