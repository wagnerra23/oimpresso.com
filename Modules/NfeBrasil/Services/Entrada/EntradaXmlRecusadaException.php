<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Services\Entrada;

use RuntimeException;

/** A nota enviada não pode entrar; a mensagem vai para a tela como está. */
final class EntradaXmlRecusadaException extends RuntimeException {}
