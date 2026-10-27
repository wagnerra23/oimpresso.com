<?php

declare(strict_types=1);

namespace Modules\Compras\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MRP — necessidade de compra de todas as OS e do estoque mínimo, por insumo e local (US-COM-030,
 * decisão coordenação 2026-10-26: MRP → cotação → qualidade).
 *
 * Só LÊ estoque, nunca grava. Por (local, variação) de produto que controla estoque:
 *   comprometido = reservas ativas (`stock_reservations`): a reserva de insumo das OS (US-PCP-031) e a
 *                  das vendas. A reserva não mexe no saldo, por isso entra na conta.
 *   mínimo       = `products.alert_quantity` (o estoque mínimo do núcleo, o mesmo da US-COM-027)
 *   saldo        = `variation_location_details.qty_available`
 *   a caminho    = compras ainda não recebidas (`ordered`/`pending`) + o que falta receber dos pedidos
 *                  de compra abertos (`quantity − po_quantity_purchased`)
 *   necessidade  = comprometido + mínimo − saldo − a caminho, quando positiva
 * Não recalcula a falta OS por OS (`FaltaDeMaterialDaOs` responde por OS); aqui é a soma.
 *
 * Quando pedir: `precisa_em` é o menor `pcp_ordens.prazo_prometido` das OS que reservaram o insumo
 * naquele local (sem OS, hoje); `pedir_ate` = precisa_em − prazo do fornecedor da última compra
 * (`compras_prazo_fornecedor`). Sem prazo cadastrado, `pedir_ate` = precisa_em.
 *
 * Contas em dez-milésimos inteiros (DECIMAL 4 casas), nunca float. Tier 0: tudo pelo `$businessId`.
 */
final class NecessidadeDeCompra
{
    public function __construct(private readonly RequisicaoEstoqueMinimoService $requisicao) {}

    /**
     * @return list<array{location_id:int, product_id:int, variation_id:int, nome:string, saldo:string, comprometido:string, minimo:string, a_caminho:string, necessidade:string, fornecedor_id:?int, prazo_dias:?int, precisa_em:string, pedir_ate:string, atrasado:bool}>
     */
    public function listar(int $businessId, ?Carbon $hoje = null): array
    {
        $hoje = ($hoje ?? Carbon::today())->copy()->startOfDay();

        $comprometido = $this->porChave(DB::table('stock_reservations')
            ->where('business_id', $businessId)->where('status', 'active')
            ->groupBy('location_id', 'variation_id')
            ->get(['location_id', 'variation_id', DB::raw('SUM(qty_reserved) as q')]));

        $compras = $this->porChave(DB::table('purchase_lines as pl')->join('transactions as t', 't.id', '=', 'pl.transaction_id')
            ->where('t.business_id', $businessId)->where('t.type', 'purchase')->whereIn('t.status', ['ordered', 'pending'])
            ->groupBy('t.location_id', 'pl.variation_id')
            ->get(['t.location_id', 'pl.variation_id', DB::raw('SUM(pl.quantity - pl.quantity_returned) as q')]));

        $pedidos = $this->porChave(DB::table('purchase_lines as pl')->join('transactions as t', 't.id', '=', 'pl.transaction_id')
            ->where('t.business_id', $businessId)->where('t.type', 'purchase_order')->whereIn('t.status', ['ordered', 'partial'])
            ->whereRaw('pl.quantity > pl.po_quantity_purchased')
            ->groupBy('t.location_id', 'pl.variation_id')
            ->get(['t.location_id', 'pl.variation_id', DB::raw('SUM(pl.quantity - pl.po_quantity_purchased) as q')]));

        $prazoOs = [];
        if (Schema::hasColumn('stock_reservations', 'pcp_ordem_id') && Schema::hasTable('pcp_ordens')) {
            foreach (DB::table('stock_reservations as r')->join('pcp_ordens as o', 'o.id', '=', 'r.pcp_ordem_id')
                ->where('r.business_id', $businessId)->where('o.business_id', $businessId)->where('r.status', 'active')
                ->whereNotNull('o.prazo_prometido')
                ->groupBy('r.location_id', 'r.variation_id')
                ->get(['r.location_id', 'r.variation_id', DB::raw('MIN(o.prazo_prometido) as d')]) as $r) {
                $prazoOs[$r->location_id.':'.$r->variation_id] = Carbon::parse($r->d)->startOfDay();
            }
        }

        // O universo: toda variação com saldo, reserva, mínimo ou algo a caminho na empresa.
        $chaves = array_keys($comprometido + $compras + $pedidos);
        $saldos = DB::table('variation_location_details as vld')
            ->join('products as p', 'p.id', '=', 'vld.product_id')
            ->join('business_locations as l', 'l.id', '=', 'vld.location_id')
            ->where('p.business_id', $businessId)->where('l.business_id', $businessId)
            ->where('p.enable_stock', 1)
            ->where(fn ($q) => $q->where('p.alert_quantity', '>', 0)->orWhereIn(
                DB::raw("CONCAT(vld.location_id, ':', vld.variation_id)"), $chaves ?: ['-']
            ))
            ->get(['vld.location_id', 'vld.variation_id', 'vld.product_id', 'vld.qty_available', 'p.alert_quantity', 'p.name']);

        $linhas = [];
        $vistos = [];
        foreach ($saldos as $s) {
            $k = $s->location_id.':'.$s->variation_id;
            $vistos[$k] = true;
            $linhas[] = $this->linha($businessId, $k, (int) $s->product_id, (string) $s->name, $this->dm((string) $s->qty_available),
                $this->dm((string) ($s->alert_quantity ?? '0')), $comprometido, $compras, $pedidos, $prazoOs, $hoje);
        }
        // Reserva ou compra de variação sem linha de saldo no local (nunca entrou estoque ali).
        foreach (array_diff($chaves, array_keys($vistos)) as $k) {
            [$loc, $vid] = array_map('intval', explode(':', (string) $k));
            $p = DB::table('variations as v')->join('products as p', 'p.id', '=', 'v.product_id')
                ->where('v.id', $vid)->where('p.business_id', $businessId)->where('p.enable_stock', 1)
                ->first(['p.id', 'p.name', 'p.alert_quantity']);
            if ($p === null || ! DB::table('business_locations')->where('id', $loc)->where('business_id', $businessId)->exists()) {
                continue;
            }
            $linhas[] = $this->linha($businessId, (string) $k, (int) $p->id, (string) $p->name, 0,
                $this->dm((string) ($p->alert_quantity ?? '0')), $comprometido, $compras, $pedidos, $prazoOs, $hoje);
        }

        $linhas = array_values(array_filter($linhas, fn ($l) => $l !== null));
        usort($linhas, fn ($a, $b) => [$a['pedir_ate'], $a['location_id'], $a['variation_id']] <=> [$b['pedir_ate'], $b['location_id'], $b['variation_id']]);

        return $linhas;
    }

    /** Necessidade de uma variação num local, já com a regra positiva. */
    public static function necessidade(int $comprometido, int $minimo, int $saldo, int $aCaminho): int
    {
        return max(0, $comprometido + $minimo - $saldo - $aCaminho);
    }

    private function linha(int $businessId, string $k, int $productId, string $nome, int $saldo, int $minimo, array $comprometido, array $compras, array $pedidos, array $prazoOs, Carbon $hoje): ?array
    {
        [$loc, $vid] = array_map('intval', explode(':', $k));
        $res = $comprometido[$k] ?? 0;
        $caminho = ($compras[$k] ?? 0) + ($pedidos[$k] ?? 0);
        $falta = self::necessidade($res, $minimo, $saldo, $caminho);
        if ($falta <= 0) {
            return null;
        }

        $fornecedor = $this->requisicao->ultimaCompra($businessId, $vid)?->contact_id;
        $dias = $fornecedor === null ? null : DB::table('compras_prazo_fornecedor')
            ->where('business_id', $businessId)->where('product_id', $productId)->where('contact_id', $fornecedor)->value('dias');
        $precisaEm = $prazoOs[$k] ?? $hoje;
        $pedirAte = $precisaEm->copy()->subDays((int) ($dias ?? 0));

        return [
            'location_id' => $loc, 'product_id' => $productId, 'variation_id' => $vid, 'nome' => $nome,
            'saldo' => $this->txt($saldo), 'comprometido' => $this->txt($res), 'minimo' => $this->txt($minimo),
            'a_caminho' => $this->txt($caminho), 'necessidade' => $this->txt($falta),
            'fornecedor_id' => $fornecedor !== null ? (int) $fornecedor : null,
            'prazo_dias' => $dias !== null ? (int) $dias : null,
            'precisa_em' => $precisaEm->toDateString(), 'pedir_ate' => $pedirAte->toDateString(),
            'atrasado' => $pedirAte->lt($hoje),
        ];
    }

    /** @return array<string,int> chave "local:variação" → quantidade em dez-milésimos */
    private function porChave(iterable $linhas): array
    {
        $saida = [];
        foreach ($linhas as $l) {
            $saida[$l->location_id.':'.$l->variation_id] = $this->dm((string) $l->q);
        }

        return $saida;
    }

    private function dm(string $valor): int
    {
        $valor = trim($valor) === '' ? '0' : trim($valor);
        $neg = str_starts_with($valor, '-');
        [$inteiro, $fracao] = array_pad(explode('.', ltrim($valor, '-'), 2), 2, '');
        $n = (int) $inteiro * 10000 + (int) substr(str_pad($fracao, 4, '0'), 0, 4);

        return $neg ? -$n : $n;
    }

    private function txt(int $n): string
    {
        return ($n < 0 ? '-' : '').sprintf('%d.%04d', intdiv(abs($n), 10000), abs($n) % 10000);
    }
}
