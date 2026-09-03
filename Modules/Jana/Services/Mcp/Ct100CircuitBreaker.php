<?php

declare(strict_types=1);

namespace Modules\Jana\Services\Mcp;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Circuit breaker do TRANSPORTE até o CT 100, usado pelo `mcp:sync-memory`.
 *
 * Incidente medido (Hostinger, 2026-09-02): CT 100 fora desde ~27-28/08 e o cron
 * de 5min gravou `failed with exit code [1]` ~145x/dia em laravel.log (08-28: 144
 * · 08-29: 148 · 08-31: 146 · 09-01: 147). Falha de REDE virou 288 ERRORs/dia —
 * volume que treina o operador a ignorar o canal onde o ERROR real aparece.
 *
 * A LINHA QUE ESTE ARQUIVO TRAÇA:
 *   - NÃO RESPONDEU (refused/timeout/DNS) = transporte. Nada a consertar deste
 *     lado: UM warning por janela de backoff e exit 0.
 *   - RESPONDEU MAL (5xx, 4xx, deadlock, dado inconsistente) = erro real, com
 *     alguém de pé discordando de nós: segue exit 1.
 *
 * Classificação por TIPO de exceção, nunca por texto da mensagem (`proibicoes.md`
 * §5 tem cinco lápides medidas de guard sintático que reprovava o legítimo).
 * FAIL-SAFE: tipo não reconhecido devolve false e o comando sai 1 como antes — o
 * breaker só cala o alarme com prova do tipo, nunca por engano.
 *
 * @see Modules\Jana\Console\Commands\McpSyncMemoryCommand (consumidor)
 * @see memory/requisitos/Infra/RUNBOOK-acesso-ct100.md (CT 100 sumiu? cabo de rede é a 1a hipótese)
 */
class Ct100CircuitBreaker
{
    /** Instante da PRIMEIRA observação de queda desta série (ISO-8601). */
    public const CACHE_DOWN_SINCE = 'jana:ct100:down_since';

    /** Instante do último WARNING — é o que faz o backoff. */
    public const CACHE_WARNED_AT = 'jana:ct100:warned_at';

    /** Silêncio entre WARNINGs. 30min = ~6 runs do cron por linha de log. */
    public const BACKOFF_SECONDS = 1800;

    /** Sobrevida do "fora desde" (7d) — o jana:health-check lê daqui. */
    public const DOWN_SINCE_TTL = 604800;

    /** Timeout do probe. Curto de propósito: é liveness, não trabalho. */
    public const PROBE_TIMEOUT = 3;

    /**
     * "Não cheguei no destino". `instanceof` com string NÃO dispara autoload —
     * classe ausente do vendor apenas não casa, então o namespace legado do
     * Meilisearch (<= 0.24, com S maiúsculo) fica listado sem custo.
     */
    private const TRANSPORT_EXCEPTIONS = [
        \Illuminate\Http\Client\ConnectionException::class,
        \GuzzleHttp\Exception\ConnectException::class,
        'Meilisearch\Exceptions\CommunicationException',
        'MeiliSearch\Exceptions\CommunicationException',
    ];

    /**
     * Anda a cadeia `getPrevious()` porque Scout/Laravel embrulham a exceção do
     * cliente HTTP. Teto de 10 saltos: cadeia cíclica é rara, loop infinito em
     * cron não vale o risco.
     */
    public static function isTransportFailure(?\Throwable $e): bool
    {
        for ($cur = $e, $hops = 0; $cur !== null && $hops < 10; $cur = $cur->getPrevious(), $hops++) {
            foreach (self::TRANSPORT_EXCEPTIONS as $fqcn) {
                if ($cur instanceof $fqcn) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Pergunta ao destino REAL do sync se ele está de pé — o Meilisearch do
     * Scout, não o `mcp.oimpresso.com`: o comando lê `memory/` do filesystem e
     * escreve em `mcp_memory_documents`; o único salto de rede é o observer do
     * Scout (`McpMemoryDocument` usa `Searchable`), que reindexa/reembeda no
     * CT 100 — ver `memory/requisitos/Jana/SPEC.md:430`. Sondar o MCP server
     * aqui mediria a propriedade errada.
     *
     * VEREDITO: respondeu = alcançável, mesmo respondendo mal.
     *
     * @return array{applicable: bool, reachable: bool, target: ?string, detail: string}
     */
    public static function probe(): array
    {
        $naoSeAplica = ['applicable' => false, 'reachable' => true, 'target' => null, 'detail' => ''];

        if ((string) config('scout.driver') !== 'meilisearch') {
            return $naoSeAplica;
        }

        $host = rtrim((string) config('scout.meilisearch.host', ''), '/');
        if ($host === '') {
            return $naoSeAplica;
        }

        try {
            $response = Http::timeout(self::PROBE_TIMEOUT)->get($host.'/health');

            return [
                'applicable' => true,
                'reachable' => true, // 4xx/5xx = de pé e doente: deixa falhar de verdade.
                'target' => $host,
                'detail' => $response->successful() ? '' : "HTTP {$response->status()}",
            ];
        } catch (\Throwable $e) {
            if (! self::isTransportFailure($e)) {
                // Erro nosso (config, TLS, URL malformada) — não é "CT 100 fora".
                return ['applicable' => true, 'reachable' => true, 'target' => $host, 'detail' => 'probe inconclusivo'];
            }

            return ['applicable' => true, 'reachable' => false, 'target' => $host, 'detail' => mb_substr($e->getMessage(), 0, 120)];
        }
    }

    /** Marca queda e devolve o início da série (o primeiro a ver, não o último). */
    public static function recordDown(): ?Carbon
    {
        $since = Cache::get(self::CACHE_DOWN_SINCE);
        if (! is_string($since) || $since === '') {
            $since = now()->toIso8601String();
            Cache::put(self::CACHE_DOWN_SINCE, $since, self::DOWN_SINCE_TTL);
        }

        return self::parseSince($since);
    }

    /** Sync voltou a fechar — encerra a série. */
    public static function recordUp(): void
    {
        Cache::forget(self::CACHE_DOWN_SINCE);
        Cache::forget(self::CACHE_WARNED_AT);
    }

    public static function downSince(): ?Carbon
    {
        return self::parseSince(Cache::get(self::CACHE_DOWN_SINCE));
    }

    /**
     * True no máximo 1x a cada BACKOFF_SECONDS.
     *
     * ⚠️ Depende de cache COMPARTILHADO entre processos. No Hostinger é `file`
     * (default do config/cache.php) e funciona. Sob `array` (per-processo) cada
     * run é um universo e isto devolve sempre true — o warning volta a sair a
     * cada 5min. Degrada pro ruído antigo em WARN, nunca em ERROR: o exit 0 não
     * passa por aqui.
     */
    public static function shouldWarn(): bool
    {
        if (Cache::get(self::CACHE_WARNED_AT) !== null) {
            return false;
        }

        Cache::put(self::CACHE_WARNED_AT, now()->toIso8601String(), self::BACKOFF_SECONDS);

        return true;
    }

    /**
     * Perna "meilisearch/sync" pro consolidador do `jana:health-check`.
     *
     * NÃO sonda: devolve o que o cron de 5min JÁ observou. O health-check roda
     * 1x/dia; o sync roda 288x/dia e é quem atravessa o caminho de fato — sondar
     * aqui de novo seria um segundo medidor do mesmo fato.
     *
     * @return array{service: string, reachable: ?bool, since: ?string}
     */
    public static function healthLeg(): array
    {
        $since = self::downSince();

        return [
            'service' => 'meilisearch/sync',
            'reachable' => $since === null ? null : false, // null = nunca observado.
            'since' => $since?->toIso8601String(),
        ];
    }

    private static function parseSince(mixed $raw): ?Carbon
    {
        if (! is_string($raw) || $raw === '') {
            return null;
        }

        try {
            return Carbon::parse($raw);
        } catch (\Throwable) {
            return null;
        }
    }
}
