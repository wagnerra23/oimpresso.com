<?php

namespace Modules\Whatsapp\Services\Drivers;

/**
 * Contrato comum dos drivers Whatsapp.
 *
 * Implementações Sprint 1:
 * - ZapiDriver (default, Z-API SaaS BR via Whatsapp Web/Baileys)
 * - MetaCloudDriver (fallback obrigatório, oficial Meta)
 * - NullDriver (dev/CI Pest)
 *
 * EvolutionDriver é PROIBIDO Tier 0 (ADR 0096 emenda 3).
 *
 * Lote 2a: só interface + NullDriver implementado.
 * Lote 2b: ZapiDriver + MetaCloudDriver com Http::fake() Pest.
 *
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-002
 * @see memory/requisitos/Whatsapp/ARCHITECTURE.md §3 Fluxos críticos
 */
interface DriverInterface
{
    /**
     * Envia mensagem template.
     *
     * Para Meta Cloud: usa HSM aprovado.
     * Para Z-API: expande placeholders e envia como freeform (sem HSM).
     *
     * @param  array<string, mixed>  $config  Config do business (whatsapp_business_configs)
     * @param  array<string, string>  $params  Variáveis do template
     */
    public function sendTemplate(array $config, string $to, string $templateName, array $params, string $locale = 'pt_BR'): WhatsappSendResult;

    /**
     * Envia mensagem freeform (texto direto).
     *
     * Para Meta Cloud: só funciona dentro da janela 24h após cliente enviar.
     * Para Z-API: sempre funciona (sem janela 24h).
     *
     * @param  array<string, mixed>  $config
     */
    public function sendFreeform(array $config, string $to, string $body): WhatsappSendResult;

    /**
     * Envia mídia (imagem, PDF, áudio).
     *
     * @param  array<string, mixed>  $config
     */
    public function sendMedia(array $config, string $to, string $mediaUrl, string $type, ?string $caption = null): WhatsappSendResult;

    /**
     * Consulta status de uma mensagem já enviada.
     *
     * @param  array<string, mixed>  $config
     */
    public function fetchMessageStatus(array $config, string $providerMessageId): MessageStatus;

    /**
     * Health check do driver — usado pelo WhatsappDriverHealthCheckJob (6h em 6h).
     *
     * @param  array<string, mixed>  $config
     */
    public function ping(array $config): DriverHealthStatus;
}
