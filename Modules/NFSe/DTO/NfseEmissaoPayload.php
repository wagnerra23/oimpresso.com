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
