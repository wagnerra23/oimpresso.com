<?php

declare(strict_types=1);

namespace Modules\Producao\Services;

use App\Contracts\Fabricacao\InsumosDaReceita;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Producao\Entities\Ordem;

/**
 * Falta de material da OS por item (D3 da proposta 2026-10-09, aprovada por [W] em 2026-10-13; pedida
 * para a Produção na revisão de 2026-10-21). A OS nasce mesmo faltando material; o que se bloqueia é
 * COMEÇAR. Liberada sem material, ela fica em "Aguardando material", com a lista do que falta e as
 * compras que já estão a caminho.
 *
 * Só LÊ estoque, nunca grava (regra mestre: nada aqui muda saldo nem reserva). Por insumo da receita
 * do produto (`App\Contracts\Fabricacao\InsumosDaReceita`, a mesma conta da reserva US-PCP-031):
 *   necessário = receita por unidade × quantidade da OS
 *   disponível = saldo do insumo no local da venda − reservas ativas de OUTRAS OS e vendas nesse local
 *                (a reserva da própria OS não conta contra ela)
 *   falta      = necessário − disponível, quando positivo
 * A caminho = compras do insumo no mesmo local ainda não recebidas (`ordered`/`pending`).
 *
 * Chave por empresa em `business.common_settings['producao_aguarda_material']`, DESLIGADA por padrão:
 * ninguém muda no deploy. Contas em dez-milésimos inteiros (DECIMAL 4 casas), nunca float.
 * Tier 0: tudo pelo `$businessId` recebido; o app e os jobs não têm sessão.
 */
final class FaltaDeMaterialDaOs
{
    public const CHAVE = 'producao_aguarda_material';

    public function ligada(int $businessId): bool
    {
        $ajustes = json_decode((string) DB::table('business')->where('id', $businessId)->value('common_settings'), true);

        return is_array($ajustes) && ! empty($ajustes[self::CHAVE]);
    }

    /**
     * Insumos que faltam para a OS. Vazio = tem tudo, ou a OS não tem receita de insumo que controla
     * estoque, ou a Fabricação não está instalada.
     *
     * @return list<array{product_id:int, variation_id:int, nome:string, necessario:string, disponivel:string, falta:string, a_caminho:string, compras:list<array{id:int, ref_no:?string, status:string, quantidade:string}>}>
     */
    public function daOrdem(int $businessId, Ordem $ordem): array
    {
        if (! app()->bound(InsumosDaReceita::class) || $ordem->variation_id === null) {
            return [];
        }
        $local = DB::table('transactions')->where('business_id', $businessId)->where('id', $ordem->transaction_id)->value('location_id');
        if ($local === null) {
            return [];
        }

        $faltas = [];
        foreach (app(InsumosDaReceita::class)->paraQuantidade($businessId, (int) $ordem->variation_id, (string) $ordem->quantidade) as $insumo) {
            $necessario = $this->dm($insumo['quantidade']);
            if ($necessario <= 0) {
                continue;
            }
            $vid = (int) $insumo['variation_id'];
            // variation_location_details não tem business_id: o insumo é da empresa pela receita (Tier 0 no contrato).
            $saldo = $this->dm((string) DB::table('variation_location_details')->where('variation_id', $vid)
                ->where('location_id', (int) $local)->value('qty_available'));
            $reservas = DB::table('stock_reservations')->where('business_id', $businessId)
                ->where('variation_id', $vid)->where('location_id', (int) $local)->where('status', 'active');
            if ($this->temOrdemNaReserva()) {
                $reservas->where(fn ($q) => $q->whereNull('pcp_ordem_id')->orWhere('pcp_ordem_id', '!=', $ordem->id));
            }
            $reservadoPorOutros = $this->dm((string) $reservas->selectRaw('COALESCE(SUM(qty_reserved), 0) as q')->value('q'));
            $disponivel = max(0, $saldo - $reservadoPorOutros);
            if ($disponivel >= $necessario) {
                continue;
            }

            $compras = DB::table('transactions as t')->join('purchase_lines as pl', 'pl.transaction_id', '=', 't.id')
                ->where('t.business_id', $businessId)->where('t.type', 'purchase')->whereIn('t.status', ['ordered', 'pending'])
                ->where('t.location_id', (int) $local)->where('pl.variation_id', $vid)
                ->groupBy('t.id', 't.ref_no', 't.status', 't.transaction_date')->orderBy('t.transaction_date')
                ->get(['t.id', 't.ref_no', 't.status', DB::raw('SUM(pl.quantity - pl.quantity_returned) as quantidade')]);
            $aCaminho = 0;
            $lista = [];
            foreach ($compras as $c) {
                $q = $this->dm((string) $c->quantidade);
                $aCaminho += $q;
                $lista[] = ['id' => (int) $c->id, 'ref_no' => $c->ref_no, 'status' => (string) $c->status, 'quantidade' => $this->txt($q)];
            }

            $faltas[] = [
                'product_id' => (int) $insumo['product_id'], 'variation_id' => $vid,
                'nome' => (string) DB::table('products')->where('business_id', $businessId)->where('id', $insumo['product_id'])->value('name'),
                'necessario' => $this->txt($necessario), 'disponivel' => $this->txt($disponivel), 'falta' => $this->txt($necessario - $disponivel),
                'a_caminho' => $this->txt($aCaminho), 'compras' => $lista,
            ];
        }

        return $faltas;
    }

    private static ?bool $temColuna = null;

    private function temOrdemNaReserva(): bool
    {
        return self::$temColuna ??= Schema::hasColumn('stock_reservations', 'pcp_ordem_id');
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
