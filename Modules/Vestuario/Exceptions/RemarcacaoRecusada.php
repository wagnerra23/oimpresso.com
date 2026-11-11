<?php

declare(strict_types=1);

namespace Modules\Vestuario\Exceptions;

use RuntimeException;

/**
 * Recusa de negócio da remarcação: a mensagem foi escrita para quem usa a tela.
 *
 * Só esta exceção tem a mensagem mostrada. `QueryException` também é `RuntimeException` e carrega
 * host, banco e SQL com valores, por isso o controller não pode capturar a classe-mãe.
 */
class RemarcacaoRecusada extends RuntimeException
{
}
