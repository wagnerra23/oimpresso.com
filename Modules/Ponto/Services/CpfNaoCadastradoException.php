<?php

namespace Modules\Ponto\Services;

use RuntimeException;

/**
 * Marcação AFD no leiaute da Portaria MTP 671/2021 cujo CPF não está cadastrado
 * como Colaborador no business. Irmã da PisNaoCadastradoException (leiaute 1510).
 *
 * O CPF só sai daqui MASCARADO: o valor vai para o log da importação, que a UI
 * admin exibe (LGPD — minimização).
 */
class CpfNaoCadastradoException extends RuntimeException
{
    /** @var string */
    protected $cpfMascarado;

    public function __construct(string $cpf)
    {
        $this->cpfMascarado = '***.***.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
        parent::__construct("CPF {$this->cpfMascarado} não cadastrado como Colaborador.");
    }

    public function getCpfMascarado(): string
    {
        return $this->cpfMascarado;
    }
}
