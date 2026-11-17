<?php

namespace Modules\NFSe\DTO;

use Carbon\Carbon;

final class NfseEmissaoPayload
{
    public function __construct(
        public readonly int     $businessId,
        public readonly string  $rpsNumero,
        public readonly Carbon  $competencia,
        public readonly string  $tomadorNome,
        public readonly ?string $tomadorCnpj,
        public readonly ?string $tomadorCpf,
        public readonly ?string $tomadorEmail,
        public readonly string  $descricao,
        public readonly string  $lc116Codigo,
        public readonly float   $valorServicos,
        public readonly float   $aliquotaIss,
        public readonly bool    $issRetido = false,
        public readonly ?string $certPfxBase64 = null,
        public readonly ?string $certSenha = null,
        public readonly string  $ambiente = 'homologacao',
        public readonly string  $municipioIbge = '4218707',
        public readonly ?string $prestadorCnpj = null,
        public readonly ?string $prestadorIm = null,
        public readonly ?int    $transactionId = null,
        // R-NFSE-003 — retenções federais (Lei 10.833/2003 arts. 30–32), em centavos,
        // calculadas pelo RetencoesNfseService do NfeBrasil na montagem do payload.
        public readonly bool    $retencaoFederalSujeito = false,
        public readonly int     $retPisCentavos = 0,
        public readonly int     $retCofinsCentavos = 0,
        public readonly int     $retCsllCentavos = 0,
        // R-NFSE-005 — DPS em XML: série numérica (TSSerieDPS, até 5 dígitos) e regime do
        // prestador (`nfe_business_configs.regime`), que vira `regTrib/opSimpNac`.
        public readonly string  $serieDps = '1',
        public readonly ?string $regimePrestador = null,
        // R-NFSE-006 — código de tributação nacional (item + subitem + desdobro, 6 dígitos).
        // Null = o subitem tem um desdobro só e a tabela resolve.
        public readonly ?string $cTribNac = null,
    ) {}

    /**
     * Mesma nota com o número da DPS definitivo. O número sai do id da `nfse_emissoes`
     * (único, sequencial, sem zero à esquerda — TSNumDPS `[1-9][0-9]{0,14}`), que só
     * existe depois de gravar a emissão.
     */
    public function comNumeroDps(string $numero): self
    {
        return new self(...array_merge(get_object_vars($this), ['rpsNumero' => $numero]));
    }

    /**
     * Mesma nota sem o certificado. É o que vai para a fila: o EmitirNfseJob é serializado
     * em `jobs`/`failed_jobs` quando a fila é banco, e o PFX e a senha decifrados não podem
     * ficar ali (lote 2, PR 2.3). O NfseEmissaoService resolve o certificado pelo
     * business_id na hora de enviar.
     */
    public function semCertificado(): self
    {
        return new self(...array_merge(get_object_vars($this), ['certPfxBase64' => null, 'certSenha' => null]));
    }

    /** Mesma nota com o certificado resolvido na hora do envio. */
    public function comCertificado(string $pfxBase64, string $senha): self
    {
        return new self(...array_merge(get_object_vars($this), ['certPfxBase64' => $pfxBase64, 'certSenha' => $senha]));
    }

    public function retencaoFederalCentavos(): int
    {
        return $this->retPisCentavos + $this->retCofinsCentavos + $this->retCsllCentavos;
    }

    public function valorIss(): float
    {
        if ($this->issRetido) {
            return 0.0;
        }
        return round($this->valorServicos * $this->aliquotaIss, 2);
    }

    /** ISS que o tomador retém (US-FIN-072). 0 quando o ISS não é retido. */
    public function valorIssRetido(): float
    {
        return $this->issRetido ? round($this->valorServicos * $this->aliquotaIss, 2) : 0.0;
    }

    public function idempotencyKey(): string
    {
        return hash('sha256', implode('|', [
            $this->businessId,
            $this->tomadorCnpj ?? $this->tomadorCpf,
            $this->valorServicos,
            $this->descricao,
            $this->competencia->format('Y-m'),
            // Só entra quando marcado: nota sem a marcação mantém a chave de antes.
            ...($this->retencaoFederalSujeito ? ['ret-fed'] : []),
        ]));
    }
}
