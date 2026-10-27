<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Services\Canais;

use App\Scopes\ScopeByBusiness;
use Illuminate\Support\Facades\Log;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Entities\Conversation;
use Modules\Whatsapp\Entities\Message;
use Modules\Whatsapp\Services\Centrifugo\CentrifugoPublisher;

/**
 * Grava a mensagem de Instagram Direct, Messenger ou e-mail na MESMA conversa,
 * fila e atendente que o WhatsApp usa (US-WA-329 · ADR 0135).
 *
 * Não existe caixa paralela: a conversa é uma linha de `conversations` com o
 * `channel_id` do canal, e a Caixa Unificada já lista, filtra por fila e
 * atribui atendente a partir dessa tabela. O que este serviço garante:
 *
 *  - a chave da conversa é (business_id, channel_id, remetente no canal) —
 *    o 2º recado do mesmo cliente cai na mesma conversa, e a atribuição que o
 *    atendente já fez permanece;
 *  - conversa bloqueada descarta o recado do cliente (mesma regra do WhatsApp);
 *  - reentrega do provedor não duplica: `provider_message_id` é único no banco
 *    INTEIRO, então leva o canal no prefixo — o mesmo e-mail entregue em duas
 *    empresas não colide, e uma empresa nunca "acha" a mensagem da outra;
 *  - o aviso em tempo real sai no mesmo canal Centrifugo que a tela escuta.
 *
 * Multi-tenant Tier 0 (ADR 0093): o `business_id` vem SEMPRE do Channel já
 * resolvido pelo chamador — nunca do payload do provedor.
 */
class RecebimentoNoCanal
{
    public const TIPOS_SUPORTADOS = [
        Channel::TYPE_INSTAGRAM,
        Channel::TYPE_MESSENGER,
        Channel::TYPE_EMAIL_IMAP,
    ];

    public function __construct(private readonly CentrifugoPublisher $centrifugo)
    {
    }

    /** @return 'gravada'|'repetida'|'bloqueada'|'vazia'|'canal_nao_suportado' */
    public function receber(Channel $canal, MensagemRecebida $m): string
    {
        if (! in_array($canal->type, self::TIPOS_SUPORTADOS, true)) {
            return 'canal_nao_suportado';
        }
        if (($m->texto === null || trim($m->texto) === '') && $m->urlDaMidia === null) {
            return 'vazia';
        }

        $bizId = (int) $canal->business_id;
        $idProvedor = self::idNoBanco($canal, $m->idNoProvedor);

        // SUPERADMIN: webhook/poller sem usuário logado — o tenant vem do Channel resolvido, nunca do payload.
        $conversa = Conversation::query()->withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $bizId)
            ->where('channel_id', $canal->id)
            ->where('customer_external_id', $m->remetenteExterno)
            ->first();

        if ($conversa !== null && $conversa->is_blocked && ! $m->enviadaPorNos) {
            return 'bloqueada';
        }

        // SUPERADMIN: idem — idempotência por (business_id, provider_message_id).
        $jaExiste = Message::query()->withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $bizId)
            ->where('provider_message_id', $idProvedor)
            ->exists();
        if ($jaExiste) {
            return 'repetida';
        }

        $agora = $m->enviadaEm !== null ? \Illuminate\Support\Carbon::createFromTimestamp($m->enviadaEm) : now();
        $direcao = $m->enviadaPorNos ? 'outbound' : 'inbound';
        $previa = mb_substr((string) ($m->assunto ?? $m->texto ?? ''), 0, 200);

        if ($conversa === null) {
            $conversa = new Conversation();
            $conversa->forceFill([
                'business_id' => $bizId,
                'channel_id' => $canal->id,
                'customer_external_id' => $m->remetenteExterno,
                'contact_name' => $m->nomeDoContato ?: $m->remetenteExterno,
                'status' => 'open',
                'bot_handling' => false,
                'unread_count' => 0,
            ]);
        } elseif ($m->nomeDoContato && $conversa->contact_name === $m->remetenteExterno) {
            $conversa->contact_name = $m->nomeDoContato;
        }

        // Prévia e direção da última mensagem: o MessageObserver grava ao criar a Message.
        $conversa->forceFill([
            'last_message_at' => $agora,
            'last_inbound_at' => $m->enviadaPorNos ? $conversa->last_inbound_at : $agora,
            'last_outbound_at' => $m->enviadaPorNos ? $agora : $conversa->last_outbound_at,
            'unread_count' => (int) $conversa->unread_count + ($m->enviadaPorNos ? 0 : 1),
        ])->save();

        $mensagem = new Message();
        $mensagem->forceFill([
            'business_id' => $bizId,
            'conversation_id' => $conversa->id,
            'direction' => $direcao,
            'provider' => $canal->type,
            'provider_message_id' => $idProvedor,
            'type' => $m->tipo,
            'subject' => $m->assunto,
            'body' => $m->texto,
            'payload' => $m->payloadBruto,
            'status' => $m->enviadaPorNos ? 'sent' : 'received',
            'sender_kind' => $m->enviadaPorNos ? 'human' : null,
            'media_url' => $m->urlDaMidia,
            'media_mime' => $m->mimeDaMidia,
        ])->save();

        try {
            $this->centrifugo->publish("omnichannel:business:{$bizId}", [
                'type' => $m->enviadaPorNos ? 'message.sent' : 'message.received',
                'channel_id' => $canal->id,
                'conversation_id' => $conversa->id,
                'contact_name' => $conversa->contact_name,
                'body_preview' => $previa,
            ]);
        } catch (\Throwable $e) {
            // Centrifugo é eventual (ADR 0058): a tela também recarrega por polling.
            Log::warning('omnichannel.recebimento.centrifugo_falhou', [
                'business_id' => $bizId, 'conversation_id' => $conversa->id, 'erro' => $e->getMessage(),
            ]);
        }

        return 'gravada';
    }

    /** `provider_message_id` é único no banco inteiro (msgs_provider_msg_uniq), daí o canal no prefixo. */
    public static function idNoBanco(Channel $canal, string $idNoProvedor): string
    {
        $prefixo = "{$canal->type}:{$canal->id}:";
        $id = $prefixo.$idNoProvedor;

        return strlen($id) <= 128 ? $id : $prefixo.sha1($idNoProvedor);
    }
}
