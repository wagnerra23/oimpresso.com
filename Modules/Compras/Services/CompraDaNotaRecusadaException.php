<?php

declare(strict_types=1);

namespace Modules\Compras\Services;

use RuntimeException;

/** A nota não pode virar compra agora; a mensagem vai para a tela de conferência como está. */
final class CompraDaNotaRecusadaException extends RuntimeException {}
