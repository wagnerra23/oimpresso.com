<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Contracts;

use Modules\NfeBrasil\Models\NfseEmissao;
use Modules\NfeBrasil\Models\NfseEventoCancelamento;

/**
 * US-NFSE-CANCEL-001 — Contrato de driver de cancelamento NFSe per-município.
 *
 * NFSe é fragmentado por padrão municipal (cada prefeitura escolhe entre ABRASF
 * v1.0, ABRASF v2.04, GINFES, IPM, Tiplan, padrão nacional `nfse.gov.br/sefin`
 * etc). NÃO existe protocolo único — cada driver implementa um padrão e
 * declara quais municípios cobre via `supportedMunicipios()`.
 *
 * O resolver (`NfseCancelService`) escolhe o driver baseado em
 * `NfseEmissao.municipio_codigo_ibge` cruzando com a lista declarada por
 * cada driver registrado no container.
 *
 * Multi-tenant Tier 0 (ADR 0093): drivers DEVEM respeitar o `business_id` da
 * emissão recebida (sem acesso a session()). Service resolver faz o
 * cross-tenant guard antes de delegar.
 *
 * @see NfseCancelService
 * @see memory/requisitos/NfeBrasil/SPEC-NFSE-CANCEL.md
 */
interface NfseCancelDriverInterface
{
    /**
     * Cancela uma NFSe via API do município.
     *
     * @param  NfseEmissao  $nfse  Emissão a cancelar (status=authorized esperado).
     * @param  string  $motivo  Motivo legível (15-255 chars — validado pelo service antes).
     * @return NfseEventoCancelamento  Evento persistido (status=autorizado ou rejeitado).
     *
     * @throws \RuntimeException Se SEFAZ municipal rejeitar, infra falhar, ou
     *                           padrão não estiver implementado ainda (stub).
     */
    public function cancelar(NfseEmissao $nfse, string $motivo): NfseEventoCancelamento;

    /**
     * Identificador único do driver (ex: 'ABRASF_V1', 'ABRASF_V2.04', 'GINFES',
     * 'IPM', 'TIPLAN', 'NFSE_GOV_BR'). Usado em logs, eventos e SPEC.
     */
    public function getDriverKey(): string;

    /**
     * Lista de códigos IBGE dos municípios suportados por este driver.
     *
     * Vazio = driver "fallback" ou ainda não populado (stub). Service não vai
     * resolver pra um município que ninguém declara.
     *
     * @return array<int, string>  Códigos IBGE 7 dígitos (ex: '3550308' = São Paulo/SP).
     */
    public function supportedMunicipios(): array;
}
