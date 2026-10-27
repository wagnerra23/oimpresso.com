<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Services\Canais;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Entities\Conversation;
use Modules\Whatsapp\Entities\Message;

/**
 * Resposta do atendente a uma conversa de Instagram Direct ou Messenger, pela
 * Send API da Meta (US-WA-329 fatia 3 · ADR 0135 Fase 1).
 *
 * Chamado pelo `EnvioDeTextoNoCanal` — o mesmo caminho da Caixa, do app das lojas
 * e dos avisos do sistema —, então quem chama já validou permissão, ACL do canal
 * e criou a `Message` em `queued`. Aqui só se fala com a Meta e grava o desfecho.
 *
 * `POST /{versão}/me/messages` com o token da página (`config_json.page_access_token`),
 * `messaging_type=RESPONSE`. O `provider_message_id` gravado leva o prefixo do canal
 * (`RecebimentoNoCanal::idNoBanco`): quando a Meta devolve o eco desta mesma mensagem
 * pelo webhook, ele cai como "repetida" e não duplica na conversa.
 *
 * Atrás de `whatsapp.omnicanal.meta_graph_enabled`.
 */
final class EnvioMetaGraph
{
    /** @return array{ok: bool, motivo: string|null, erro: string|null} */
    public function despachar(Message $message, Conversation $conversation, Channel $channel, ?string $texto): array
    {
        if (! config('whatsapp.omnicanal.meta_graph_enabled', false)) {
            return $this->falha($message, 'tipo_nao_suportado', 'Instagram e Messenger ainda não estão ligados nesta instalação.');
        }

        $token = (string) ($channel->config_json['page_access_token'] ?? '');
        if ($token === '') {
            return $this->falha($message, 'sem_token', 'Canal sem token da página — reconecte o Instagram/Messenger.');
        }

        $url = rtrim((string) config('whatsapp.meta.base_url', 'https://graph.facebook.com'), '/')
            .'/'.config('whatsapp.meta.api_version', 'v21.0').'/me/messages';

        try {
            $resp = Http::withToken($token)->timeout((int) config('whatsapp.meta.request_timeout', 10))
                ->post($url, [
                    'recipient' => ['id' => $conversation->customer_external_id],
                    'messaging_type' => 'RESPONSE',
                    'message' => ['text' => (string) $texto],
                ]);
        } catch (\Throwable $e) {
            return $this->falha($message, 'rede', 'Falha ao falar com a Meta. Veja status da mensagem.', mb_substr($e->getMessage(), 0, 240));
        }

        if (! $resp->successful() || empty($resp->json('message_id'))) {
            Log::warning('[omnicanal.meta_graph.envio] erro', [
                'business_id' => $channel->business_id, 'channel_id' => $channel->id,
                'status' => $resp->status(), 'erro' => mb_substr($resp->body(), 0, 200),
            ]);

            return $this->falha($message, 'daemon', 'A Meta recusou o envio. Veja status da mensagem.', 'Meta '.$resp->status().': '.mb_substr($resp->body(), 0, 200));
        }

        $message->forceFill([
            'status' => 'sent',
            'provider_message_id' => RecebimentoNoCanal::idNoBanco($channel, (string) $resp->json('message_id')),
        ])->save();

        return ['ok' => true, 'motivo' => null, 'erro' => null];
    }

    /** @return array{ok: false, motivo: string, erro: string} */
    private function falha(Message $message, string $motivo, string $erro, ?string $detalhe = null): array
    {
        $message->forceFill(['status' => 'failed', 'failed_reason' => $detalhe ?? $erro])->save();

        return ['ok' => false, 'motivo' => $motivo, 'erro' => $erro];
    }
}
