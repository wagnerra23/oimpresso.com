<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Services\Cnab\Drivers;

use Eduardokum\LaravelBoleto\Boleto\Banco\Itau as ItauBoleto;
use Modules\PaymentGateway\Services\Cnab\CnabBoletoAdapter;

/**
 * Driver CNAB Itaú — Onda 4f.cnab/Itau (ADR 0170 v3, drivers separados).
 *
 * Fino sobre `CnabBoletoAdapter`: apenas declara FQCN da classe Boleto da
 * lib (`Eduardokum\LaravelBoleto\Boleto\Banco\Itau`), layout CNAB 240
 * (Itaú abandonou 400 em 2024 — Manual Itaú v10.2) e os 5 campos de
 * `config_json` que o banco exige.
 *
 * Carteiras Itaú aceitas pela lib: 109, 110, 111, 112, 115, 121, 180, 188.
 *   - 109 — cobrança simples com registro (mais comum PJ)
 *   - 112 — cobrança simples sem registro
 *   - 115 — direta especial
 *   - 188 — escritural eletrônica (CNAB 240)
 *
 * Nosso Número Itaú: 8 dígitos + 1 DV calculado por agencia+conta+carteira
 * (CalculoDV::itauNossoNumero). Conta DV é mandatório no campo livre
 * (parseCampoLivre lê 'agenciaDv'/'contaCorrenteDv').
 *
 * Multi-tenant: business_id flui via $cred->business_id → fundação
 * grava em cnab-remessas/biz-{id}/cred-{id}/{idem}.rem (não vaza
 * cross-tenant).
 *
 * Refs: ADR 0170-bancos-nativos-top5-drivers-separados — Onda 4f.cnab/Itau
 */
final class ItauCnabDriver extends CnabBoletoAdapter
{
    public function key(): string
    {
        return 'itau_cnab';
    }

    protected function getBoletoClass(): string
    {
        return ItauBoleto::class;
    }

    protected function getLayoutVersion(): int
    {
        // Itaú padronizou CNAB 240 em 2024 (descontinuou 400 pra novos
        // contratos). Manual Itaú v10.2, seção 1.2. Drivers de clientes
        // legados em 400 podem subclassear se necessário.
        return 240;
    }

    /**
     * @return array<int, string>
     */
    protected function camposObrigatoriosCnab(): array
    {
        // Itaú: agencia(4) + conta(5) + contaDv(1) + carteira(3) +
        // cedente (nome+CNPJ). Sem convenio/codigoCliente (Itaú usa
        // conta-corrente como identificador do cedente).
        // Confirmado em lib: Itau::parseCampoLivre lê 'contaCorrenteDv'
        // e Itau::getCampoLivre calcula DV via CalculoDV::itauContaCorrente
        // se contaDv estiver vazio — porém pra cobrança registrada é
        // obrigatório do banco (Manual Itaú seção 3.1).
        return [
            'agencia',
            'conta',
            'conta_dv',
            'carteira',
            'cedente_nome',
            'cedente_documento',
        ];
    }
}
