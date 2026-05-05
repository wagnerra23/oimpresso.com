<?php

namespace Modules\Copiloto\Drivers\Channels;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Copiloto\Contracts\ChatChannel;
use Modules\Copiloto\Support\Channels\IncomingMessage;
use Modules\Copiloto\Support\Channels\OutgoingMessage;

/**
 * EvolutionApiChannel — driver Fase 0 (ADR 0075).
 *
 * Backend: Evolution API self-host CT 100 (Docker), Baileys-based.
 * Custo: R$0 + chip pré-pago R$15.
 * Risco: ban (issue EvolutionAPI/evolution-api#2298 ativo em 2026).
 *
 * STUB de scaffold — preenchimento dos endpoints depende de:
 *  - container Evolution API rodando no CT 100 (tarefa fora-código Wagner)
 *  - chip pré-pago dedicado ativo (tarefa fora-código Wagner)
 *  - package Composer escolhido (samuelterra22/laravel-evolution-client OU happones/laravel-evolution-client) — avaliar DX antes de `composer require`
 *
 * Quando o package Composer entrar, este driver vai virar wrapper fino sobre
 * a SDK escolhida — preserva a interface ChatChannel pra agent core não notar.
 */
class EvolutionApiChannel implements ChatChannel
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly string $instance,
        private readonly string $webhookSecret,
        private readonly int $timeoutSeconds = 10,
    ) {
    }

    public function name(): string
    {
        return 'evolution';
    }

    public function send(OutgoingMessage $message): void
    {
        // TODO(US-COPI-089): mapear pra endpoint /message/sendText/{instance}
        // Evolution API v2: POST {base}/message/sendText/{instance}
        // body: { "number": "+5511...", "text": "...", "delay": 0 }
        // header: apikey: {apiKey}
        $response = Http::withHeaders(['apikey' => $this->apiKey])
            ->timeout($this->timeoutSeconds)
            ->post("{$this->baseUrl}/message/sendText/{$this->instance}", [
                'number' => $message->wireId,
                'text'   => $message->text,
            ]);

        if (! $response->successful()) {
            Log::warning('[copiloto.channel.evolution] send failed', [
                'business_id' => $message->businessId,
                'status'      => $response->status(),
                'body'        => $response->body(),
            ]);
            throw new \RuntimeException("Evolution send falhou: HTTP {$response->status()}");
        }
    }

    public function parseWebhook(array $payload): ?IncomingMessage
    {
        // Evolution API v2 webhook payload (event=messages.upsert):
        // {
        //   "event": "messages.upsert",
        //   "instance": "...",
        //   "data": {
        //     "key": { "remoteJid": "+5511...@s.whatsapp.net", "id": "MSG_ID" },
        //     "message": { "conversation": "texto" },
        //     "messageTimestamp": 1234567890,
        //     "pushName": "..."
        //   }
        // }
        if (($payload['event'] ?? null) !== 'messages.upsert') {
            return null;
        }

        $data = $payload['data'] ?? [];
        $remoteJid = $data['key']['remoteJid'] ?? null;
        if (! $remoteJid || str_ends_with($remoteJid, '@g.us')) {
            // ignorar grupo por ora (escopo Fase 0 = 1:1)
            return null;
        }

        $text = $data['message']['conversation']
            ?? $data['message']['extendedTextMessage']['text']
            ?? null;
        if (! $text) {
            // mídia / áudio / location — TODO(fase 2)
            return null;
        }

        $wireId = '+' . preg_replace('/\D/', '', explode('@', $remoteJid)[0]);
        $sentAt = isset($data['messageTimestamp'])
            ? (new \DateTimeImmutable())->setTimestamp((int) $data['messageTimestamp'])
            : null;

        return new IncomingMessage(
            channel: $this->name(),
            providerMessageId: $data['key']['id'] ?? '',
            wireId: $wireId,
            text: $text,
            sentAt: $sentAt,
            raw: $payload,
        );
    }

    public function verifySignature(array $headers, string $rawBody): bool
    {
        // Evolution API NÃO assina HMAC por default. Convenção do projeto:
        // exigir secret compartilhado via header customizado.
        $provided = $headers['x-evolution-secret'][0]
            ?? $headers['X-Evolution-Secret'][0]
            ?? null;

        if ($provided === null || $this->webhookSecret === '') {
            return false;
        }

        return hash_equals($this->webhookSecret, $provided);
    }
}
