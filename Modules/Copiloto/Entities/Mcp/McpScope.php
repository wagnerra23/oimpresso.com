<?php

namespace Modules\Copiloto\Entities\Mcp;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * MEM-MCP-1.a (ADR 0053) — Catálogo de scopes do MCP server.
 * Mapeamento 1-pra-1 com Spatie permissions `copiloto.mcp.*`.
 */
class McpScope extends Model
{
    protected $table = 'mcp_scopes';

    protected $fillable = [
        'slug', 'nome', 'descricao',
        'resources_pattern', 'tools_pattern',
        'is_destructive', 'business_required', 'admin_only',
    ];

    protected $casts = [
        'is_destructive'    => 'boolean',
        'business_required' => 'boolean',
        'admin_only'        => 'boolean',
    ];

    public function userScopes(): HasMany
    {
        return $this->hasMany(McpUserScope::class, 'scope_id');
    }

    public function matchesTool(string $toolName): bool
    {
        if ($this->tools_pattern === null) {
            return false;
        }
        return $this->matchesPattern($this->tools_pattern, $toolName);
    }

    public function matchesResource(string $uri): bool
    {
        if ($this->resources_pattern === null) {
            return false;
        }
        return $this->matchesPattern($this->resources_pattern, $uri);
    }

    /**
     * Suporta glob (`tools.*`, `resources/decisions/*`) ou regex se prefixado `~`.
     */
    protected function matchesPattern(string $pattern, string $value): bool
    {
        if (str_starts_with($pattern, '~')) {
            return (bool) preg_match(substr($pattern, 1), $value);
        }
        // Glob simples: escape regex chars com preg_quote, depois reverte \* → .*
        $regex = '#^' . str_replace('\*', '.*', preg_quote($pattern, '#')) . '$#';
        return (bool) preg_match($regex, $value);
    }
}
