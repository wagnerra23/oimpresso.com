<?php

namespace Modules\Admin\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware TailscaleOnly — bloqueia requests fora da CIDR Tailscale.
 *
 * ADR 0122 §2 — Admin Center é Tailscale-only. Internet pública zera vetor
 * de ataque externo. CIDR default `100.99.0.0/16` mas configurável via
 * env `ADMIN_TAILSCALE_CIDR` (Agent D 2026-05-10: hardcode é frágil quando
 * Tailscale re-onboard altera CIDR).
 *
 * Aceita lista CSV de CIDRs em `config('admin.tailscale_cidrs')`.
 *
 * Ordem de middleware (importante — Agent D security review):
 *   tailscale-only (zero cost, IP check) ANTES de auth ANTES de is-wagner
 *
 * @see memory/decisions/0122-admin-center-ct100.md
 */
class TailscaleOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        // Bypass dev — Wagner testando localmente em Herd/serve (sem Tailscale).
        // Habilitar via .env: ADMIN_BYPASS_LOCAL=true (default false em prod).
        if (config('admin.bypass_local') && app()->environment('local')) {
            return $next($request);
        }

        // Bypass Tailscale-only restrito (qualquer env, inclusive prod). Defense-in-depth
        // preservado — middleware `is-wagner` continua validando user_id=1. Log WARN
        // pra auditoria. Ver config('admin.bypass_tailscale') + ADR 0122.
        if (config('admin.bypass_tailscale')) {
            \Log::channel('stack')->warning('admin.tailscale_bypass', [
                'ip'    => $request->ip(),
                'route' => $request->path(),
                'env'   => app()->environment(),
            ]);
            return $next($request);
        }

        $allowedCidrs = config('admin.tailscale_cidrs', '100.99.0.0/16');
        $cidrs = is_array($allowedCidrs)
            ? $allowedCidrs
            : array_filter(array_map('trim', explode(',', (string) $allowedCidrs)));

        $ip = $request->ip();

        foreach ($cidrs as $cidr) {
            if ($this->ipInCidr($ip, $cidr)) {
                return $next($request);
            }
        }

        \Log::channel('stack')->warning('admin.tailscale_block', [
            'ip'    => $ip,
            'route' => $request->path(),
            'cidrs' => $cidrs,
        ]);

        abort(403, 'Acesso permitido apenas via Tailscale.');
    }

    /**
     * IPv4 CIDR match. Sem deps externas — IPv6 fica pra Sprint 2 (raro
     * Tailscale IPv6 em laptop Wagner).
     */
    private function ipInCidr(string $ip, string $cidr): bool
    {
        if (! str_contains($cidr, '/')) {
            return $ip === $cidr;
        }
        [$subnet, $bits] = explode('/', $cidr);
        $ipLong     = ip2long($ip);
        $subnetLong = ip2long($subnet);
        if ($ipLong === false || $subnetLong === false) {
            return false;
        }
        $mask = -1 << (32 - (int) $bits);
        return ($ipLong & $mask) === ($subnetLong & $mask);
    }
}
