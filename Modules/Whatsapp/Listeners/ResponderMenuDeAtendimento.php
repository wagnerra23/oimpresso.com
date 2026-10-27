<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Listeners;

use Modules\Whatsapp\Events\OmnichannelMessageReceived;
use Modules\Whatsapp\Services\BotMenu\MenuDeAtendimento;

/**
 * Mensagem recebida → bot de menu (US-WA-324). A decisão de responder ou não é toda do
 * `MenuDeAtendimento` (flag, menu ativo no canal, conversa sem atendente, mensagem nova).
 */
class ResponderMenuDeAtendimento
{
    public function handle(OmnichannelMessageReceived $event): void
    {
        app(MenuDeAtendimento::class)->processar($event->message);
    }
}
