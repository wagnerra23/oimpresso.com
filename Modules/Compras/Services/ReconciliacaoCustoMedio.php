<?php

declare(strict_types=1);

namespace Modules\Compras\Services;

use Illuminate\Support\Facades\DB;

/**
 * Confere o custo médio guardado contra o histórico de movimentos (D5 da auditoria de 2026-11-05).
 *
 * O observer só registra o erro no log; este serviço acha o que ficou para trás:
 *  - compra órfã: movimento ativo de compra que não existe mais ou deixou de estar recebida
 *    (erro no observer, ou cancelada com a flag desligada). Sai da conta pelo mesmo estorno.
 *  - custo divergente: o guardado difere do último movimento ativo. Vale o movimento.
 *  - entrada perdida: compra recebida depois do início do histórico e sem movimento. Só aponta:
 *    a conta depende do estoque daquele dia, que não dá para saber agora.
 *
 * Simulação e gravação rodam o mesmo código: sem `$aplicar`, a transação de cada variação termina em
 * rollback. Só escreve em `compras_custo_medio*`.
 *
 * Tier 0 (ADR 0093): tudo filtrado pela empresa recebida.
 */
final class ReconciliacaoCustoMedio
{
    public function __construct(private readonly CustoMedioMovel $custoMedio) {}

    /** @return list<int> empresas com custo médio guardado ou histórico */
    public function empresas(): array
    {
        return DB::table('compras_custo_medio_movimentos')->select('business_id')
            ->union(DB::table('compras_custo_medio')->select('business_id'))
            ->orderBy('business_id')->pluck('business_id')->map(fn ($id) => (int) $id)->all();
    }

    /** @return list<int> */
    public function variacoes(int $biz): array
    {
        return DB::table('compras_custo_medio_movimentos')->where('business_id', $biz)->where('ativo', 1)->select('variation_id')
            ->union(DB::table('compras_custo_medio')->where('business_id', $biz)->select('variation_id'))
            ->orderBy('variation_id')->pluck('variation_id')->map(fn ($id) => (int) $id)->all();
    }

    /** @return array<string, mixed>|null a diferença encontrada; nulo se a variação está certa */
    public function variacao(int $biz, int $variationId, bool $aplicar): ?array
    {
        DB::beginTransaction();
        try {
            $resultado = $this->conferir($biz, $variationId);
            $aplicar ? DB::commit() : DB::rollBack();

            return $resultado;
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /** @return array<string, mixed>|null */
    private function conferir(int $biz, int $variationId): ?array
    {
        $antes = $this->guardado($biz, $variationId);

        $orfas = $this->comprasOrfas($biz, $variationId);
        foreach ($orfas as $transactionId) {
            $this->custoMedio->estornarVariacao($biz, $variationId, $transactionId);
        }

        $guardado = $this->guardado($biz, $variationId);
        $ultimo = DB::table('compras_custo_medio_movimentos')
            ->where('business_id', $biz)->where('variation_id', $variationId)->where('ativo', 1)
            ->orderByDesc('id')->value('custo_depois');

        $corrigiu = false;
        if ($ultimo === null) {
            $motivo = $guardado !== null ? 'custo sem histórico (não alterado)' : null;
        } else {
            $corrigiu = $guardado === null || abs($guardado - (float) $ultimo) >= 0.00005;
            if ($corrigiu && $guardado === null) {
                DB::table('compras_custo_medio')->insert([
                    'business_id' => $biz, 'variation_id' => $variationId, 'custo_medio' => $ultimo,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            } elseif ($corrigiu) {
                DB::table('compras_custo_medio')->where('business_id', $biz)->where('variation_id', $variationId)
                    ->update(['custo_medio' => $ultimo, 'updated_at' => now()]);
            }
            $motivo = null;
        }

        $depois = $this->guardado($biz, $variationId);
        if ($orfas === [] && ! $corrigiu && $motivo === null) {
            return null;
        }

        return [
            'variation_id' => $variationId, 'antes' => $antes, 'depois' => $depois,
            'motivo' => $motivo ?? ($orfas !== [] ? 'compra órfã estornada: '.implode(', ', $orfas) : 'guardado difere do histórico'),
            'orfas' => count($orfas),
        ];
    }

    /** Compras recebidas depois do início do histórico da variação, sem movimento ativo. Só leitura. */
    public function entradasPerdidas(int $biz): array
    {
        $inicio = DB::table('compras_custo_medio_movimentos')->where('business_id', $biz)->where('ativo', 1)
            ->groupBy('variation_id')->selectRaw('variation_id, MIN(registrado_em) as primeiro');

        return DB::table('purchase_lines as pl')
            ->join('transactions as t', 't.id', '=', 'pl.transaction_id')
            ->joinSub($inicio, 'h', 'h.variation_id', '=', 'pl.variation_id')
            ->leftJoin('compras_custo_medio_movimentos as m', function ($j) use ($biz) {
                $j->on('m.transaction_id', '=', 't.id')->on('m.variation_id', '=', 'pl.variation_id')
                    ->where('m.business_id', $biz)->where('m.ativo', 1);
            })
            ->where('t.business_id', $biz)->where('t.type', 'purchase')->where('t.status', 'received')
            ->whereNull('m.id')
            ->whereColumn('t.updated_at', '>=', 'h.primeiro')
            ->groupBy('t.id', 'pl.variation_id')
            ->havingRaw('SUM(pl.quantity) > 0')
            ->orderBy('t.id')
            ->selectRaw('t.id as transaction_id, pl.variation_id, SUM(pl.quantity) as qtd')
            ->get()->map(fn ($l) => (array) $l)->all();
    }

    /** @return list<int> */
    private function comprasOrfas(int $biz, int $variationId): array
    {
        return DB::table('compras_custo_medio_movimentos as m')
            ->leftJoin('transactions as t', function ($j) use ($biz) {
                $j->on('t.id', '=', 'm.transaction_id')->where('t.business_id', $biz);
            })
            ->where('m.business_id', $biz)->where('m.variation_id', $variationId)->where('m.ativo', 1)
            ->where('m.origem', 'entrada')
            ->where(fn ($q) => $q->whereNull('t.id')->orWhere('t.type', '!=', 'purchase')->orWhere('t.status', '!=', 'received'))
            ->orderBy('m.id')->pluck('m.transaction_id')->map(fn ($id) => (int) $id)->all();
    }

    private function guardado(int $biz, int $variationId): ?float
    {
        $v = DB::table('compras_custo_medio')->where('business_id', $biz)->where('variation_id', $variationId)
            ->lockForUpdate()->value('custo_medio');

        return $v !== null ? (float) $v : null;
    }
}
