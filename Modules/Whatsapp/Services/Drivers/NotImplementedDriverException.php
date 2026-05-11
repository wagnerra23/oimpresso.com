<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Services\Drivers;

/**
 * Driver pra um Channel::type ainda não implementado nesta fase (ADR 0135).
 *
 * Fase 1 (Insta/Messenger) — gate cliente sinalizar
 * Fase 2 (Email) — gate volume justificar
 * Fase 3 (ML) — gate cliente pagante pedindo
 */
class NotImplementedDriverException extends \RuntimeException
{
}
