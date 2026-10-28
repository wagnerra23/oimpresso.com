<?php

namespace Modules\Financeiro\Contracts;

use Modules\Financeiro\Models\ContaBancaria;
use Modules\Financeiro\Models\PagamentoBanco;

/**
 * Banco que executa o pagamento de um título a pagar (US-FIN-079).
 *
 * As duas operações devolvem o mesmo formato:
 *   ['status' => 'aguardando_banco'|'pago'|'recusado', 'codigo_transacao' => ?string,
 *    'motivo' => ?string, 'raw' => array]
 * `pago` só pode vir quando o banco de fato debitou. Falha de rede/credencial lança exceção.
 */
interface PagadorBanco
{
    public function enviar(PagamentoBanco $pagamento, ContaBancaria $conta): array;

    public function consultar(PagamentoBanco $pagamento, ContaBancaria $conta): array;
}
