<?php

namespace Modules\PontoWr2\Services;

use RuntimeException;

/**
 * Sinaliza marcação AFD com PIS não cadastrado como Colaborador no business.
 * O parser AFD agrega essas ocorrências em contador separado (não enche a
 * amostra de erros) para facilitar o diagnóstico "faltam N PIS cadastrados".
 */
class PisNaoCadastradoException extends RuntimeException
{
    /** @var string */
    protected $pis;

    public function __construct($pis, $message = null)
    {
        $this->pis = $pis;
        parent::__construct($message ?: "PIS {$pis} não cadastrado como Colaborador.");
    }

    public function getPis()
    {
        return $this->pis;
    }
}
