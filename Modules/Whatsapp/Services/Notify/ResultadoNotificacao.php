<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Services\Notify;

/**
 * Desfecho de `NotificadorClienteWhatsapp::notificar()` (US-WA-319).
 *
 * Só `Enviado` enfileirou mensagem. Os demais dizem por que não saiu, para quem chamou decidir o
 * caminho alternativo (e-mail, link manual) sem precisar repetir as checagens.
 */
enum ResultadoNotificacao: string
{
    case Enviado = 'enviado';
    case SemNumeroConfigurado = 'sem_numero_configurado';
    case ContatoNaoEncontrado = 'contato_nao_encontrado';
    case RecusouWhatsapp = 'recusou_whatsapp';
    case SemCelular = 'sem_celular';

    public function enviado(): bool
    {
        return $this === self::Enviado;
    }
}
