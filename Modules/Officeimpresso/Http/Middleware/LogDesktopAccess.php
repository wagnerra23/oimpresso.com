<?php

namespace Modules\Officeimpresso\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Officeimpresso\Entities\LicencaLog;

/**
 * Middleware pra gravar chamadas do Delphi em licenca_log com contexto completo.
 *
 * Apply em routes /api/officeimpresso/* via auth:api group.
 *
 * SEGURANCA: toda logica de log em try/catch. Se DB down, log write
 * falha, schema dessincronizado — request do Delphi continua processando.
 */
class LogDesktopAccess
{
    public function handle(Request $request, Closure $next)
    {
        $start = microtime(true);
        $response = $next($request);
        $durationMs = (int) round((microtime(true) - $start) * 1000);

        try {
            $user = $request->user();
            $token = $user?->token();

            LicencaLog::create([
                'event'       => 'api_call',
                'user_id'     => $user?->id,
                'business_id' => $user?->business_id,
                'client_id'   => $token?->client_id ? (string) $token->client_id : null,
                'token_hint'  => $token?->id ? substr($token->id, 0, 8) . '…' . substr($token->id, -4) : null,
                'ip'          => $request->ip(),
                'user_agent'  => Str::limit($request->userAgent() ?? '', 500, ''),
                'endpoint'    => Str::limit($request->path(), 255, ''),
                'http_method' => $request->method(),
                'http_status' => $response->getStatusCode(),
                'duration_ms' => $durationMs,
                'source'      => 'api_middleware',
                'created_at'  => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('LogDesktopAccess middleware falhou: ' . $e->getMessage());
        }

        return $response;
    }
}
