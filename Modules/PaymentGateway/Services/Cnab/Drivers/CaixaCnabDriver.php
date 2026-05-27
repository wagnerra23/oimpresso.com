<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Services\Cnab\Drivers;

use Eduardokum\LaravelBoleto\Boleto\Banco\Caixa as CaixaBoleto;
use Modules\PaymentGateway\Services\Cnab\CnabBoletoAdapter;

/**
 * Driver CNAB Caixa Econômica Federal — boleto registrado via SIGCB CNAB 240.
 *
 * Onda 4f.cnab/Caixa — ADR 0170-bancos-nativos-top5-drivers-separados (v3).
 *
 * Por que CNAB e NÃO REST: dossiê banco-caixa
 * (`memory/sessions/2026-05-25-arte-banco-caixa.md`) confirma que (1) SIGCB
 * boleto Caixa é SOAP/XML B2B legado sem REST oficial pra PME, (2) portal
 * `desenvolvedores.caixa.gov.br` está literalmente quebrado (504) em maio/2026,
 * (3) comunidade ACBr classifica Caixa como "único banco sem suporte real ao
 * desenvolvedor". O ÚNICO caminho viável pra Caixa hoje é CNAB 240 SIGCB via
 * `eduardokum/laravel-boleto` + upload manual de remessa no Internet Banking
 * CAIXA. Pix Automático REST fica pra Q3-2026 quando convênio SIGCB-Pix maturar.
 *
 * Herda toda a lógica de:
 *   - emitirBoleto (constrói Boleto + grava remessa em Storage scoped biz/cred)
 *   - cancelar (instrução PEDIDO_CANCELAMENTO 240 — Caixa código '02')
 *   - healthCheck (smoke test instanciando Boleto)
 *   - emitirPix/emitirPixAutomatico/cobrarCartao/refund/consultar/processWebhook
 *     → DriverNotSupportedException (CNAB é file-based)
 *
 * Especificidades Caixa SIGCB:
 *   - Layout CNAB 240 (SIGCB — Sistema de Gestão de Cobrança Bancária Caixa).
 *     NÃO existe layout 400 ativo na Caixa moderna (SIGCB nasceu em 240).
 *   - Carteira ÚNICA aceita pela lib: 'RG' (Registrada — cobrança com registro).
 *     A lib `Caixa` traz `protected $carteiras = ['RG']` (linha 31), e SR
 *     (Sem Registro) foi descontinuado na prática pra novos convênios SIGCB.
 *   - Campos obrigatórios CNAB (lib linha 16):
 *       'numero', 'agencia', 'carteira', 'codigoCliente'.
 *     'numero' é gerado pelo Adapter base (crc32(idempotencyKey) % 99999999),
 *     então no `config_json` cliente entrega APENAS:
 *       agencia + codigo_cliente (+ carteira default 'RG').
 *   - Caixa NÃO usa 'convenio' separado: o `codigoCliente` (até 6 dígitos)
 *     É a identificação SIGCB do cedente — entregue pelo gerente PJ junto da
 *     senha SIGCB no momento da assinatura presencial do "Convênio Caixa
 *     Cobrança Bancária".
 *   - Caixa NÃO usa 'conta' tradicional pra CNAB: a lib sobrepõe `getConta()`
 *     pra retornar `getCodigoCliente()` (lib linha 83-86) — então `conta` é
 *     opcional/descartável em config_json (cliente pode preencher só pra
 *     exibir no painel, mas a lib ignora pro nossoNumero/linha digitável).
 *   - NossoNumero formato: 2 chars (composição '14' p/ RG) + 15 chars
 *     (sequencial zero-padded) = 17 chars (sem DV; DV vai em
 *     getNossoNumeroBoleto via Mod 11).
 *   - "modalidade" mencionada em docs SIGCB refere-se ao tipo de carteira
 *     dentro do convênio (cobrança simples/caucionada/desconto) — NÃO é
 *     parâmetro de envio CNAB pela lib, e sim acordo SIGCB cadastrado na
 *     agência. NÃO incluímos em camposObrigatoriosCnab().
 *
 * Lib upstream: `Eduardokum\LaravelBoleto\Boleto\Banco\Caixa`
 * (linha 16: setCamposObrigatorios('numero', 'agencia', 'carteira', 'codigoCliente')).
 *
 * Wizard `/payment-gateways/create?gateway_key=caixa_cnab` (UI fora desta onda)
 * apresenta:
 *   - agencia (4 dígitos · sem DV)
 *   - codigo_cliente (até 6 dígitos · "Código do Cedente SIGCB" entregue
 *     pelo gerente PJ presencialmente — NUNCA self-service web)
 *   - carteira (default 'RG' · única ativa)
 *   - cedente_nome / cedente_documento (CNPJ titular convênio SIGCB)
 *   - cedente_endereco/cep/uf/cidade (opcional · fallback Adapter)
 *
 * Multi-tenant Tier 0: business_id flui via $cred->business_id no Adapter base
 * (gravação remessa em Storage::disk()->put('cnab-remessas/biz-{id}/cred-{id}/…')).
 *
 * Sem coexistência REST nesta onda: per ADR 0170 v3 + dossiê Caixa, NÃO
 * existe `caixa_api` planejado pra top-5 (Pix Automático Caixa REST adiada
 * pra Q3-2026 condicional). Cliente cadastra apenas `caixa_cnab` hoje.
 *
 * Refs:
 *   - ADR 0170-bancos-nativos-top5-drivers-separados (v3) — Onda 4f.cnab/Caixa
 *   - Fundação: ADR 0170 Onda 4f.0 (CnabBoletoAdapter)
 *   - Dossiê banco: memory/sessions/2026-05-25-arte-banco-caixa.md
 *   - Doc SIGCB: laravel-boleto.readthedocs.io/en/latest/usage/remessa/caixa.html
 */
final class CaixaCnabDriver extends CnabBoletoAdapter
{
    public function key(): string
    {
        return 'caixa_cnab';
    }

    protected function getBoletoClass(): string
    {
        return CaixaBoleto::class;
    }

    /**
     * Caixa SIGCB padrão CNAB 240 (Centralizadora 104).
     * NÃO existe layout 400 ativo na Caixa moderna pra novos convênios SIGCB.
     */
    protected function getLayoutVersion(): int
    {
        return 240;
    }

    /**
     * Campos do `config_json` obrigatórios pra Caixa CNAB SIGCB.
     *
     * Por que ESTES e não outros:
     *   - 'agencia': identificação da centralizadora Caixa (4 dígitos sem DV).
     *   - 'codigo_cliente': identificação SIGCB do cedente (até 6 dígitos) —
     *     entregue presencialmente pelo gerente PJ. SUBSTITUI 'convenio' e
     *     SUBSTITUI 'conta' (lib sobrepõe getConta() pra retornar codigoCliente).
     *   - 'carteira': default 'RG' (única ativa na lib + única que SIGCB-Cobrança
     *     vende pra novos clientes em 2026).
     *   - 'cedente_nome' / 'cedente_documento': dados pra montar boleto e
     *     gravar no header da remessa CNAB.
     *
     * Por que NÃO inclui 'conta'/'conta_dv': lib Caixa.php linha 83-86 sobrepõe
     * `getConta()` retornando `getCodigoCliente()` — Caixa SIGCB não usa conta
     * tradicional pro CNAB cobrança, apenas o código de convênio.
     *
     * Por que NÃO inclui 'convenio': Caixa SIGCB chama de "codigoCliente"
     * (lib usa esse nome). Reservar 'convenio' aqui seria pegadinha pro cliente.
     *
     * Por que NÃO inclui 'modalidade': não é parâmetro da lib (ver doc SIGCB
     * — modalidade é tipo de carteira dentro do convênio cadastrado na agência,
     * não vai no CNAB de envio).
     */
    protected function camposObrigatoriosCnab(): array
    {
        return [
            'agencia',
            'codigo_cliente',
            'carteira',
            'cedente_nome',
            'cedente_documento',
        ];
    }
}
