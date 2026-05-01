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
    ) {}

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
        ]));
    }
}
