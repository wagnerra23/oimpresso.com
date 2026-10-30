<?php

declare(strict_types=1);

namespace Modules\Producao\Services;

use App\Domain\Fsm\Modelos\RegrasDaEmpresa;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Producao\Entities\Ordem;
use Modules\Producao\Exceptions\ApontamentoRecusado;

/**
 * "Produção só libera com o sinal pago" (UC-FLX-28 · US-PCP-025).
 *
 * A empresa liga a regra com um percentual mínimo (`fsm_modelos_empresa.sinal_minimo_pct`, via
 * RegrasDaEmpresa). Ligada, a OS por item só sai de Aguardando liberação quando o total pago da
 * venda de origem (`transaction_payments`, troco descontado) alcança esse percentual do
 * `final_total`. Desligada, nada muda.
 *
 * O cliente pode ter a condição própria (`contacts.sinal_minimo_pct`, P-025): preenchida, vale no
 * lugar da da empresa, e 0 dispensa o sinal daquele cliente. Vazia, vale a da empresa.
 *
 * Só LÊ valor: nada é gravado em dinheiro. A conta é feita no MySQL em DECIMAL (sem float):
 * exigido = ROUND(final_total × % / 100, 2); pago = soma das entradas − troco.
 * Tier 0: venda e pagamentos sempre filtrados pelo `$businessId`.
 */
final class SinalDaOrdem
{
    public function __construct(private readonly RegrasDaEmpresa $regras) {}

    /**
     * Percentual exigido, ou nulo sem sinal. Com a venda, vale a condição do cliente dela quando
     * existe (0 = cliente dispensado); sem condição própria, a da empresa. Tier 0: venda e cliente
     * lidos pela `$businessId`.
     */
    public function minimo(int $businessId, ?int $transactionId = null): ?string
    {
        $doCliente = $transactionId === null ? null : $this->doCliente($businessId, $transactionId);
        if ($doCliente !== null) {
            return (float) $doCliente > 0 ? $doCliente : null;
        }

        return $this->regras->sinalMinimo($businessId);
    }

    /** Condição própria do cliente da venda (texto decimal), ou nula. */
    private function doCliente(int $businessId, int $transactionId): ?string
    {
        if (! Schema::hasColumn('contacts', 'sinal_minimo_pct')) {
            return null;
        }
        $v = DB::table('transactions as t')
            ->join('contacts as c', function ($j) {
                $j->on('c.id', '=', 't.contact_id')->on('c.business_id', '=', 't.business_id');
            })
            ->where('t.id', $transactionId)->where('t.business_id', $businessId)
            ->value('c.sinal_minimo_pct');

        return $v === null ? null : (string) $v;
    }

    /**
     * Pago × exigido da venda, em texto decimal. Venda de outra empresa (ou inexistente) = não pago.
     *
     * @return array{pago: string, exigido: string, ok: bool}
     */
    public function situacao(int $businessId, int $transactionId, string $pct): array
    {
        $linha = DB::selectOne(
            'SELECT ROUND(x.pago, 2) AS pago, x.exigido, (x.pago >= x.exigido) AS ok FROM (
                SELECT COALESCE((SELECT SUM(IF(tp.is_return = 0, tp.amount, -tp.amount)) FROM transaction_payments tp
                        WHERE tp.transaction_id = t.id AND tp.business_id = t.business_id), CAST(0 AS DECIMAL(22,4))) AS pago,
                    ROUND(t.final_total * CAST(? AS DECIMAL(5,2)) / 100, 2) AS exigido
                FROM transactions t WHERE t.id = ? AND t.business_id = ?) x',
            [$pct, $transactionId, $businessId],
        );
        if ($linha === null) {
            return ['pago' => '0.00', 'exigido' => '—', 'ok' => false];
        }

        return ['pago' => (string) $linha->pago, 'exigido' => (string) $linha->exigido, 'ok' => (bool) $linha->ok];
    }

    /** Recusa o Liberar quando a regra está ligada e o sinal da venda da OS não chegou ao mínimo. */
    public function conferir(int $businessId, Ordem $ordem): void
    {
        $pct = $this->minimo($businessId, (int) $ordem->transaction_id);
        if ($pct === null) {
            return;
        }
        $s = $this->situacao($businessId, (int) $ordem->transaction_id, $pct);
        if (! $s['ok']) {
            throw new ApontamentoRecusado(ApontamentoRecusado::SINAL_PENDENTE,
                "A empresa só libera a produção com {$this->br($pct)}% da venda pago: exigido {$this->br($s['exigido'])}, pago {$this->br($s['pago'])}.",
                ['ordem_id' => $ordem->id, 'pago' => $s['pago'], 'exigido' => $s['exigido']]);
        }
    }

    private function br(string $v): string
    {
        return str_replace('.', ',', $v);
    }
}
