<?php

declare(strict_types=1);

namespace App\Contracts\Cobranca;

/**
 * Falha de cobrança com código estável, sem depender das exceções do módulo de gateway.
 * Códigos: sem_configuracao · provedor_indisponivel · pagador_invalido · ja_existe ·
 * nao_encontrado · nao_cancelavel.
 */
final class FalhaCobranca extends \RuntimeException
{
    public function __construct(public readonly string $codigo, string $mensagem = '', ?\Throwable $anterior = null)
    {
        parent::__construct($mensagem !== '' ? $mensagem : $codigo, 0, $anterior);
    }
}
