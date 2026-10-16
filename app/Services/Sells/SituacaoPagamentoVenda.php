<?php

declare(strict_types=1);

namespace App\Services\Sells;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Situação de pagamento de uma venda, derivada dos títulos a receber do Financeiro (ADR 0434 item 2).
 *
 * Desde a ADR 0434 "pago" deixou de ser etapa do pedido (`paid` na FSM `venda_com_producao`) e passou
 * a ser situação dos títulos: quem sabe se a venda foi paga é o Financeiro. Este service só LÊ —
 * não calcula total de venda, não grava nada, não mexe em baixa.
 *
 * Títulos considerados: `fin_titulos` com origem `venda`, origem_id = id da venda, tipo `receber`,
 * mesmo business_id (Tier 0), não cancelados e não apagados (soft delete). Parcelas são linhas
 * separadas (parcela_numero preenchido) e entram na soma.
 *
 *  - sem_titulo : nenhum título vivo ligado à venda (Financeiro desligado, venda sem bridge, cancelada)
 *  - pago       : todos os títulos vivos com valor_aberto = 0
 *  - pendente   : nada baixado — valor_aberto somado = valor_total somado
 *  - parcial    : algo baixado, algo em aberto
 *
 * `valor_aberto` é a soma de `fin_titulos.valor_aberto` (o observer do Financeiro a mantém), em 2
 * casas. Uso: API do app (`pagamento: {situacao, valor_aberto}` no detalhe do pedido) e quem mais
 * precisar mostrar a situação sem olhar a etapa da FSM.
 */
final class SituacaoPagamentoVenda
{
    public const SEM_TITULO = 'sem_titulo';

    public const PENDENTE = 'pendente';

    public const PARCIAL = 'parcial';

    public const PAGO = 'pago';

    /** @return array{situacao: string, valor_aberto: float} */
    public function daVenda(int $businessId, int $transactionId): array
    {
        return $this->dasVendas($businessId, [$transactionId])[$transactionId];
    }

    /**
     * Várias vendas numa consulta só (listas). Toda venda pedida volta no resultado.
     *
     * @param  list<int>  $transactionIds
     * @return array<int, array{situacao: string, valor_aberto: float}>
     */
    public function dasVendas(int $businessId, array $transactionIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $transactionIds)));
        $vazio = ['situacao' => self::SEM_TITULO, 'valor_aberto' => 0.0];
        $resultado = array_fill_keys($ids, $vazio);
        if ($ids === [] || ! Schema::hasTable('fin_titulos')) {
            return $resultado;
        }

        $linhas = DB::table('fin_titulos')
            ->where('business_id', $businessId)
            ->where('origem', 'venda')
            ->where('tipo', 'receber')
            ->whereIn('origem_id', $ids)
            ->where('status', '!=', 'cancelado')
            ->whereNull('deleted_at')
            ->groupBy('origem_id')
            ->selectRaw('origem_id, SUM(valor_total) AS total, SUM(valor_aberto) AS aberto')
            ->get();

        foreach ($linhas as $l) {
            $total = round((float) $l->total, 2);
            $aberto = round((float) $l->aberto, 2);
            $resultado[(int) $l->origem_id] = [
                'situacao' => $this->situacao($total, $aberto),
                'valor_aberto' => max($aberto, 0.0),
            ];
        }

        return $resultado;
    }

    private function situacao(float $total, float $aberto): string
    {
        if ($aberto <= 0.0) {
            return self::PAGO;
        }

        return $aberto >= $total ? self::PENDENTE : self::PARCIAL;
    }
}
