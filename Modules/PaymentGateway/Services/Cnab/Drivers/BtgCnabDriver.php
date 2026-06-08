<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Services\Cnab\Drivers;

use Eduardokum\LaravelBoleto\Boleto\Banco\Btg;
use Modules\PaymentGateway\Services\Cnab\CnabBoletoAdapter;

/**
 * BTG Pactual — driver CNAB 240 (cobrança registrada PJ).
 *
 * BTG Pactual é banco de investimento que oferece cobrança PJ moderna
 * (boleto + PIX integrado), porém na fundação 4f.cnab usamos APENAS o
 * fluxo CNAB 240 file-based (envio remessa + retorno upload). A API REST
 * de boleto/PIX do BTG (developers.empresas.btgpactual.com) fica pra
 * onda futura como `btg_api` (driver separado), conforme ADR 0170 v3.
 *
 * Especificidades BTG:
 *   - CNAB 240 APENAS — não existe CNAB 400 BTG na lib eduardokum
 *     (validado em vendor/.../Cnab/Remessa/Cnab400/Banco — sem Btg.php)
 *   - Carteiras aceitas pela lib: [1, 2, 3, 4, 5, 6]
 *       · 1 = Cobrança Simples (mais comum PJ)
 *       · 2 = Cobrança Vinculada
 *       · 3 = Cobrança Caucionada
 *       · 4 = Cobrança Descontada
 *       · 5 = Cobrança Vendor
 *       · 6 = Cobrança Cessão
 *   - `codigo_cliente` (12 dígitos) emitido pelo BTG no onboarding —
 *     obrigatório pra compor `agenciaCodigoBeneficiario` (formato 0000/000000000000)
 *   - CNPJ da conta BTG deve bater com o CNPJ do beneficiário (regra BTG)
 *   - Arquivo remessa upload no portal BTG aceita extensão `.rem` ou `.txt`
 *
 * Nosso Número (gerado pela lib):
 *   - 11 dígitos formato geral + 1 DV (calculado por CalculoDV::btgNossoNumero
 *     baseado em carteira+numero)
 *   - Sequencial `numero` derivado do idempotencyKey (estratégia herdada
 *     da fundação 4f.0 — driver concreto pode override pra sequencial
 *     persistente em DB no futuro, fora do escopo desta onda)
 *
 * Refs:
 *   - ADR 0170-bancos-nativos-top5-drivers-separados (Onda 4f.cnab/BTG)
 *   - https://developers.empresas.btgpactual.com/docs/cnab-febraban-240-posições
 *   - vendor/eduardokum/laravel-boleto/src/Boleto/Banco/Btg.php
 *
 * Multi-tenant Tier 0: business_id flui via $cred->business_id na
 * fundação CnabBoletoAdapter (gravarRemessa + assertCredential), nada
 * adicional necessário neste driver fino.
 */
final class BtgCnabDriver extends CnabBoletoAdapter
{
    public function key(): string
    {
        return 'btg_cnab';
    }

    protected function getBoletoClass(): string
    {
        return Btg::class;
    }

    protected function getLayoutVersion(): int
    {
        // BTG só tem CNAB 240 na lib eduardokum/laravel-boleto.
        // Confirmado em vendor/.../Cnab/Remessa/Cnab400/Banco/ (sem Btg.php).
        return 240;
    }

    /**
     * Campos obrigatórios em `config_json`.
     *
     * - agencia: agência BTG (numérica, geralmente '0050' ou '0001')
     * - conta: conta corrente PJ vinculada à cobrança
     * - carteira: 1..6 (mais comum: 1 = Cobrança Simples)
     * - codigo_cliente: 12 dígitos emitidos pelo BTG (composição
     *     agenciaCodigoBeneficiario do boleto)
     * - cedente_nome: razão social do beneficiário
     * - cedente_documento: CNPJ do beneficiário (deve == CNPJ da conta BTG)
     *
     * @return array<int, string>
     */
    protected function camposObrigatoriosCnab(): array
    {
        return [
            'agencia',
            'conta',
            'carteira',
            'codigo_cliente',
            'cedente_nome',
            'cedente_documento',
        ];
    }
}
