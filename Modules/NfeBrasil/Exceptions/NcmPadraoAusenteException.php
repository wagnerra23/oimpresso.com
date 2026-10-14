<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Exceptions;

use Illuminate\Support\Facades\Route;
use RuntimeException;

/**
 * Emissão recusada porque a empresa não tem NCM padrão válido (playbook Fiscal thread 16 · R-NFE-022).
 *
 * A nota NÃO sai: NF-e exige NCM, e "emitir sem NCM" não é opção (D-SUPORTE). O que esta exceção
 * acrescenta ao `RuntimeException` que existia é a SAÍDA: quem a recebe sabe qual tela resolve, e o
 * campo, em vez de só um texto.
 *
 * A rota é a mesma que a Saúde fiscal usa para o item `ncm_padrao_invalido`
 * (`SaudeFiscalService::ncmPadrao`), para o card e o erro mandarem ao mesmo lugar.
 *
 * Continua sendo `RuntimeException`: quem já capturava (jobs, listener, retransmitir) segue igual.
 */
final class NcmPadraoAusenteException extends RuntimeException
{
    public const ERRO = 'ncm_padrao_ausente';

    public const ROTA = 'nfe-brasil.tributacao.config.show';

    /** `id` do campo na tela `NfeBrasil/Tributacao/ConfigDefault`. */
    public const CAMPO = 'ncm_default';

    public function __construct(public readonly int $businessId)
    {
        parent::__construct("Business {$businessId} sem NCM padrão configurado. Escolha o NCM padrão da empresa para emitir.");
    }

    /**
     * A ação que resolve o bloqueio.
     *
     * @return array{label: string, url: ?string, campo: string}
     */
    public function acao(): array
    {
        return [
            'label' => 'Escolher NCM padrão',
            'url' => Route::has(self::ROTA) ? route(self::ROTA) . '#' . self::CAMPO : null,
            'campo' => self::CAMPO,
        ];
    }
}
