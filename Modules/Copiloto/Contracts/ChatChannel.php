<?php

namespace Modules\Copiloto\Contracts;

use Modules\Copiloto\Support\Channels\IncomingMessage;
use Modules\Copiloto\Support\Channels\OutgoingMessage;

/**
 * ChatChannel — interface canônica do channel adapter (ADRs 0074 + 0075).
 *
 * Drivers planejados:
 *  - EvolutionApiChannel (FASE 0 — dogfooding self-host CT 100, R$0)
 *  - ZApiChannel         (FASE 1 — beta clientes pagantes, R$55-99/mês fixo)
 *  - WhatsAppCloudChannel (FASE 2 — Meta oficial, R$/conversa)
 *  - WebChannel           (canal web atual, atrás da mesma interface)
 *
 * Multi-tenant scope obrigatório: `business_id` resolvido via
 * `ChannelIdentityResolver::resolve()` antes de qualquer downstream.
 *
 * Ver:
 *  - memory/decisions/0074-whatsapp-channel-adapter-copiloto.md (pattern)
 *  - memory/decisions/0075-whatsapp-provider-3-fases-evolution-zapi-cloud.md (provider strategy)
 */
interface ChatChannel
{
    /**
     * Nome do canal (ex.: "evolution", "zapi", "meta", "web"). Usado como
     * label de métricas OTel (`gen_ai.channel`) e em `copiloto_channel_identity.channel`.
     */
    public function name(): string;

    /**
     * Envia mensagem outbound pelo canal.
     *
     * Implementações DEVEM respeitar opt-in (`channel_identity.opted_in_at`)
     * — se não houver opt-in, abrir com mensagem de consentimento em vez de
     * conteúdo livre.
     */
    public function send(OutgoingMessage $message): void;

    /**
     * Normaliza payload bruto do webhook do provider em IncomingMessage.
     * Retorna `null` se for evento ignorado (status, presença, etc.).
     */
    public function parseWebhook(array $payload): ?IncomingMessage;

    /**
     * Valida assinatura do webhook (cada provider tem o seu formato).
     * Retorna `false` → controller deve responder 401.
     */
    public function verifySignature(array $headers, string $rawBody): bool;
}
