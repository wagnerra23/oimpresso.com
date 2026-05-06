<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Exceptions;

use RuntimeException;

/**
 * Levantada pelo MotorTributarioService quando o produto não tem NCM
 * cadastrado e nenhum override por produto está vinculado.
 *
 * UPos não exige NCM no cadastro de produto; produto sem NCM não pode
 * passar pelo cascade níveis 2-4. ADR ARQ-0006.
 */
class NcmObrigatorioException extends RuntimeException {}
