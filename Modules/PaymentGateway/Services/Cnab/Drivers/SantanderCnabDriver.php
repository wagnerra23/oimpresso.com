<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Services\Cnab\Drivers;

use Eduardokum\LaravelBoleto\Boleto\Banco\Santander as SantanderBoleto;
use Modules\PaymentGateway\Services\Cnab\CnabBoletoAdapter;

/**
 * Driver CNAB Santander — boleto registrado via arquivo remessa CNAB 240.
 *
 * Onda 4f.cnab — ADR 0170-bancos-nativos-top5-drivers-separados (v3).
 *
 * Herda toda a lógica de:
 *   - emitirBoleto (constrói Boleto + grava remessa em Storage)
 *   - cancelar (instrução PEDIDO_CANCELAMENTO 240)
 *   - healthCheck (smoke test instanciando Boleto)
 *   - emitirPix/emitirPixAutomatico/cobrarCartao/refund/consultar/processWebhook (throw)
 *
 * Especificidades Santander:
 *   - Layout CNAB 240 (padrão Santander multibanco, layout H7815 v8.4 jul/2025)
 *   - Carteiras válidas: 101 (Cobrança Simples Rápida c/ Registro — ECR · default
 *     da maioria dos clientes PJ) ou 201 (Penhor). A lib aceita ambas e mapeia
 *     '1'→'101', '4'→'102', '5'→'101' internamente.
 *   - Campos obrigatórios CNAB (lib):
 *       'numero', 'codigoCliente', 'carteira' — gerados/preenchidos pelo
 *       Adapter base via configToBoletoArgs() + numero sequencial derivado
 *       do idempotencyKey (crc32 % 99999999).
 *   - Santander NÃO usa 'convenio' (substituído pelo 'codigo_cliente' fornecido
 *     pelo gerente PJ na homologação CNAB — 7 dígitos).
 *   - Conta tem DV (1 dígito), agência típica sem DV.
 *
 * Lib upstream: `Eduardokum\LaravelBoleto\Boleto\Banco\Santander`
 * (linha 16: setCamposObrigatorios('numero', 'codigoCliente', 'carteira')).
 *
 * Wizard `/payment-gateways/create?gateway_key=santander_cnab` (UI fora desta onda)
 * apresenta:
 *   - agencia (4 dígitos · sem DV)
 *   - conta (até 9 dígitos)
 *   - conta_dv (1 dígito)
 *   - carteira (default '101' · cliente confirma na homologação)
 *   - codigo_cliente (7 dígitos · "Código do Cedente" fornecido pelo banco)
 *   - cedente_nome / cedente_documento (CNPJ titular conta)
 *   - cedente_endereco/cep/uf/cidade (opcional · fallback Adapter)
 *
 * Multi-tenant Tier 0: business_id flui via $cred->business_id no Adapter base
 * (gravação remessa em Storage::disk()->put('cnab-remessas/biz-{id}/cred-{id}/…')).
 *
 * Coexistência REST: cliente pode cadastrar `santander_cnab` (esta onda — dia 1)
 * e futuramente `santander_api` (Onda 4j — REST Open Banking pós-homologação
 * 3-5 semanas, com cert A1 ICP-Brasil). Cada um é gateway_key próprio,
 * audit log próprio, replay próprio — sem `mode` polui credencial.
 *
 * Refs:
 *   - ADR 0170-bancos-nativos-top5-drivers-separados (v3) — Onda 4f.cnab
 *   - Fundação: ADR 0170 Onda 4f.0 (CnabBoletoAdapter)
 *   - Layout oficial: cms.santander.com.br/.../layout-cobranca-240-...-jul-2025
 *   - Dossiê banco: memory/sessions/2026-05-25-arte-banco-santander.md
 */
final class SantanderCnabDriver extends CnabBoletoAdapter
{
    public function key(): string
    {
        return 'santander_cnab';
    }

    protected function getBoletoClass(): string
    {
        return SantanderBoleto::class;
    }

    /**
     * Santander padrão multibanco H7815 — versão 8.4 jul/2025 (CMS oficial).
     * NÃO existe layout 400 ativo pra Santander novo (descontinuado em 2018+).
     */
    protected function getLayoutVersion(): int
    {
        return 240;
    }

    /**
     * Campos do `config_json` obrigatórios pra Santander CNAB.
     *
     * Por que NÃO inclui 'convenio': Santander não usa convênio CNAB — usa
     * 'codigo_cliente' (Código do Cedente · 7 dígitos · entregue pelo gerente
     * PJ na homologação). Lib `Santander` reflete isso em
     * setCamposObrigatorios('numero', 'codigoCliente', 'carteira').
     *
     * 'conta_dv' incluído porque conta Santander PJ tem DV de 1 dígito (a lib
     * expõe getContaDv() — ausência quebra a linha digitável).
     */
    protected function camposObrigatoriosCnab(): array
    {
        return [
            'agencia',
            'conta',
            'conta_dv',
            'carteira',
            'codigo_cliente',
            'cedente_nome',
            'cedente_documento',
        ];
    }
}
