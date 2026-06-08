<?php

namespace Modules\Admin\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware IsWagner — gate hardcoded pro Centro de Operações.
 *
 * 3 condições AND (defense in depth contra DB corruption — ADR 0122 §1):
 *   1. user_id matches `config('admin.wagner_user_id')` (default 1)
 *   2. business_id matches `config('admin.wagner_business_id')` (default 1)
 *   3. Spatie role `superadmin` (preferencialmente com `#1` business scope)
 *
 * Tentativa não autorizada → 403 + log em mcp_admin_audit_log (action='unauthorized_access').
 *
 * Override emergencial via env `ADMIN_FALLBACK_USERNAME` (Agent D 2026-05-10
 * security review — fragilidade contra DB restore que perde user_id=1).
 *
 * @see memory/decisions/0122-admin-center-ct100.md
 */
class IsWagner
{
    public function handle(Request $request, Closure $next): Response
    {
        // Bypass dev — Wagner testando localmente em Herd/serve.
        // Habilitar via .env: ADMIN_BYPASS_LOCAL=true (default false em prod).
        if (config('admin.bypass_local') && app()->environment('local')) {
            return $next($request);
        }

        $user = Auth::user();

        if (! $user) {
            return $this->forbid($request, 'no_auth');
        }

        $expectedUserId     = (int) config('admin.wagner_user_id', 1);
        $expectedBusinessId = (int) config('admin.wagner_business_id', 1);
        $fallbackUsername   = config('admin.fallback_username'); // env-driven

        $userIdMatch     = (int) $user->id === $expectedUserId;
        $businessIdMatch = (int) ($user->business_id ?? 0) === $expectedBusinessId;
        // UltimatePOS Spatie usa suffix #{biz} (roles.business_id NOT NULL — FK).
        // Aceita 'superadmin' (legacy global) OU 'superadmin#{biz}' (canon multi-tenant).
        $hasRole         = method_exists($user, 'hasRole')
            && ($user->hasRole('superadmin') || $user->hasRole("superadmin#{$expectedBusinessId}"));

        // Caminho normal: 3 condições AND
        if ($userIdMatch && $businessIdMatch && $hasRole) {
            return $next($request);
        }

        // Fallback emergencial (env): username match + role
        if ($fallbackUsername && $user->username === $fallbackUsername && $hasRole) {
            return $next($request);
        }

        return $this->forbid($request, 'gate_check_failed');
    }

    private function forbid(Request $request, string $reason): Response
    {
        // Audit log — registro append-only no mcp_admin_audit_log.
        // (Sprint 1: log via Log::channel('admin-audit') até migration rodar.)
        \Log::channel('stack')->warning('admin.unauthorized', [
            'reason'  => $reason,
            'user_id' => Auth::id(),
            'ip'      => $request->ip(),
            'route'   => $request->path(),
        ]);

        abort(403, 'Admin Center é Wagner-only.');
    }
}
