<?php

declare(strict_types=1);

namespace Modules\TeamMcp\Services;

use App\Util\OtelHelper;
use Illuminate\Http\Request;
use Modules\TeamMcp\Entities\McpActor;

/**
 * ADR 0081 — Identity Mesh canonical resolver.
 *
 * Centraliza lookup de actor a partir de:
 * - Request (lê mcp_token attribute populado pelo McpAuthMiddleware)
 * - actor_id explícito
 * - slug
 *
 * Usado por tools MCP (MyWorkTool, MyInboxTool) e ActionGate middleware
 * (Fase 5) pra enforcement.
 */
class ActorResolver
{
    /**
     * Resolve o actor da request atual via mcp_token attribute.
     * Retorna null se sem token, sem actor_id, ou actor revogado.
     */
    public function fromRequest(?Request $request = null): ?McpActor
    {
        // Wave 11 D9.a — OTel span pra resolução actor a partir de token request.
        // Chamado em toda tool MCP via middleware (auth + identity mesh). Span
        // detecta latência de lookup + revoked check (caso DB lento).
        return OtelHelper::spanBiz('teammcp.actor.resolve_from_request', function () use ($request) {
            $request = $request ?? request();
            $token = $request->attributes->get('mcp_token');
            if (!$token || empty($token->actor_id)) {
                return null;
            }

            return $this->byId((int) $token->actor_id);
        }, ['module' => 'TeamMcp']);
    }

    public function byId(int $id): ?McpActor
    {
        // Wave 11 D9.a — OTel span pra lookup direto por id. Atributos não logam
        // dados sensíveis (apenas actor_id resolvido + revoked flag).
        return OtelHelper::spanBiz('teammcp.actor.by_id', function () use ($id) {
            $actor = McpActor::find($id);
            if (!$actor || $actor->isRevoked()) {
                return null;
            }
            return $actor;
        }, ['module' => 'TeamMcp', 'actor_id' => $id]);
    }

    public function bySlug(string $slug): ?McpActor
    {
        $actor = McpActor::where('slug', $slug)->first();
        if (!$actor || $actor->isRevoked()) {
            return null;
        }
        return $actor;
    }

    /**
     * Pra tools que filtram por owner (my-work): retorna o slug humano
     * efetivo (resolve IA → parent_actor humano).
     *
     * Fallback pra Auth::user() se não houver token (pré-Identity Mesh).
     */
    public function effectiveOwnerSlug(?Request $request = null): string
    {
        $actor = $this->fromRequest($request);
        if ($actor) {
            return $actor->effectiveHumanSlug();
        }

        // Fallback legacy
        $user = \Illuminate\Support\Facades\Auth::user();
        if (!$user) return '';

        $u = strtolower($user->username ?? $user->first_name ?? '');
        if ($u !== '') return $u;
        if (!empty($user->email)) {
            return strtolower(explode('@', $user->email)[0]);
        }
        return '';
    }

    /**
     * Pra tools que filtram por user_id (my-inbox): retorna user_id humano
     * efetivo. Resolve IA → parent_actor.user_id.
     */
    public function effectiveUserId(?Request $request = null): ?int
    {
        $actor = $this->fromRequest($request);
        if ($actor) {
            $userId = $actor->effectiveHumanUserId();
            if ($userId) return $userId;
        }

        // Fallback legacy
        $user = \Illuminate\Support\Facades\Auth::user();
        return $user?->id ? (int) $user->id : null;
    }

    /**
     * Pra logging/display: retorna nome curto efetivo (parent humano se IA).
     */
    public function effectiveDisplayName(?Request $request = null): string
    {
        $actor = $this->fromRequest($request);
        if ($actor) {
            $effective = $actor->isAi() && $actor->parent
                ? $actor->parent
                : $actor;
            $first = explode(' ', $effective->display_name ?? '')[0] ?: $effective->slug;
            return $first;
        }

        $user = \Illuminate\Support\Facades\Auth::user();
        return $user?->first_name ?? 'você';
    }
}
