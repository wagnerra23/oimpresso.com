<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Services\Cnab\Drivers;

use Eduardokum\LaravelBoleto\Boleto\Banco\Bradesco;
use Modules\PaymentGateway\Services\Cnab\CnabBoletoAdapter;

/**
 * Driver CNAB Bradesco — gateway_key='bradesco_cnab'.
 *
 * Onda 4f.cnab — ADR 0170-bancos-nativos-top5-drivers-separados (v3 Wagner 2026-05-26).
 *
 * Implementação fina herdando CnabBoletoAdapter (Onda 4f.0 fundação 466 LOC).
 * 100% da lógica HTTP/file/Storage/healthcheck vem da abstract — este driver
 * apenas declara:
 *
 *   - key():                     'bradesco_cnab'
 *   - getBoletoClass():          Eduardokum\LaravelBoleto\Boleto\Banco\Bradesco
 *   - getLayoutVersion():        240 (CNAB FEBRABAN moderno — default Bradesco)
 *   - camposObrigatoriosCnab():  agencia, conta, carteira, cedente_nome, cedente_documento
 *
 * Carteiras Bradesco aceitas (validadas pela lib em $carteiras):
 *   '02' → Com registro
 *   '04' → Com registro
 *   '09' → Com registro (mais comum em PMEs)
 *   '21' → Com Registro — Pagável somente no Bradesco
 *   '26' → Com Registro — Emissão na Internet
 *
 * NOTA layout: Bradesco também aceita CNAB 400 (legado, ainda muito usado em
 * agências menores). Onda 4f.cnab default é 240 (moderno); se cliente precisa
 * 400, override pode ser feito num subclass futuro ou via config_json
 * `layout_cnab=400` em ondas posteriores (escopo Onda 4f.cnab.legacy fora desta).
 *
 * Multi-tenant Tier 0 (ADR 0093) herdado da fundação:
 *   - assertCredential valida gateway_key + business_id flui via $cred->business_id
 *   - gravarRemessa usa path cnab-remessas/biz-{id}/cred-{id}/{idem}.rem
 *
 * Refs: ADR 0170-bancos-nativos-top5-drivers-separados Onda 4f.cnab/Bradesco
 */
class BradescoCnabDriver extends CnabBoletoAdapter
{
    public function key(): string
    {
        return 'bradesco_cnab';
    }

    protected function getBoletoClass(): string
    {
        return Bradesco::class;
    }

    protected function getLayoutVersion(): int
    {
        return 240;
    }

    /**
     * Campos config_json obrigatórios pra Bradesco CNAB:
     *
     *   - agencia: 4 dígitos (ex '1234')
     *   - conta: até 7 dígitos (ex '0567890')
     *   - carteira: '02' | '04' | '09' | '21' | '26' (validado pela lib)
     *   - cedente_nome: razão social
     *   - cedente_documento: CNPJ (14 dígitos sem máscara)
     *
     * Opcionais comuns (passthrough buildBoletoData/configToBoletoArgs):
     *   - agenciaDv, contaDv (Bradesco geralmente não exige)
     *   - cedente_endereco, cedente_cep, cedente_uf, cedente_cidade
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
