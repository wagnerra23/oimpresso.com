<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Exceptions;

/**
 * R-NFE-021 · a alíquota interna da UF de destino não foi cadastrada pelo contador.
 *
 * Existe para que ninguém use 0 nem um palpite no lugar dela (lei 4 do módulo): quem precisa da
 * interna (ST, DIFAL) recebe este erro, com a UF no texto, e a tela pode mandar o usuário cadastrar.
 * Estende a exceção de tributação não configurada — quem já a trata, trata esta.
 */
class AliquotaInternaNaoCadastradaException extends TributacaoNaoConfiguradaException
{
    public static function para(string $uf): self
    {
        return new self("Alíquota interna de {$uf} não cadastrada. Peça ao contador para preencher em Tributação.");
    }
}
