<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * US-WA-084 — Backpressure protetiva no webhook receiver Baileys.
 *
 * Conta jobs pendentes na fila `whatsapp-history` (queue:database) e, se
 * estourou `whatsapp.backpressure.queue_max_depth`, retorna 429 com
 * Retry-After header. Daemon Node (`WebhookDispatcher.ts`) já tem 429 em
 * RETRYABLE_STATUS, então faz exponential backoff + jitter sem perder a
 * mensagem (SQLite local preserva).
 *
 * Drop policy:
 *  - 429 > 500 > timeout: daemon retenta, mensagem fica viva
 *  - Sem queue → não bloqueia (fail-open p/ não derrubar quem não usa fila)
 *  - Resultado é cacheado 10s p/ não martelar `SELECT COUNT(*) FROM jobs`
 *    em burst de webhooks (PHP-FPM proteção compounding).
 *
 * @see Modules/Whatsapp/Config/config.php § backpressure
 * @see Modules/Whatsapp/daemon-node/src/webhook/WebhookDispatcher.ts § RETRYABLE_STATUS
 */
class EnforceWebhookBackpressure
{
    private const CACHE_TTL_SECONDS = 10;
    private const CACHE_KEY = 'whatsapp:backpressure:queue_depth';

    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) config('whatsapp.backpressure.enabled', true)) {
            return $next($request);
        }

        $maxDepth = (int) config('whatsapp.backpressure.queue_max_depth', 2000);
        $queueName = (string) config('whatsapp.backpressure.queue_name', 'whatsapp-history');
        $retryAfter = (int) config('whatsapp.backpressure.retry_after_seconds', 30);

        if ($maxDepth <= 0) {
            return $next($request);
        }

        $depth = $this->currentDepth($queueName);

        if ($depth >= $maxDepth) {
            Log::warning('[whatsapp.backpressure] webhook rejeitado por queue depth', [
                'queue' => $queueName,
                'depth' => $depth,
                'max' => $maxDepth,
            ]);

            return response()
                ->json([
                    'ok' => false,
                    'error' => 'queue_backpressure',
                    'queue_depth' => $depth,
                    'retry_after_seconds' => $retryAfter,
                ], 429)
                ->header('Retry-After', (string) $retryAfter);
        }

        return $next($request);
    }

    private function currentDepth(string $queueName): int
    {
        return (int) Cache::remember(self::CACHE_KEY . ':' . $queueName, self::CACHE_TTL_SECONDS, function () use ($queueName) {
            try {
                return DB::table('jobs')->where('queue', $queueName)->count();
            } catch (\Throwable $e) {
                Log::warning('[whatsapp.backpressure] SELECT COUNT jobs falhou — fail-open', [
                    'queue' => $queueName,
                    'error' => $e->getMessage(),
                ]);

                return 0; // fail-open
            }
        });
    }
}
