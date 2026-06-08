<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Services\Cnab\Drivers;

use Eduardokum\LaravelBoleto\Boleto\Banco\Cresol as CresolBoleto;
use Modules\PaymentGateway\Services\Cnab\CnabBoletoAdapter;

/**
 * Driver CNAB Cresol — cooperativa de crédito (cód. banco 133).
 *
 * Onda 4f.cnab — ADR 0170-bancos-nativos-top5-drivers-separados (v3).
 * Fundação compartilhada: {@see CnabBoletoAdapter}.
 *
 * Características Cresol (extraídas da lib `Eduardokum\LaravelBoleto\Boleto\Banco\Cresol`):
 *
 *   - Layout CNAB suportado: 240 (versão moderna padrão Febraban — Onda 4f.cnab).
 *   - Carteira disponível: APENAS '09' (cobrança simples com registro).
 *   - Campo livre (posições 20-44 do código de barras):
 *       AAAA(agencia 4) + CC(carteira 2) + NNNNNNNNNNN(número 11) + CCCCCCC(conta 7) + '0'
 *   - Nosso número: 11 dígitos + 1 DV (CalculoDV::cresolNossoNumero baseado em carteira+numero).
 *   - CIP default '000' (cobrança interna cooperativa; cliente pode sobrescrever via
 *     variaveis_adicionais futuras se for cooperado de central diferente).
 *   - Cresol é cooperativa de crédito brasileira — apesar da estrutura cooperativa
 *     análoga ao Sicredi, a lib NÃO exige campos cooperativa/posto separados
 *     (estão embutidos na agência via convênio Bacen 133). Diferente do Sicredi
 *     que parsa byte_seq cooperativa/posto/conta em `parseCampoLivre`.
 *
 * Multi-tenant Tier 0: business_id flui via $cred->business_id (CnabBoletoAdapter
 * grava remessa em `cnab-remessas/biz-{id}/cred-{id}/{idem}.rem`).
 *
 * NÃO mexer (escopo isolado paralelo Onda 4f.cnab):
 *   - PaymentGatewayService::DRIVERS (parent consolida registry)
 *   - CnabBoletoAdapter (fundação 4f.0)
 *   - Demais drivers irmãos (Bradesco/Itau/BB/Santander/Caixa/Sicredi/...)
 *   - Wire Delphi
 *
 * Refs: ADR 0170-bancos-nativos-top5-drivers-separados Onda 4f.cnab/Cresol
 */
class CresolCnabDriver extends CnabBoletoAdapter
{
    public function key(): string
    {
        return 'cresol_cnab';
    }

    protected function getBoletoClass(): string
    {
        return CresolBoleto::class;
    }

    protected function getLayoutVersion(): int
    {
        // Onda 4f.cnab padroniza CNAB 240 (Febraban moderno) — Cresol aceita.
        return 240;
    }

    /**
     * Campos `config_json` obrigatórios pra Cresol.
     *
     * Subset realmente exigido pela lib (Cresol::getCampoLivre + AbstractBoleto):
     *   - agencia (4 dígitos, agência da cooperativa Cresol Central)
     *   - conta (até 7 dígitos, conta corrente do cooperado)
     *   - carteira (única aceita: '09' — cobrança simples com registro)
     *   - cedente_nome (razão social do beneficiário)
     *   - cedente_documento (CNPJ do beneficiário, 14 dígitos)
     *
     * Conta_dv NÃO é exigido pela lib (Cresol não usa contaDv no campo livre,
     * diferente de Bradesco/Itaú). Convenio NÃO usado (carteira '09' fixa).
     *
     * @return array<int, string>
     */
    protected function camposObrigatoriosCnab(): array
    {
        return [
            'agencia',
            'conta',
            'carteira',
            'cedente_nome',
            'cedente_documento',
        ];
    }
}
