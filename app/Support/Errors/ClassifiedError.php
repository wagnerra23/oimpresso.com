<?php

declare(strict_types=1);

namespace App\Support\Errors;

use Throwable;

/**
 * ClassifiedError — exceção de domínio que se auto-classifica (Fase 1 · E-1).
 *
 * Caminho confiável: o código que LANÇA conhece a severidade melhor que um
 * classificador genérico. O {@see ErrorClassifier} respeita estes valores —
 * exceto {@see CrossTenantViolation}, que é sempre S0 e tem prioridade.
 *
 * As exceções de domínio do Mapa de Severidade (fiscal, cobrança, OS, ...)
 * graduam pra cá conforme [W] for preenchendo o Mapa.
 *
 * @see prototipo-ui/handoffs/erros-fase1-classificacao.md
 */
interface ClassifiedError extends Throwable
{
    public function severity(): Severity;

    public function audience(): Audience;

    public function owner(): string;

    /** Texto humano de recuperação pro operador. NUNCA o trace. */
    public function operatorMessage(): string;
}
