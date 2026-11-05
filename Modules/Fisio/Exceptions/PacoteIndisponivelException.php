<?php

declare(strict_types=1);

namespace Modules\Fisio\Exceptions;

use DomainException;

/**
 * Débito recusado no pacote de sessões (US-FISIO-020): sem saldo, vencido ou encerrado.
 * A mensagem vai para a tela; não carrega dado clínico.
 */
final class PacoteIndisponivelException extends DomainException
{
    public static function semSaldo(): self
    {
        return new self('O pacote não tem sessão disponível.');
    }

    public static function vencido(string $validade): self
    {
        return new self("O pacote venceu em {$validade}. Estenda a validade para usar as sessões.");
    }

    public static function encerrado(): self
    {
        return new self('O pacote está encerrado.');
    }

    public static function vendaJaLigada(): self
    {
        return new self('Este pacote já tem venda, ou a venda já paga outro pacote.');
    }

    public static function estornoInvalido(): self
    {
        return new self('Só um débito pode ser estornado, e uma vez.');
    }
}
