<?php

namespace Modules\Ponto\Services;

use RuntimeException;

/**
 * Sinaliza marcação AFD com PIS não cadastrado como Colaborador no business.
 * O parser AFD agrega essas ocorrências em contador separado (não enche a
 * amostra de erros) para facilitar o diagnóstico "faltam N PIS cadastrados".
 *
 * O PIS só sai daqui MASCARADO (`getPisMascarado`): o agregado vai para o log da
 * importação, que a UI admin exibe (LGPD — minimização; mesmo tratamento do CPF).
 */
class PisNaoCadastradoException extends RuntimeException
{
    /** @var string */
    protected $pis;

    public function __construct($pis, $message = null)
    {
        $this->pis = $pis;
        parent::__construct($message ?: 'PIS ' . $this->getPisMascarado() . ' não cadastrado como Colaborador.');
    }

    public function getPis()
    {
        return $this->pis;
    }

    /** Últimos 3 dígitos visíveis, sobre os 11 do PIS (o AFD grava 12, com zero à esquerda). */
    public function getPisMascarado(): string
    {
        $digitos = substr(str_pad(preg_replace('/\D/', '', (string) $this->pis), 11, '0', STR_PAD_LEFT), -11);

        return '***.*****.' . substr($digitos, 8, 2) . '-' . substr($digitos, 10, 1);
    }
}
