<?php

declare(strict_types=1);

namespace Modules\OficinaAuto\Exceptions;

/**
 * A assinatura do cliente na entrega, uma vez colhida, é prova da entrega: não se troca nem se
 * apaga (UC-OSH-23). Lançada pelo serviço (`AssinaturaEntrega::registrar`) e pela entidade
 * (`OsAssinatura`, eventos updating/deleting).
 */
class AssinaturaImutavelException extends \DomainException
{
}
