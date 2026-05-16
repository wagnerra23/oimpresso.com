<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Services\Centrifugo;

use App\Util\OtelHelper;
use Illuminate\Support\Facades\Http;

/**
 * CentrifugoPublisher — wrapper HTTP minimal pra publicar eventos Centrifugo.
 *
 * Centrifugo roda no CT 100 (ADR 0058 — substituiu Reverb).
 * API HTTP REST: POST {url}/api com header X-API-Key + body
 *   {"method":"publish","params":{"channel":"...","data":{...}}}
 *
 * **Defesa em profundidade:** falha silenciosa em qualquer erro (rede,
 * 5xx, config ausente). Real-time é "eventually consistent" — não pode
 * derrubar a request HTTP do business só porque Centrifugo está down.
 *
 * @see memory/decisions/0058-reverb-substituido-por-centrifugo-frankenphp.md
 * @see https://centrifugal.dev/docs/server/server_api
 */
class CentrifugoPublisher
{
    /**
     * Publica evento em channel.
     *
     * @param  array<string, mixed>  $data  Payload arbitrário (será JSON-encoded)
     * @return bool  true = sucesso (200 OK Centrifugo). false = qualquer falha (silencioso, log warning).
     */
    public function publish(string $channel, array $data): bool
    {
        return OtelHelper::span('whatsapp.centrifugo.publish', [
            'channel' => $channel,
            'payload_keys' => count($data),
        ], function () use ($channel, $data): bool {
            return $this->doPublish($channel, $data);
        });
    }

    private function doPublish(string $channel, array $data): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        $url = (string) config('whatsapp.centrifugo.url');
        $apiKey = (string) config('whatsapp.centrifugo.api_key', '');

        if ($url === '' || $apiKey === '') {
            return false;
        }

        try {
            $response = Http::withHeaders(['X-API-Key' => $apiKey])
                ->timeout((int) config('whatsapp.centrifugo.request_timeout', 5))
                ->acceptJson()
                ->asJson()
                ->post(rtrim($url, '/') . '/api', [
                    'method' => 'publish',
                    'params' => [
                        'channel' => $channel,
                        'data' => $data,
                    ],
                ]);

            if (! $response->successful()) {
                \Log::warning('[whatsapp.centrifugo.publish] HTTP não-2xx', [
                    'channel' => $channel,
                    'status' => $response->status(),
                    'body' => mb_substr($response->body(), 0, 200),
                ]);
                return false;
            }
            return true;
        } catch (\Throwable $e) {
            \Log::warning('[whatsapp.centrifugo.publish] exception', [
                'channel' => $channel,
                'exception' => $e,
            ]);
            return false;
        }
    }

    public function isEnabled(): bool
    {
        return (bool) config('whatsapp.centrifugo.enabled', true);
    }
}
