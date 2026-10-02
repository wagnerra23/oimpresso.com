<?php

declare(strict_types=1);

namespace App\Contracts\Enderecos\Nulo;

use App\Contracts\Enderecos\BuscaCep;

/** Padrão quando o módulo Crm não está carregado: nenhum CEP é encontrado. */
final class SemBuscaCep implements BuscaCep
{
    public function buscar(string $cep): ?array
    {
        return null;
    }
}
