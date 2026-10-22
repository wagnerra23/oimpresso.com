<?php

declare(strict_types=1);

namespace Modules\Producao\Services;

use App\Contracts\Fabricacao\InsumosDaReceita;
use App\Scopes\ScopeByBusiness;
use App\Utils\TransactionUtil;
use Illuminate\Support\Facades\DB;
use Modules\Producao\Entities\Ordem;
use Modules\Producao\Entities\Programacao;

/**
 * O que o operador pode apontar como material na etapa (US-PCP-021 · UC-FLX-37 · UC-FLX-18) — só LEITURA
 * de ESTOQUE. Quem baixa é o `ConsumoMaterialService`; isto só monta a lista da tela do operador.
 *
 * Materiais da etapa = os insumos da receita do produto da OS (`InsumosDaReceita`, a mesma fonte da
 * reserva da US-PCP-031) e os que já foram apontados na OS. O roteiro não tem material próprio: a
 * receita é que diz o que o item gasta. Cada material traz o previsto pela receita (para a quantidade
 * da OS) e o já apontado.
 *
 * Rolos = os lotes do material no local da venda que gerou a OS, de compra recebida e com saldo — o
 * mesmo filtro que o `ConsumoMaterialService::conferirLote` aplica ao rolo escolhido, para a lista não
 * oferecer rolo que a baixa recusaria. Saldo pela fórmula do núcleo (`get_pl_quantity_sum_string`).
 * Ordem: o mais antigo primeiro (o que o FIFO tiraria).
 *
 * Sem custo: o operador não vê custo (é do gestor). Tier 0: tudo pelo `$businessId` recebido.
 */
final class MateriaisDaEtapa
{
    private const TIPOS_DE_ENTRADA = ['purchase', 'purchase_transfer', 'opening_stock', 'production_purchase'];

    public function __construct(private readonly TransactionUtil $transactionUtil) {}

    /**
     * @param  list<int>  $programacaoIds
     * @return array<int, list<array{variation_id:int, nome:string, unidade:?string, previsto:?string, apontado:string,
     *                                lotes: list<array{id:int, lote:?string, saldo:string, recebido_em:string}>}>>
     */
    public function paraEtapas(int $businessId, array $programacaoIds): array
    {
        $etapas = Programacao::query()->where('business_id', $businessId)->where('os_tipo', Ordem::OS_TIPO)
            ->whereIn('id', $programacaoIds)->get(['id', 'os_id']);
        $saida = [];
        foreach ($etapas as $etapa) {
            $saida[(int) $etapa->id] = $this->daOrdem($businessId, (int) $etapa->os_id);
        }

        return $saida;
    }

    private function daOrdem(int $businessId, int $ordemId): array
    {
        // Escopo global trocado por business_id explícito, como no ConsumoMaterialService (ADR 0093).
        $ordem = Ordem::withoutGlobalScope(ScopeByBusiness::class)->where('business_id', $businessId)->find($ordemId);
        if (! $ordem) {
            return [];
        }
        $localId = DB::table('transactions')->where('business_id', $businessId)->where('id', $ordem->transaction_id)->value('location_id');
        if ($localId === null) {
            return [];
        }

        $previsto = [];
        if ($ordem->variation_id !== null && app()->bound(InsumosDaReceita::class)) {
            foreach (app(InsumosDaReceita::class)->paraQuantidade($businessId, (int) $ordem->variation_id, (string) $ordem->quantidade) as $i) {
                $previsto[(int) $i['variation_id']] = (string) $i['quantidade'];
            }
        }
        $apontado = DB::table('pcp_consumos')->where('business_id', $businessId)->where('ordem_id', $ordem->id)
            ->groupBy('variation_id')->selectRaw('variation_id, SUM(quantidade) as q')->pluck('q', 'variation_id')
            ->mapWithKeys(fn ($q, $v) => [(int) $v => (string) $q])->all();

        $ids = array_values(array_unique(array_merge(array_keys($previsto), array_keys($apontado))));
        if ($ids === []) {
            return [];
        }

        $materiais = DB::table('variations as v')->join('products as p', 'p.id', '=', 'v.product_id')
            ->leftJoin('units as u', 'u.id', '=', 'p.unit_id')
            ->where('p.business_id', $businessId)->where('p.enable_stock', 1)->whereNull('v.deleted_at')->whereIn('v.id', $ids)
            ->orderBy('p.name')->orderBy('v.id')
            ->get(['v.id', 'v.name as variacao', 'p.name as produto', 'u.short_name as unidade']);

        $usado = $this->transactionUtil->get_pl_quantity_sum_string('pl');
        $lotes = DB::table('purchase_lines as pl')->join('transactions as t', 't.id', '=', 'pl.transaction_id')
            ->where('t.business_id', $businessId)->where('t.location_id', (int) $localId)->where('t.status', 'received')
            ->whereIn('t.type', self::TIPOS_DE_ENTRADA)->whereIn('pl.variation_id', $materiais->pluck('id')->all())
            ->whereRaw("(pl.quantity - ($usado)) > 0")
            ->orderBy('t.transaction_date')->orderBy('pl.id')
            ->get(['pl.id', 'pl.variation_id', 'pl.lot_number', 't.transaction_date', DB::raw("(pl.quantity - ($usado)) as saldo")])
            ->groupBy('variation_id');

        return $materiais->map(fn ($m) => [
            'variation_id' => (int) $m->id,
            'nome' => $m->variacao && $m->variacao !== 'DUMMY' ? $m->produto.' · '.$m->variacao : $m->produto,
            'unidade' => $m->unidade,
            'previsto' => $previsto[(int) $m->id] ?? null,
            'apontado' => $apontado[(int) $m->id] ?? '0.0000',
            'lotes' => ($lotes[$m->id] ?? collect())->map(fn ($l) => ['id' => (int) $l->id, 'lote' => $l->lot_number,
                'saldo' => (string) $l->saldo, 'recebido_em' => substr((string) $l->transaction_date, 0, 10)])->values()->all(),
        ])->values()->all();
    }
}
