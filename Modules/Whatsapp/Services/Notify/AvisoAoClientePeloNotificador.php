<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Services\Notify;

use App\Contracts\Atendimento\AvisaClienteWhatsapp;

/**
 * Liga o contrato do núcleo `AvisaClienteWhatsapp` ao `NotificadorClienteWhatsapp` (US-WA-319).
 *
 * Quem consome de fora do módulo (lembrete de revisão da oficina, link da prova da arte) só conhece o
 * contrato; até esta ligação, `app()->bound()` dava falso e nada saía. Usa o canal de saída padrão
 * (`outbound_default`). Canal, cliente da mesma empresa (Tier 0), opt-out LGPD e celular são conferidos
 * pelo notificador; aqui só se traduz o desfecho para os motivos que o contrato documenta.
 */
final class AvisoAoClientePeloNotificador implements AvisaClienteWhatsapp
{
    private const MOTIVO = [
        'sem_numero_configurado' => 'sem_canal',
        'contato_nao_encontrado' => 'contato_de_outra_empresa',
        'recusou_whatsapp' => 'sem_consentimento',
        'sem_celular' => 'sem_celular',
    ];

    public function __construct(private readonly NotificadorClienteWhatsapp $notificador) {}

    public function avisar(int $businessId, int $contactId, string $texto): array
    {
        $r = $this->notificador->notificar($businessId, $contactId, 'outbound_default', $texto);

        return ['enviado' => $r->enviado(), 'motivo' => $r->enviado() ? null : (self::MOTIVO[$r->value] ?? $r->value)];
    }
}
