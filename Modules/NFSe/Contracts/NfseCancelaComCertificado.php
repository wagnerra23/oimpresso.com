<?php

namespace Modules\NFSe\Contracts;

/**
 * Cancelamento no ambiente da EMPRESA da nota, com o certificado dela (mTLS).
 *
 * Existe à parte da NfseProviderInterface para não mudar a assinatura dela: há dublês
 * em Modules/NfeBrasil/Tests que a implementam (auditoria NFSe 2026-11-05, lote 0).
 * O `cancelar()` antigo usava o ambiente global `config('nfse.ambiente')` e ia sem
 * certificado.
 *
 * Devolve true SÓ quando o provedor confirma o cancelamento. false = não cancelou.
 */
interface NfseCancelaComCertificado
{
    public function cancelarNoAmbiente(
        string $numero,
        string $motivo,
        string $ambiente,
        string $certPfxBase64,
        string $certSenha,
    ): bool;
}
