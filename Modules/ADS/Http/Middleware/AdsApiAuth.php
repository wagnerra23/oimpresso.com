<?php

namespace Modules\ADS\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Auth Bearer simples para endpoints internos do ADS.
 * Usado pelo Brain A daemon e por integrações server-to-server.
 *
 * Token vem do env ADS_API_KEY (não exposto para Wagner ou cliente).
 */
class AdsApiAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('ads.api_key');

        if (empty($expected)) {
            return response()->json([
                'error' => 'ads_api_key_not_configured',
                'hint'  => 'Defina ADS_API_KEY no .env',
            ], 500);
        }

        $token = $request->bearerToken();
        if (empty($token) || ! hash_equals($expected, $token)) {
            return response()->json(['error' => 'unauthorized'], 401);
        }

        return $next($request);
    }
}
