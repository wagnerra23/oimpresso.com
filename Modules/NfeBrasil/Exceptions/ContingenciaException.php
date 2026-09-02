<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Exceptions;

use RuntimeException;

/**
 * Falhas de domínio da contingência fiscal (US-NFE-006 / ADR TECH-0002).
 *
 * Construtores nomeados em vez de `new` solto: a mensagem é o que o operador do balcão
 * vai ler quando a SEFAZ cair e ele tentar ligar a contingência — merece ser escrita
 * uma vez, no domínio, e não improvisada em cada controller.
 */
class ContingenciaException extends RuntimeException
{
    public static function motivoObrigatorio(): self
    {
        return new self(
            'Informe o motivo da contingência. A justificativa é exigência de auditoria fiscal: '
            . 'a nota sai marcada como emitida em contingência e o fisco pergunta por quê.'
        );
    }

    public static function semConfigFiscal(int $businessId): self
    {
        return new self(
            "Business {$businessId} não tem configuração fiscal (nfe_business_configs). "
            . 'Configure a tributação antes de ativar contingência.'
        );
    }
}
