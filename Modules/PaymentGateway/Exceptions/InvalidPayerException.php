<?php

namespace Modules\PaymentGateway\Exceptions;

/**
 * Dados do pagador insuficientes ou inválidos: CPF/CNPJ malformado,
 * endereço ausente quando obrigatório pelo driver, e-mail inválido.
 * NÃO é retry-safe — humano corrige cadastro.
 */
class InvalidPayerException extends PaymentGatewayException
{
}
