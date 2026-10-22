<?php

declare(strict_types=1);

namespace Modules\Compras\Services;

use App\Contracts\Compras\PropostasDePreco as Contrato;
use App\Domain\Fsm\Modelos\RegrasCompra;
use App\Domain\Fsm\Modelos\RegrasDaEmpresa;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Aprovar ou recusar o preço proposto pelo recebimento da compra (US-COM-028 · UC-FLX-17).
 *
 * A proposta nasce em AplicarCustoDoRecebimento quando a regra da empresa está em "propor": o custo
 * já mudou, o preço ficou igual e a margem caiu. Aqui alguém decide:
 *
 *  aprovar — preço com imposto = `preco_proposto`;
 *            preço sem imposto = preço com imposto × 100 / (100 + imposto do produto);
 *            margem = (preço sem imposto − custo sem imposto atual) / custo × 100.
 *            É o mesmo resultado do `aplicar` na hora do recebimento (a margem volta à de antes).
 *            Arredondamento: 4 casas, como as colunas de `variations`.
 *  recusar — nada muda na variação.
 *
 * Só se aprova se o preço da variação ainda é o `preco_antes` da proposta. Se alguém mudou o preço
 * depois (no produto ou aprovando outra proposta da mesma variação), aprovar sobrescreveria essa
 * mudança — então recusa a aprovação e pede para recusar a proposta.
 *
 * Tier 0 (ADR 0093): proposta e variação só da empresa (`business_id` da proposta e
 * `products.business_id`). Proposta de outra empresa = não encontrada.
 */
final class PropostasDePreco implements Contrato
{
    public function __construct(private readonly RegrasDaEmpresa $regras) {}

    public function daCompra(int $businessId, int $transactionId): array
    {
        $regra = $this->regras->custoGravado($businessId);
        $params = $regra['params'] ?? null;
        $aviso = null;
        if ($regra !== null && $regra['ativo'] && ($params['etapa'] ?? null) === 'conferido') {
            $aviso = 'A regra "Atualizar o custo dos insumos" dispara em Conferido, etapa que a compra ainda não tem. '
                .'Por isso o recebimento não atualiza custo nem preço. Para valer, mude a etapa para Recebido em Fluxos › Empresa.';
        }
        $aprovacao = $params['aprovacao'] ?? null;
        if ($aprovacao !== null && $aprovacao !== 'recebimento') {
            $onde = RegrasCompra::OPCOES['aprovacao'][1][$aprovacao] ?? $aprovacao;
            $aviso = trim(($aviso ?? '').' A aprovação está configurada em "'.$onde.'", que ainda não tem a fila; aprove aqui enquanto isso.');
        }

        $itens = DB::table('compras_propostas_preco as cp')
            ->join('variations as v', 'v.id', '=', 'cp.variation_id')
            ->join('products as p', 'p.id', '=', 'v.product_id')
            ->where('cp.business_id', $businessId)
            ->where('p.business_id', $businessId)
            ->where('cp.transaction_id', $transactionId)
            ->orderBy('cp.id')
            ->get([
                'cp.id', 'cp.status', 'cp.custo_antes', 'cp.custo_depois', 'cp.preco_antes', 'cp.preco_proposto', 'cp.decided_at',
                'p.name as produto', 'p.type as tipo', 'v.name as variacao', 'v.sell_price_inc_tax as preco_atual',
            ])
            ->map(fn ($r) => [
                'id' => (int) $r->id,
                'status' => (string) $r->status,
                'produto' => (string) $r->produto,
                'variacao' => $r->tipo === 'variable' ? (string) $r->variacao : null,
                'custo_antes' => (float) $r->custo_antes,
                'custo_depois' => (float) $r->custo_depois,
                'preco_antes' => (float) $r->preco_antes,
                'preco_proposto' => (float) $r->preco_proposto,
                'preco_atual' => (float) $r->preco_atual,
                'preco_mudou' => $r->status === 'pendente' && abs((float) $r->preco_atual - (float) $r->preco_antes) >= 0.0001,
                'decided_at' => $r->decided_at,
            ])
            ->values()
            ->all();

        return ['aprovacao' => $aprovacao, 'aviso' => $aviso, 'itens' => $itens];
    }

    /**
     * Propostas pendentes por produto, para a consulta de produto (UC-PIDX-28). Mesmo critério de
     * empresa do `daCompra`: proposta, produto e compra da empresa. Só lê; aprovar e recusar
     * continuam no detalhe da compra.
     */
    public function pendentesDosProdutos(int $businessId, array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        $porProduto = [];
        DB::table('compras_propostas_preco as cp')
            ->join('variations as v', 'v.id', '=', 'cp.variation_id')
            ->join('products as p', 'p.id', '=', 'v.product_id')
            ->leftJoin('transactions as t', fn ($j) => $j->on('t.id', '=', 'cp.transaction_id')->where('t.business_id', $businessId))
            ->where('cp.business_id', $businessId)
            ->where('p.business_id', $businessId)
            ->where('cp.status', 'pendente')
            ->whereIn('p.id', $productIds)
            ->orderBy('cp.id')
            ->get([
                'cp.id', 'cp.transaction_id', 'cp.preco_antes', 'cp.preco_proposto', 'p.id as product_id', 'p.type as tipo',
                'v.name as variacao', 'v.sell_price_inc_tax as preco_atual', 't.ref_no', 't.transaction_date',
            ])
            ->each(function ($r) use (&$porProduto) {
                $porProduto[(int) $r->product_id][] = [
                    'id' => (int) $r->id,
                    'variacao' => $r->tipo === 'variable' ? (string) $r->variacao : null,
                    'preco_atual' => (float) $r->preco_atual,
                    'preco_proposto' => (float) $r->preco_proposto,
                    'preco_mudou' => abs((float) $r->preco_atual - (float) $r->preco_antes) >= 0.0001,
                    'compra_id' => (int) $r->transaction_id,
                    'compra_ref' => $r->ref_no === null ? null : (string) $r->ref_no,
                    'compra_data' => $r->transaction_date === null ? null : substr((string) $r->transaction_date, 0, 10),
                ];
            });

        return $porProduto;
    }

    /** @return array{variation_id:int, antes:array<string,string>, depois:array<string,string>} */
    public function aprovar(int $businessId, int $propostaId, int $userId): array
    {
        return DB::transaction(function () use ($businessId, $propostaId, $userId) {
            $p = $this->pendente($businessId, $propostaId);
            $v = DB::table('variations as v')
                ->join('products as p', 'p.id', '=', 'v.product_id')
                ->leftJoin('tax_rates as tr', 'tr.id', '=', 'p.tax')
                ->where('p.business_id', $businessId)
                ->where('v.id', $p->variation_id)
                ->lockForUpdate()
                ->select('v.id', 'v.default_purchase_price', 'v.default_sell_price', 'v.sell_price_inc_tax', 'v.profit_percent', 'tr.amount as imposto')
                ->first();
            if ($v === null) {
                throw new DomainException('O produto desta proposta não existe mais.');
            }
            if (abs((float) $v->sell_price_inc_tax - (float) $p->preco_antes) >= 0.0001) {
                throw new DomainException('O preço deste produto mudou desde a proposta. Recuse a proposta e ajuste o preço no produto.');
            }

            $imposto = (float) ($v->imposto ?? 0);
            $vendaInc = round((float) $p->preco_proposto, 4);
            $vendaExc = round($vendaInc * 100 / (100 + $imposto), 4);
            $custoExc = (float) $v->default_purchase_price;
            $margem = $custoExc > 0 ? round(($vendaExc - $custoExc) / $custoExc * 100, 4) : (float) $v->profit_percent;

            DB::table('variations')->where('id', $v->id)->update([
                'default_sell_price' => $vendaExc, 'sell_price_inc_tax' => $vendaInc, 'profit_percent' => $margem, 'updated_at' => now(),
            ]);
            $this->decidir($p->id, 'aprovada', $userId);

            $f = fn (float $n) => number_format($n, 4, '.', '');

            return [
                'variation_id' => (int) $v->id,
                'antes' => ['default_sell_price' => $f((float) $v->default_sell_price), 'sell_price_inc_tax' => $f((float) $v->sell_price_inc_tax), 'profit_percent' => $f((float) $v->profit_percent)],
                'depois' => ['default_sell_price' => $f($vendaExc), 'sell_price_inc_tax' => $f($vendaInc), 'profit_percent' => $f($margem)],
            ];
        });
    }

    public function recusar(int $businessId, int $propostaId, int $userId): void
    {
        DB::transaction(function () use ($businessId, $propostaId, $userId) {
            $this->decidir($this->pendente($businessId, $propostaId)->id, 'recusada', $userId);
        });
    }

    private function pendente(int $businessId, int $propostaId): object
    {
        $p = DB::table('compras_propostas_preco')
            ->where('business_id', $businessId)
            ->where('id', $propostaId)
            ->lockForUpdate()
            ->first();
        if ($p === null) {
            abort(404);
        }
        if ($p->status !== 'pendente') {
            throw new DomainException('Esta proposta já foi decidida.');
        }

        return $p;
    }

    private function decidir(int $id, string $status, int $userId): void
    {
        DB::table('compras_propostas_preco')->where('id', $id)->update([
            'status' => $status, 'decided_by' => $userId, 'decided_at' => now(), 'updated_at' => now(),
        ]);
    }
}
