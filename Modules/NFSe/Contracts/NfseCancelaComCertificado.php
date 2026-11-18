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
 *
 * Desde 2026-11-18 o cancelamento é o evento e101101 assinado, que identifica a nota pela
 * CHAVE DE ACESSO (50 dígitos, `nfse_emissoes.provider_protocolo`) e o autor pelo CNPJ do
 * prestador. Antes o primeiro argumento era o número da nota e não havia CNPJ.
 */
interface NfseCancelaComCertificado
{
    public function cancelarNoAmbiente(
        string $chaveAcesso,
        string $motivo,
        string $ambiente,
        string $certPfxBase64,
        string $certSenha,
        string $cnpjAutor,
    ): bool;
}
