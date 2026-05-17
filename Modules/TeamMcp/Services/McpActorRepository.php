<?php

declare(strict_types=1);

namespace Modules\TeamMcp\Services;

use App\Util\OtelHelper;
use Illuminate\Support\Collection;
use Modules\TeamMcp\Entities\McpActor;

/**
 * McpActorRepository — Wave 18 D4 SATURATION (2026-05-16).
 *
 * Repository pattern thin sobre `McpActor` (Identity Mesh). Centraliza
 * queries reutilizadas em Controllers, Tools MCP, Middleware ActionGate
 * (Fase 5 planejada — ADR 0086).
 *
 * Cobre 4 lookups canon:
 *   - `findActiveBySlug(slug)` — resolução humano ou IA não-revogada
 *   - `listHumansByTrust(trustLevel)` — Wagner L0, Felipe/Maira L2, etc.
 *   - `listAiChildren(parentActorId)` — IAs pareadas a humano
 *   - `revokedSince(Carbon)` — audit/governança
 *
 * **ADR 0081 Tier 0 cross-tenant:** `mcp_actors` NÃO tem `business_id` —
 * Identity Mesh transcende tenants by design. NÃO aplicar global scope —
 * documentado em proibições.
 *
 * @see Modules\TeamMcp\Entities\McpActor
 * @see Modules\TeamMcp\Services\ActorResolver (caller principal)
 */
class McpActorRepository
{
    /**
     * Busca actor ativo (não revogado) por slug.
     *
     * @return McpActor|null  null se não existe OU revogado
     */
    public function findActiveBySlug(string $slug): ?McpActor
    {
        return OtelHelper::spanBiz('teammcp.actor.find_active_by_slug', function () use ($slug) {
            $actor = McpActor::where('slug', $slug)->first();
            if ($actor === null || $actor->isRevoked()) {
                return null;
            }
            return $actor;
        }, ['module' => 'TeamMcp', 'slug' => $slug]);
    }

    /**
     * Lista actors humanos ativos por trust_level.
     *
     * @return Collection<int, McpActor>
     */
    public function listHumansByTrust(int $trustLevel): Collection
    {
        return McpActor::query()
            ->where('type', 'human')
            ->where('trust_level', $trustLevel)
            ->whereNull('revoked_at')
            ->orderBy('slug')
            ->get();
    }

    /**
     * Lista IAs pareadas a um humano (parent_actor_id).
     *
     * @return Collection<int, McpActor>
     */
    public function listAiChildren(int $parentActorId): Collection
    {
        return McpActor::query()
            ->where('type', 'ai')
            ->where('parent_actor_id', $parentActorId)
            ->whereNull('revoked_at')
            ->orderBy('slug')
            ->get();
    }

    /**
     * Lista actors revogados a partir de uma data — audit/governança.
     *
     * @return Collection<int, McpActor>
     */
    public function revokedSince(\DateTimeInterface $since): Collection
    {
        return McpActor::query()
            ->whereNotNull('revoked_at')
            ->where('revoked_at', '>=', $since)
            ->orderByDesc('revoked_at')
            ->get();
    }
}
