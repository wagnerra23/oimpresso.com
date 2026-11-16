<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Exceptions;

/**
 * A natureza de operação escolhida no lançamento não serve para esta emissão (onda 5 das naturezas ·
 * RUNBOOK-tributacao §Naturezas). O controller devolve a mensagem como 422 — é escolha do usuário, não
 * falha do servidor.
 */
final class NaturezaDoLancamentoInvalidaException extends \InvalidArgumentException
{
}
