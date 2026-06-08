<?php

namespace Modules\PaymentGateway\Exceptions;

/**
 * Driver atual não suporta a operação pedida (ex: PIX Automático em Asaas,
 * cartão em Inter sandbox). NÃO é retry — chame Service::supports() antes.
 */
class DriverNotSupportedException extends PaymentGatewayException
{
}
