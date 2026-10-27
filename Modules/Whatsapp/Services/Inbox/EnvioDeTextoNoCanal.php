<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Services\Inbox;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Entities\Conversation;
use Modules\Whatsapp\Entities\Message;

/**
 * Despacho de texto outbound ao daemon do canal (Baileys ou Whatsmeow).
 *
 * Extraído de `InboxController::send` (web) sem mudar o comportamento, para o app das lojas
 * (`/api/app/atendimento/*`, PR 18 do playbook propostas-app) enviar pelo MESMO caminho em vez
 * de reimplementar a chamada ao daemon. Quem chama já validou permissão, ACL do canal e
 * consentimento, já criou a `Message` outbound em `queued` e já atualizou a conversa: aqui só
 * se fala com o daemon e se grava o desfecho (status, provider_message_id, failed_reason).
 *
 * Os textos de `erro` são os mesmos que a web já mostrava (`back()->withErrors(['send' => …])`).
 *
 * Multi-tenant (ADR 0093): não consulta nada por business — recebe as entidades já carregadas
 * e escopadas pelo chamador.
 */
final class EnvioDeTextoNoCanal
{
    /**
     * @return array{ok: bool, motivo: string|null, erro: string|null}
     *   motivo ∈ tipo_nao_suportado | sem_token | daemon | rede (null quando ok)
     */
    public function despachar(Message $message, Conversation $conversation, Channel $channel, ?string $texto): array
    {
        // US-WA-329 — Instagram Direct e Messenger respondem pela Send API da Meta, no mesmo caminho.
        if ($channel->type === Channel::TYPE_INSTAGRAM || $channel->type === Channel::TYPE_MESSENGER) {
            return app(\Modules\Whatsapp\Services\Canais\EnvioMetaGraph::class)
                ->despachar($message, $conversation, $channel, $texto);
        }
        if ($channel->type === Channel::TYPE_EMAIL_IMAP) {
            return app(\Modules\Whatsapp\Services\Canais\EnvioEmail::class)->despachar($message, $conversation, $channel, $texto);
        }
        if ($channel->type !== Channel::TYPE_WHATSAPP_BAILEYS
            && $channel->type !== Channel::TYPE_WHATSAPP_WHATSMEOW) {
            $message->forceFill([
                'status' => 'failed',
                'failed_reason' => "Envio só implementado pra Baileys/Whatsmeow nesta fase. Tipo atual: {$channel->type}",
            ])->save();

            return $this->falha('tipo_nao_suportado', 'Envio só disponível pra canais Baileys/Whatsmeow nesta fase.');
        }

        // Resolve daemon URL/auth + endpoint + payload por tipo:
        //   - Baileys (legacy):  POST /instances/{ch-uuid}/text     Authorization Bearer {API_KEY}
        //                        body {to, text} → resp {message_id, status}
        //   - Whatsmeow (ADR 0204): POST /chat/send/text             Header Token {channel.config_json.whatsmeow_user_token}
        //                        body {Phone, Body, Id} → resp {Id, Details: Sent}
        //
        // Incident 2026-05-28: outbound whatsmeow rejeitado por hardcode pra Baileys.
        // UI mostrava verde (msg persistia status=queued→failed) mas msg NUNCA saía
        // pro WhatsApp real. Padrão portado: aceita ambos drivers + dispatch por type.
        $toPhone = preg_replace('/^\+/', '', $conversation->customer_external_id);
        $isWhatsmeow = $channel->type === Channel::TYPE_WHATSAPP_WHATSMEOW;

        if ($isWhatsmeow) {
            $daemonUrl = rtrim((string) config('whatsapp.whatsmeow.daemon_url'), '/');
            $userToken = $channel->config_json['whatsmeow_user_token'] ?? null;
            if (! $userToken) {
                $message->forceFill([
                    'status' => 'failed',
                    'failed_reason' => 'whatsmeow_user_token ausente em channel.config_json — canal precisa ser reconectado (scan QR).',
                ])->save();

                return $this->falha('sem_token', 'Canal whatsmeow não tem token — reconecte via QR.');
            }
            $endpoint = '/chat/send/text';
            $authHeaders = ['Token' => $userToken, 'Content-Type' => 'application/json'];
            $payload = [
                'Phone' => $toPhone,
                'Body' => $texto,
                'Id' => strtoupper(str_replace('-', '', (string) Str::uuid())),
            ];
        } else {
            $daemonUrl = rtrim((string) config('whatsapp.baileys.daemon_url'), '/');
            $apiKey = (string) config('whatsapp.baileys.api_key');
            $instanceId = 'ch-' . str_replace('-', '', $channel->channel_uuid);
            $endpoint = "/instances/{$instanceId}/text";
            $authHeaders = ['Authorization' => "Bearer {$apiKey}", 'Content-Type' => 'application/json'];
            $payload = ['to' => $toPhone, 'text' => $texto];
        }

        try {
            $response = Http::withHeaders($authHeaders)
                ->withoutVerifying() // FIXME(US-WA-058): cert LE pendente CT 100
                ->timeout(15)
                ->post("{$daemonUrl}{$endpoint}", $payload);

            if (! $response->successful()) {
                $message->forceFill([
                    'status' => 'failed',
                    'failed_reason' => 'Daemon ' . $response->status() . ': ' . mb_substr($response->body(), 0, 200),
                ])->save();
                Log::warning('[atendimento.inbox.send] daemon error', [
                    'channel_id' => $channel->id,
                    'channel_type' => $channel->type,
                    'endpoint' => $endpoint,
                    'status' => $response->status(),
                    'body_sample' => mb_substr($response->body(), 0, 200),
                ]);

                return $this->falha('daemon', 'Falha ao enviar via daemon. Veja status da mensagem.');
            }

            $respJson = $response->json();
            // Normaliza resp por tipo:
            //   Baileys: {message_id, status}
            //   Whatsmeow WuzAPI: {Details: 'Sent', Id: 'WAMID...'} ou nested {Data: {Id: ...}}
            $providerMsgId = $isWhatsmeow
                ? ($respJson['Id'] ?? $respJson['Data']['Id'] ?? null)
                : ($respJson['message_id'] ?? null);
            $newStatus = $isWhatsmeow
                ? (($respJson['Details'] ?? '') === 'Sent' ? 'sent' : ($respJson['Details'] ?? 'sent'))
                : ($respJson['status'] ?? 'sent');

            $message->forceFill([
                'status' => $newStatus,
                'provider_message_id' => $providerMsgId,
            ])->save();

            Log::info('[atendimento.inbox.send] daemon ok', [
                'channel_id' => $channel->id,
                'channel_type' => $channel->type,
                'message_id' => $message->id,
                'provider_message_id' => $providerMsgId,
                'endpoint' => $endpoint,
            ]);

            return ['ok' => true, 'motivo' => null, 'erro' => null];
        } catch (\Throwable $e) {
            $message->forceFill([
                'status' => 'failed',
                'failed_reason' => mb_substr($e->getMessage(), 0, 240),
            ])->save();
            Log::error('[atendimento.inbox.send] exception', [
                'channel_id' => $channel->id,
                'channel_type' => $channel->type,
                'exception' => $e->getMessage(),
            ]);

            return $this->falha('rede', 'Erro de rede com daemon: ' . $e->getMessage());
        }
    }

    /** @return array{ok: false, motivo: string, erro: string} */
    private function falha(string $motivo, string $erro): array
    {
        return ['ok' => false, 'motivo' => $motivo, 'erro' => $erro];
    }
}
