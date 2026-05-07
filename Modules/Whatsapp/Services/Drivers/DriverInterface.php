<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Services\Drivers;

use Modules\Whatsapp\Entities\WhatsappBusinessConfig;

/**
 * Contrato comum dos drivers Whatsapp.
 *
 * Implementações Sprint 1:
 * - ZapiDriver (default, Z-API SaaS BR via Whatsapp Web/Baileys)
 * - MetaCloudDriver (fallback obrigatório, oficial Meta)
 * - NullDriver (dev/CI Pest)
 *
 * Sprint 3 (autorizado emenda 4 ADR 0096):
 * - BaileysDriver (custom oimpresso, daemon Node CT 100 próprio)
 *
 * EvolutionDriver é PROIBIDO permanente (ADR 0096 emenda 4):
 * bans em produção Wagner + schema não atende + falta observabilidade.
 *
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-002
 * @see memory/requisitos/Whatsapp/ARCHITECTURE.md §3 Fluxos críticos
 */
interface DriverInterface
{
    /**
     * Envia mensagem template.
     *
     * Para Meta Cloud: usa HSM aprovado (status=APPROVED).
     * Para Z-API/Baileys: expande placeholders e envia como freeform (sem HSM).
     *
     * @param  array<string, string>  $params  Variáveis do template (chaves nominais ou numéricas)
     */
    public function sendTemplate(
        WhatsappBusinessConfig $config,
        string $to,
        string $templateName,
        array $params,
        string $locale = 'pt_BR',
    ): WhatsappSendResult;

    /**
     * Envia mensagem freeform (texto direto).
     *
     * Para Meta Cloud: só funciona dentro da janela 24h após cliente enviar.
     * Para Z-API/Baileys: sempre funciona (sem janela 24h).
     */
    public function sendFreeform(
        WhatsappBusinessConfig $config,
        string $to,
        string $body,
    ): WhatsappSendResult;

    /**
     * Envia mídia (imagem, PDF, áudio).
     */
    public function sendMedia(
        WhatsappBusinessConfig $config,
        string $to,
        string $mediaUrl,
        string $type,
        ?string $caption = null,
    ): WhatsappSendResult;

    /**
     * Consulta status de uma mensagem já enviada.
     */
    public function fetchMessageStatus(
        WhatsappBusinessConfig $config,
        string $providerMessageId,
    ): MessageStatus;

    /**
     * Health check do driver — usado pelo WhatsappDriverHealthCheckJob (6h em 6h).
     */
    public function ping(WhatsappBusinessConfig $config): DriverHealthStatus;
}
