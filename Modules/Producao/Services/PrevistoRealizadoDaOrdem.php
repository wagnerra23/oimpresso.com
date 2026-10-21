<?php

declare(strict_types=1);

namespace Modules\Producao\Services;

use App\Scopes\ScopeByBusiness;
use Illuminate\Support\Facades\DB;
use Modules\Producao\Entities\Ordem;
use Modules\Producao\Exceptions\ApontamentoRecusado;

/**
 * Previsto × realizado por OS (UC-FLX-15 · US-COMVIS-005) — só LEITURA de VALOR.
 *
 * Por OS por item:
 *   receita   = quantidade da linha × preço unitário da linha (sem imposto, como o custo do orçamento)
 *   orçado    = quantidade da linha × `transaction_sell_lines.custo_previsto` (o custo do dia guardado
 *               no orçamento, US-SELL-067). Linha sem custo guardado → orçado nulo.
 *   realizado = `pcp_ordens.custo_real`, congelado ao concluir (US-PCP-021). OS aberta → o parcial
 *               de hoje pelo CustoRealService, marcado "em_andamento". OS cancelada → sem realizado.
 *   diferença = realizado − orçado, e em % do orçado; margem = (receita − custo) / receita.
 * Por venda: soma das OS não canceladas; "em_andamento" se alguma ainda não concluiu.
 *
 * Nada é gravado. Contas: produtos pelo DECIMAL do MySQL (4 casas); somas, diferenças e % em
 * décimos de milésimo inteiros, % arredondado uma vez a 2 casas, meio para cima; sem float.
 * Tier 0: OS, linha e venda filtradas por `$businessId`.
 */
final class PrevistoRealizadoDaOrdem
{
    public function __construct(private readonly CustoRealService $custo) {}

    /** @return array<string, mixed> */
    public function daOrdem(int $businessId, int $ordemId): array
    {
        $ordem = Ordem::withoutGlobalScope(ScopeByBusiness::class)->where('business_id', $businessId)->with('currentStage')->find($ordemId)
            ?? throw new ApontamentoRecusado(ApontamentoRecusado::NAO_ENCONTRADO, 'OS não encontrada.');

        return $this->apresentar($this->linha($businessId, $ordem));
    }

    /** @return array<string, mixed> */
    public function daVenda(int $businessId, int $transactionId): array
    {
        $existe = DB::table('transactions')->where('business_id', $businessId)->where('id', $transactionId)->exists();
        if (! $existe) {
            throw new ApontamentoRecusado(ApontamentoRecusado::NAO_ENCONTRADO, 'Venda não encontrada.');
        }

        $ordens = Ordem::withoutGlobalScope(ScopeByBusiness::class)->where('business_id', $businessId)
            ->where('transaction_id', $transactionId)->with('currentStage')->orderBy('id')->get();

        $linhas = $ordens->map(fn (Ordem $o) => $this->linha($businessId, $o))->all();
        $soma = ['receita' => 0, 'orcado' => 0, 'realizado' => 0];
        $semOrcado = false;
        $emAndamento = false;
        foreach ($linhas as $l) {
            if ($l['situacao'] === 'cancelada') {
                continue;
            }
            $semOrcado = $semOrcado || $l['orcado'] === null;
            $emAndamento = $emAndamento || $l['status'] === 'em_andamento';
            $soma['receita'] += $l['receita'];
            $soma['orcado'] += $l['orcado'] ?? 0;
            $soma['realizado'] += $l['realizado'] ?? 0;
        }

        $total = ['status' => $emAndamento ? 'em_andamento' : 'realizado', 'situacao' => null, 'receita' => $soma['receita'],
            'orcado' => $semOrcado ? null : $soma['orcado'], 'realizado' => $soma['realizado']];

        return [
            'transaction_id' => $transactionId,
            'ordens' => array_map(fn (array $l) => $this->apresentar($l), $linhas),
            'total' => $this->apresentar($total) + ['sem_orcado' => $semOrcado],
        ];
    }

    /**
     * Valores em décimos de milésimo inteiros.
     *
     * @return array<string, mixed>
     */
    private function linha(int $businessId, Ordem $ordem): array
    {
        $sl = DB::table('transaction_sell_lines as l')->join('transactions as t', 't.id', '=', 'l.transaction_id')
            ->where('t.business_id', $businessId)->where('l.id', $ordem->transaction_sell_line_id)
            ->selectRaw('ROUND(l.quantity * l.unit_price, 4) AS receita, ROUND(l.quantity * l.custo_previsto, 4) AS orcado')
            ->first();
        $situacao = $ordem->currentStage?->key;

        if ($situacao === 'cancelada') {
            [$status, $realizado] = ['cancelada', null];
        } elseif ($situacao === 'concluida' && $ordem->custo_real !== null) {
            [$status, $realizado] = ['realizado', $this->dm((string) $ordem->custo_real)];
        } else {
            [$status, $realizado] = ['em_andamento', $this->dm($this->custo->daOs($businessId, Ordem::OS_TIPO, (int) $ordem->id)['total'])];
        }

        return ['ordem_id' => (int) $ordem->id, 'transaction_sell_line_id' => (int) $ordem->transaction_sell_line_id,
            'situacao' => $situacao, 'status' => $status,
            'receita' => $sl !== null ? $this->dm((string) $sl->receita) : 0,
            'orcado' => $sl !== null && $sl->orcado !== null ? $this->dm((string) $sl->orcado) : null,
            'realizado' => $realizado];
    }

    /**
     * @param  array<string, mixed>  $l
     * @return array<string, mixed>
     */
    private function apresentar(array $l): array
    {
        $orc = $l['orcado'];
        $real = $l['realizado'];
        $dif = $orc !== null && $real !== null ? $real - $orc : null;

        return array_diff_key($l, array_flip(['receita', 'orcado', 'realizado'])) + [
            'receita' => $this->texto($l['receita']),
            'orcado' => $orc !== null ? $this->texto($orc) : null,
            'realizado' => $real !== null ? $this->texto($real) : null,
            'diferenca' => $dif !== null ? $this->texto($dif) : null,
            'diferenca_pct' => $dif !== null ? $this->pct($dif, $orc) : null,
            'margem_orcada_pct' => $orc !== null ? $this->pct($l['receita'] - $orc, $l['receita']) : null,
            'margem_real_pct' => $real !== null ? $this->pct($l['receita'] - $real, $l['receita']) : null,
        ];
    }

    /** num / den × 100 com 2 casas, meio para cima (longe do zero); den ≤ 0 → nulo. */
    private function pct(int $num, int $den): ?string
    {
        if ($den <= 0) {
            return null;
        }
        $centesimos = intdiv(abs($num) * 20000 + $den, 2 * $den);

        return ($num < 0 && $centesimos > 0 ? '-' : '').sprintf('%d.%02d', intdiv($centesimos, 100), $centesimos % 100);
    }

    private function dm(string $valor): int
    {
        $valor = trim($valor) === '' ? '0' : trim($valor);
        $neg = str_starts_with($valor, '-');
        [$inteiro, $fracao] = array_pad(explode('.', ltrim($valor, '-'), 2), 2, '');
        $n = (int) $inteiro * 10000 + (int) substr(str_pad($fracao, 4, '0'), 0, 4);

        return $neg ? -$n : $n;
    }

    private function texto(int $n): string
    {
        return ($n < 0 ? '-' : '').sprintf('%d.%04d', intdiv(abs($n), 10000), abs($n) % 10000);
    }
}
