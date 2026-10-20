<?php

declare(strict_types=1);

namespace Modules\Producao\Services;

use App\Contracts\Compras\CustoDoInsumo;
use App\Domain\Fsm\Modelos\RegrasCompra;
use App\Domain\Fsm\Modelos\RegrasDaEmpresa;
use App\Scopes\ScopeByBusiness;
use Illuminate\Support\Facades\DB;
use Modules\Producao\Entities\Ordem;
use Modules\Producao\Exceptions\ApontamentoRecusado;

/**
 * OS em andamento avisa o insumo que mudou de custo (UC-FLX-36 · US-PCP-022) — só LEITURA de VALOR.
 *
 * A regra da compra "Atualizar o custo dos insumos" (`RegrasCompra::CUSTO`) tem o parâmetro `ops`:
 *   - `congela` — a OS aberta mantém o custo da abertura: a previsão é o custo do insumo na data em
 *                 que a OS nasceu; o de hoje aparece ao lado, só como aviso.
 *   - `vivo`    — a previsão passa a usar o custo de hoje.
 * Regra desligada: nada propaga, vale como `congela`.
 *
 * Os insumos da OS são o material apontado nas etapas (US-PCP-021). Para cada um:
 *   abertura = quantidade × custo do insumo na data de abertura da OS (método da empresa)
 *   hoje     = quantidade × custo do insumo hoje
 *   lote     = custo do lote que de fato saiu (o que congela ao concluir — UC-FLX-37)
 * Nenhum dos dois modos escreve nada: o custo congelado da OS concluída é sempre o do lote mais as
 * horas, e esta previsão não o reescreve. OS concluída devolve só o congelado.
 *
 * O custo do insumo vem pela interface `App\Contracts\Compras\CustoDoInsumo` (módulo Compras).
 * Contas em DECIMAL do MySQL e somas em décimos de milésimo inteiros, sem float. Tier 0: tudo por
 * `$businessId`.
 */
final class PrevisaoCustoDaOrdem
{
    public function __construct(private readonly RegrasDaEmpresa $regras) {}

    /** `congela` ou `vivo`, da regra de custo dos insumos gravada pela empresa. */
    public function regraOps(int $businessId): string
    {
        foreach ($this->regras->compra($businessId) as $regra) {
            if ($regra['id'] === RegrasCompra::CUSTO) {
                return $regra['ativo'] && ($regra['params']['ops'] ?? null) === 'vivo' ? 'vivo' : 'congela';
            }
        }

        return 'congela';
    }

    /** @return array<string, mixed> */
    public function daOrdem(int $businessId, int $ordemId): array
    {
        $ordem = Ordem::withoutGlobalScope(ScopeByBusiness::class)->where('business_id', $businessId)->with('currentStage')->find($ordemId)
            ?? throw new ApontamentoRecusado(ApontamentoRecusado::NAO_ENCONTRADO, 'OS não encontrada.');
        $situacao = $ordem->currentStage?->key;
        $base = ['ordem_id' => (int) $ordem->id, 'situacao' => $situacao, 'aberta_em' => $ordem->created_at?->toDateString(),
            'congelado' => $ordem->custo_real !== null ? $this->quatro((string) $ordem->custo_real) : null];

        if (in_array($situacao, ['concluida', 'cancelada'], true)) {
            return $base + ['regra' => null, 'itens' => [], 'previsto' => null];
        }

        $regra = $this->regraOps($businessId);
        $custo = app()->bound(CustoDoInsumo::class) ? app(CustoDoInsumo::class) : null;
        $abertura = $ordem->created_at?->toDateString() ?? now()->toDateString();
        $hoje = now()->toDateString();

        $grupos = DB::table('pcp_consumos')->where('business_id', $businessId)->where('ordem_id', $ordem->id)
            ->groupBy('variation_id')->orderBy('variation_id')
            ->selectRaw('variation_id, SUM(quantidade) as quantidade, SUM(custo) as lote')->get();

        $itens = [];
        $tot = ['abertura' => 0, 'hoje' => 0, 'lote' => 0];
        $incompleto = $custo === null;
        foreach ($grupos as $g) {
            $qtd = $this->quatro((string) $g->quantidade);
            $unAbertura = $custo?->naData($businessId, (int) $g->variation_id, $abertura)['custo'];
            $unHoje = $custo?->naData($businessId, (int) $g->variation_id, $hoje)['custo'];
            $item = [
                'variation_id' => (int) $g->variation_id,
                'quantidade' => $qtd,
                'custo_unit_abertura' => $unAbertura,
                'custo_unit_hoje' => $unHoje,
                'abertura' => $unAbertura !== null ? $this->vezes($qtd, $unAbertura) : null,
                'hoje' => $unHoje !== null ? $this->vezes($qtd, $unHoje) : null,
                'lote' => $this->quatro((string) $g->lote),
            ];
            $item['mudou'] = $item['abertura'] !== null && $item['hoje'] !== null && $item['abertura'] !== $item['hoje'];
            $incompleto = $incompleto || $item['abertura'] === null || $item['hoje'] === null;
            foreach (['abertura', 'hoje', 'lote'] as $k) {
                $tot[$k] += $item[$k] !== null ? $this->dezMilesimos($item[$k]) : 0;
            }
            $itens[] = $item;
        }

        $totais = array_map(fn (int $n) => $this->texto($n), $tot);

        return $base + [
            'regra' => $regra,
            'itens' => $itens,
            'total_abertura' => $totais['abertura'],
            'total_hoje' => $totais['hoje'],
            'total_lote' => $totais['lote'],
            'previsto' => $regra === 'vivo' ? $totais['hoje'] : $totais['abertura'],
            'itens_mudaram' => count(array_filter($itens, fn ($i) => $i['mudou'])),
            // Algum insumo sem compra recebida até a data (ou sem o módulo Compras): o total não fecha.
            'incompleto' => $incompleto,
        ];
    }

    private function vezes(string $qtd, string $unitario): string
    {
        return $this->quatro((string) DB::selectOne('SELECT ROUND(? * ?, 4) AS v', [$qtd, $unitario])->v);
    }

    private function quatro(string $valor): string
    {
        [$inteiro, $fracao] = array_pad(explode('.', trim($valor) === '' ? '0' : trim($valor), 2), 2, '');

        return ($inteiro === '' ? '0' : $inteiro).'.'.substr(str_pad($fracao, 4, '0'), 0, 4);
    }

    private function dezMilesimos(string $valor): int
    {
        [$inteiro, $fracao] = explode('.', $this->quatro($valor), 2);

        return (int) $inteiro * 10000 + (int) $fracao;
    }

    private function texto(int $n): string
    {
        return sprintf('%d.%04d', intdiv($n, 10000), $n % 10000);
    }
}
