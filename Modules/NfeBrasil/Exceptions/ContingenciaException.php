<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Exceptions;

use Modules\NfeBrasil\Services\ModoContingencia;
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

    public static function modoInvalido(string $modo): self
    {
        return new self("Modo de contingência desconhecido: {$modo}. Use EPEC, SVC-AN ou SVC-RS.");
    }

    /** US-NFE-071 — cada UF tem uma SEFAZ Virtual; a outra recusa a nota. */
    public static function svcForaDaUf(string $escolhido, ?string $uf, ?string $daUf): self
    {
        if ($daUf === null) {
            return new self(
                'Não foi possível saber a UF do emitente (endereço do primeiro local da empresa), '
                . 'então não dá para escolher a SEFAZ Virtual. Use EPEC ou corrija a UF do local.'
            );
        }

        return new self(sprintf(
            'A UF %s é atendida pela %s, não pela %s — a SEFAZ Virtual errada recusaria as notas.',
            strtoupper((string) $uf),
            ModoContingencia::rotulo($daUf),
            ModoContingencia::rotulo($escolhido),
        ));
    }
}
