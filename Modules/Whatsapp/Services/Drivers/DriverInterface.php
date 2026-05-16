<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Services\Drivers;

use Modules\Whatsapp\Entities\WhatsappBusinessConfig;
use Modules\Whatsapp\Entities\WhatsappBusinessPhone;

/**
 * Contrato comum dos drivers Whatsapp.
 *
 * Observabilidade D9.a (ADR 0155): cada driver concreto envolve send/ping
 * em `OtelHelper::span(` ou `OtelHelper::spanBiz(` — Tracer multi-tenant
 * resolve `business_id` da sessão automaticamente.
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
 * Multi-números (ADR 0117 — US-WA-040): todos os métodos aceitam
 * `WhatsappBusinessConfig` (legacy 1:1 business→config) ou
 * `WhatsappBusinessPhone` (1:N business→números) via union type.
 * Drivers acessam apenas campos comuns aos 2 models (driver, fallback,
 * meta_*, zapi_*, baileys_*, business_id), então operação é transparente
 * durante a fase de coexistência (PR 2 → PR 5).
 *
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-002, US-WA-040
 * @see memory/decisions/0117-multiplos-numeros-whatsapp-por-business.md
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
        WhatsappBusinessConfig|WhatsappBusinessPhone $config,
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
        WhatsappBusinessConfig|WhatsappBusinessPhone $config,
        string $to,
        string $body,
    ): WhatsappSendResult;

    /**
     * Envia mídia (imagem, PDF, áudio).
     */
    public function sendMedia(
        WhatsappBusinessConfig|WhatsappBusinessPhone $config,
        string $to,
        string $mediaUrl,
        string $type,
        ?string $caption = null,
    ): WhatsappSendResult;

    /**
     * Consulta status de uma mensagem já enviada.
     */
    public function fetchMessageStatus(
        WhatsappBusinessConfig|WhatsappBusinessPhone $config,
        string $providerMessageId,
    ): MessageStatus;

    /**
     * Health check do driver — usado pelo WhatsappDriverHealthCheckJob (6h em 6h).
     */
    public function ping(WhatsappBusinessConfig|WhatsappBusinessPhone $config): DriverHealthStatus;

    /**
     * Envia mensagem INTERATIVA (US-WA-045/046).
     *
     * Tipos suportados (variam por driver — driver não-suportado lança
     * `DriverDoesNotSupport`):
     *  - `buttons` (até 3 reply buttons) — Meta Cloud, Baileys 6.7+, Z-API
     *  - `list` (sections + items, 10 max) — Meta Cloud, Baileys 6.7+, Z-API
     *  - `cta_url` (botão link único) — Meta Cloud apenas
     *
     * Estrutura `$interactive`:
     *   ['type' => 'buttons', 'buttons' => [['id' => 'sim', 'label' => 'Sim'], ...]]
     *   ['type' => 'list', 'button_label' => 'Escolha', 'sections' => [
     *     ['title' => 'Tamanhos', 'items' => [['id' => 'p', 'title' => 'P', 'description' => null], ...]]
     *   ]]
     *   ['type' => 'cta_url', 'button_label' => 'Pagar', 'url' => 'https://...']
     *
     * @param  array<string, mixed>  $interactive  Payload tipado discriminated union pelo `type`
     *
     * @throws DriverDoesNotSupport quando driver não suporta o tipo solicitado
     */
    public function sendInteractive(
        WhatsappBusinessConfig|WhatsappBusinessPhone $config,
        string $to,
        string $body,
        array $interactive,
    ): WhatsappSendResult;
}
