<?php

declare(strict_types=1);

namespace App\Support\Errors;

use Throwable;

/**
 * CrossTenantViolation — marcador Tier 0 IRREVOGÁVEL (ADR 0093).
 *
 * Qualquer exceção que sinalize acesso/escrita a dado de `business_id` alheio
 * implementa esta interface. O {@see ErrorClassifier} classifica TODA
 * CrossTenantViolation como **S0** — é o "S0 silencioso" do Mapa: o operador
 * só vê uma negação genérica, mas o construtor é alertado na hora.
 *
 * @see prototipo-ui/handoffs/erros-fase1-classificacao.md
 * @see ADR 0093 (Multi-tenant Tier 0)
 */
interface CrossTenantViolation extends Throwable
{
}
