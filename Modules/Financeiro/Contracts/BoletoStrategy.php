<?php

namespace Modules\Financeiro\Contracts;

use Modules\Financeiro\Models\BoletoRemessa;
use Modules\Financeiro\Models\ContaBancaria;
use Modules\Financeiro\Models\Titulo;

/**
 * Contrato pra emissão/cancelamento/consulta de boleto.
 *
 * 3 implementações concretas previstas (ADR ARQ-0003):
 *  - CnabDirectStrategy : eduardokum/laravel-boleto offline (MVP, ADR TECH-0003)
 *  - GatewayStrategy    : Asaas / Iugu / Pagar.me via HTTP (onda futura)
 *  - HybridStrategy     : delega por regra (cliente VIP -> CNAB, resto -> Gateway)
 *
 * Pattern obrigatório de teste: BoletoStrategyContractTest itera os bancos
 * suportados e valida emitir/cancelar/statusAtual em todos.
 */
interface BoletoStrategy
{
    /**
     * Gera o boleto pra um título usando a conta bancária especificada.
     * Idempotente por (business_id, titulo_id, idempotency_key).
     * Re-chamadas com mesmo (titulo, conta) retornam a mesma BoletoRemessa.
     */
    public function emitir(Titulo $titulo, ContaBancaria $conta): BoletoRemessa;

    /**
     * Cancela um boleto emitido. Não-reversível — boleto cancelado fica
     * no histórico com status='cancelado'.
     */
    public function cancelar(BoletoRemessa $remessa, string $motivo = ''): void;

    /**
     * Lê status atual do boleto. Em MVP mock, retorna o status persistido.
     * Em ondas futuras, consulta o banco/gateway via API/CNAB retorno.
     */
    public function statusAtual(BoletoRemessa $remessa): string;
}
