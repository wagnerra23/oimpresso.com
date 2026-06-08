<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Redirect 301 de /public/X → /X — fallback PHP-side do .htaccess raiz.
 *
 * Hostinger Cloud Startup tem DocumentRoot apontando pra raiz do repo
 * (~/domains/oimpresso.com/public_html/), não pra public_html/public/. Por isso
 * /public/X funciona acessado direto. O .htaccess raiz tenta interceptar via
 * RewriteCond %{THE_REQUEST} ^[A-Z]{3,9}\s/public/ mas LiteSpeed Hostinger NÃO
 * dispara a regra em alguns paths (descoberto 17/mai 2026 — request com auth
 * passava direto pelo Laravel, address bar ficava /public/admin).
 *
 * Este middleware garante o redirect independente do servidor web. Roda como
 * primeiro middleware global, ANTES de TrustProxies, CORS, sessão e auth.
 *
 * @see memory/reference/hostinger.md
 */
class RedirectPublicPath
{
    public function handle(Request $request, Closure $next): Response
    {
        // ATENÇÃO: usar getRequestUri() em vez de path(). Em prod o Symfony Request
        // detecta SCRIPT_NAME=/public/index.php e strip "/public" automaticamente
        // do path() — então em HTTP real /public/admin → path()=admin (sem prefix).
        // getRequestUri() preserva o path bruto do cliente, incluindo "/public/".
        // Descoberto via probe inline 17/mai 2026.
        $uri = $request->getRequestUri();
        $pathOnly = strtok($uri, '?');

        if ($pathOnly !== '/public' && ! str_starts_with((string) $pathOnly, '/public/')) {
            return $next($request);
        }

        $newPath = $pathOnly === '/public' ? '/' : substr($pathOnly, strlen('/public'));
        $query = $request->getQueryString();

        return redirect()->to($newPath . ($query !== null && $query !== '' ? '?' . $query : ''), 301);
    }
}
