<?php

namespace Modules\Financeiro\Services\Banking;

use Modules\Financeiro\Contracts\PagadorBanco;
use Modules\Financeiro\Models\ContaBancaria;
use Modules\Financeiro\Models\PagamentoBanco;
use Modules\RecurringBilling\Services\Banking\InterBankingClient;

/**
 * Pagamento de título a pagar pelo Banco Inter — boleto e Pix (US-FIN-079).
 *
 * Usa a mesma credencial mTLS que o extrato: a credencial Inter do PaymentGateway ligada à conta
 * (`ContaBancaria::credencialGateway`; até 2026-11-24 vinha de `rb_boleto_credentials`).
 * Conservador de propósito: o ENVIO nunca responde `pago`, mesmo que o banco diga "realizado" —
 * a baixa vem da CONSULTA seguinte. Status que este mapa não conhece ficam aguardando, nunca pagos.
 */
class InterPagadorBanco implements PagadorBanco
{
    private const PAGO = ['PAGO', 'REALIZADO', 'EFETIVADO', 'LIQUIDADO'];
    private const RECUSADO = ['CANCELADO', 'REJEITADO', 'RECUSADO', 'FALHA', 'ERRO', 'DEVOLVIDO', 'EXPIRADO'];

    public function enviar(PagamentoBanco $pagamento, ContaBancaria $conta): array
    {
        $cliente = $this->cliente($conta);
        $valor = round((float) $pagamento->valor, 2);

        $raw = $pagamento->meio === 'boleto'
            ? $cliente->pagarBoleto([
                'codBarraLinhaDigitavel' => $pagamento->destino,
                'valorPagar' => $valor,
                'dataPagamento' => now()->toDateString(),
            ], $pagamento->idempotency_key)
            : $cliente->pagarPix([
                'valor' => $valor,
                'descricao' => 'Pagamento de título',
                'destinatario' => ['tipo' => 'CHAVE', 'chave' => $pagamento->destino],
            ], $pagamento->idempotency_key);

        $mapa = $this->mapear($raw);

        return ['status' => $mapa === PagamentoBanco::STATUS_RECUSADO ? $mapa : PagamentoBanco::STATUS_AGUARDANDO] + $this->base($raw);
    }

    public function consultar(PagamentoBanco $pagamento, ContaBancaria $conta): array
    {
        $cliente = $this->cliente($conta);
        $codigo = (string) $pagamento->codigo_transacao;
        if ($codigo === '') {
            return ['status' => PagamentoBanco::STATUS_AGUARDANDO, 'codigo_transacao' => null, 'raw' => []];
        }

        $raw = $pagamento->meio === 'boleto'
            ? $cliente->consultarPagamentoBoleto($codigo)
            : $cliente->consultarPix($codigo);
        // A consulta de boleto devolve lista; a de Pix, o objeto.
        $item = array_is_list($raw) ? ($raw[0] ?? []) : $raw;

        return ['status' => $this->mapear($item)] + $this->base($item);
    }

    private function mapear(array $raw): string
    {
        $status = strtoupper((string) ($raw['statusPagamento'] ?? $raw['status'] ?? $raw['tipoRetorno'] ?? ''));

        return match (true) {
            in_array($status, self::PAGO, true) => PagamentoBanco::STATUS_PAGO,
            in_array($status, self::RECUSADO, true) => PagamentoBanco::STATUS_RECUSADO,
            default => PagamentoBanco::STATUS_AGUARDANDO,
        };
    }

    private function base(array $raw): array
    {
        return [
            'codigo_transacao' => $raw['codigoTransacao'] ?? $raw['codigoSolicitacao'] ?? null,
            'motivo' => $raw['motivo'] ?? $raw['detail'] ?? null,
            'raw' => $raw,
        ];
    }

    private function cliente(ContaBancaria $conta): InterBankingClient
    {
        $cred = $conta->credencialGateway('inter');
        if ($cred === null) {
            throw new \DomainException('Conta sem credencial Inter ativa para pagamento.');
        }

        return new InterBankingClient($cred->config_json ?? [], (int) $conta->business_id);
    }
}
