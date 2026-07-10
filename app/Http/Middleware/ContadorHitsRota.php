<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Conta hits por rota — sinal CONTÍNUO de execução real ("servido").
 *
 * Por que existir: a governança spec↔código só tinha eixo ESTÁTICO
 * (anchor-lint dead/zombie/wired = existe no disco + referenciado no roteador)
 * e sinal pontual (charter-live-signal: prod-flags/smoke). Faltava a prova
 * DINÂMICA — "essa rota foi de fato servida nos últimos N dias" (régua
 * Coverband/Wallarm, grade v3 fraqueza "verificação runtime" 5/10).
 *
 * Contratos duros (Tier 0 de produção):
 *   - default OFF (`config('route_hits.enabled')` ← ROUTE_HITS_ENABLED) —
 *     early-return de 1 acesso a array quando desligado;
 *   - contagem em terminate() — roda APÓS a response ser enviada (LiteSpeed
 *     LSAPI/FPM), latência zero pro usuário;
 *   - NUNCA write síncrono em DB por request: Cache::increment (driver file
 *     no Hostinger); `route-hits:flush` move batch → tabela `route_hits`;
 *   - ZERO PII / ZERO tenant: a chave é só data + identidade da rota (nome
 *     canônico OU URI-pattern com placeholders `sells/{id}` — nunca a URL
 *     resolvida, nunca query string, nunca business_id/user_id);
 *   - fail-silent: Throwable engolido — telemetria jamais derruba request.
 *
 * Registrado no fim do grupo `web` (App\Http\Kernel) — chokepoint real: core
 * routes/web.php E módulos nWidart (`Route::middleware(['web', ...])`) passam
 * todos por ele. Verificado 2026-07-09 contra routes/web.php + Modules/*/Routes.
 */
class ContadorHitsRota
{
    public const PREFIXO_CACHE = 'route_hits';

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        try {
            if (! config('route_hits.enabled')) {
                return;
            }

            $rota = self::identidadeRota($request);
            if ($rota === null) {
                return; // sem rota casada (404 etc) — cardinalidade não-limitada, não conta
            }

            $sample = (float) config('route_hits.sample_rate', 1.0);
            if ($sample < 1.0 && mt_rand() / mt_getrandmax() > $sample) {
                return;
            }

            $chave = self::chaveCache($rota, now()->format('Y-m-d'));
            // add + increment (em vez de increment puro): garante TTL na criação
            // cross-driver — increment do FileStore criaria a chave "forever".
            Cache::add($chave, 0, now()->addHours((int) config('route_hits.cache_ttl_horas', 48)));
            Cache::increment($chave);
        } catch (Throwable) {
            // fail-silent por contrato — telemetria nunca quebra a request.
        }
    }

    /**
     * Identidade limitada e sem PII da rota: nome canônico Laravel; fallback
     * URI-pattern (`repair/{id}/edit`) pra legacy UPOS sem nome. NUNCA o path
     * resolvido (conteria IDs) nem query string.
     */
    public static function identidadeRota(Request $request): ?string
    {
        $route = $request->route();
        if ($route === null) {
            return null;
        }

        $nome = $route->getName();

        return ($nome !== null && $nome !== '') ? $nome : $route->uri();
    }

    public static function chaveCache(string $rota, string $data): string
    {
        return self::PREFIXO_CACHE.':'.$data.':'.$rota;
    }
}
