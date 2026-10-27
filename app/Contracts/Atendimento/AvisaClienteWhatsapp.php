<?php

declare(strict_types=1);

namespace App\Contracts\Atendimento;

/**
 * Aviso automático ao cliente por WhatsApp (texto), visto de fora do Modules/Whatsapp.
 *
 * Primeiro consumidor: o lembrete de revisão da oficina (US-AUTO-014, decisão [W] 2026-10-26). O
 * envio é da frente CRM/WhatsApp (`NotificadorClienteWhatsapp`, US-WA-319): quem implementa este
 * contrato é o Modules/Whatsapp, ligado pelo WhatsappServiceProvider. Sem a ligação, quem consome
 * confere `app()->bound(AvisaClienteWhatsapp::class)` e não manda nada.
 *
 * Multi-tenant Tier 0 (ADR 0093): `business_id` é argumento explícito; o contato tem de ser da
 * mesma empresa (quem implementa confere).
 */
interface AvisaClienteWhatsapp
{
    /**
     * Enfileira o aviso ao contato. Não manda na hora: `enviado` = aceito para envio.
     *
     * @return array{enviado: bool, motivo: ?string}  `motivo` quando não saiu (ex.: `sem_canal`,
     *   `contato_de_outra_empresa`, `sem_consentimento`, `sem_celular`).
     */
    public function avisar(int $businessId, int $contactId, string $texto): array;
}
