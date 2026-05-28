<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Injeta contexto canônico em todos os `Log::*()` da request inteira via
 * `Log::withContext([...])` (Laravel 12 docs canon).
 *
 * **Camada 1 da defensive logging** ([ADR 0212](../../../memory/decisions/0212-defensive-logging-fallback-paths.md)).
 *
 * Por que existir:
 *
 * Antes deste middleware, logs de fallback silencioso (ex: `Carbon::now()`
 * default em `SellPosController:435` — bug R9 Larissa 2026-05-28) saíam sem
 * `business_id` nem `user_id` nem rastreabilidade. Bug invisível: 2h47 entre
 * Larissa abrir Sells/Create e submeter, DB gravou transaction_date errado,
 * nenhum log apontou origem.
 *
 * Agora: TODO `Log::warning(...)` subsequente na request tem automaticamente:
 *   - `business_id` (de `session('user.business_id')` — UPOS canon)
 *   - `user_id` (de `session('user.id')`)
 *   - `request_id` (UUID v4 gerado por request)
 *   - `route_name` (request route name OR url)
 *
 * Não-objetivos:
 *
 * - NÃO injeta PII (email, telefone, cpf) — só identificadores.
 * - NÃO substitui `Log::error` em exceptions reais — escopo é WARNING level
 *   pra fallback paths + INFO/DEBUG developer-visible.
 * - NÃO depende de Auth::user() (UPOS canon é `session()` direto pra evitar
 *   redundância de query).
 *
 * Posição no pipeline:
 *
 * Registrado em `App\Http\Kernel::$middlewareGroups['web']` logo APÓS
 * `StartSession` (precisa session) e ANTES de qualquer outro middleware UPOS
 * (precisa estar ativo nos demais). Ver Kernel:36-50.
 *
 * Performance: `Log::withContext` é hashmap merge — microsegundos.
 */
class LogContextMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $context = [
            'request_id' => (string) Str::uuid(),
        ];

        // business_id + user_id da session UPOS canon (pode ser null em
        // requests não-autenticados como /login, webhook receivers, etc).
        $session = $request->session();
        if ($session->has('user.business_id')) {
            $context['business_id'] = (int) $session->get('user.business_id');
        }
        if ($session->has('user.id')) {
            $context['user_id'] = (int) $session->get('user.id');
        }

        // route_name: prefere nome da rota (canon Laravel); fallback URL path
        // se rota não-nomeada (legacy UPOS tem várias).
        $route = $request->route();
        if ($route !== null) {
            $context['route_name'] = $route->getName() ?? $request->path();
        } else {
            $context['route_name'] = $request->path();
        }

        Log::withContext($context);

        return $next($request);
    }
}
