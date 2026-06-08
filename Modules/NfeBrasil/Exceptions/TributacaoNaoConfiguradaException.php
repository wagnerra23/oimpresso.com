<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Exceptions;

use RuntimeException;

/**
 * Levantada pelo MotorTributarioService quando o cascade chega no Nível 4
 * (defaults business) mas o business não tem `nfe_business_configs` ou
 * tem `tributacao_default` vazio.
 *
 * UI: bloquear emissão e direcionar tenant pra wizard de configuração
 * inicial. ADR ARQ-0006.
 */
class TributacaoNaoConfiguradaException extends RuntimeException {}
