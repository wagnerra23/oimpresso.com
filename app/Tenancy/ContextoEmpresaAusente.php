<?php

declare(strict_types=1);

namespace App\Tenancy;

use RuntimeException;

/** Consulta a model de empresa sem empresa no contexto, com o modo `fechado` ligado (ADR 0453). */
final class ContextoEmpresaAusente extends RuntimeException
{
}
