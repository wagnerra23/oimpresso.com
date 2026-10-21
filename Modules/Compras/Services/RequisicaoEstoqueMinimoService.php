<?php

declare(strict_types=1);

namespace Modules\Compras\Services;

use App\Contracts\Compras\RequisicaoEstoqueMinimo;
use App\Utils\Util;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Estoque mínimo vira requisição de compra — US-COM-027 · UC-FLX-19.
 *
 * Para UM business (Tier 0, ADR 0093 — `$businessId` é argumento, nunca session):
 *  - lê `variation_location_details` das variações cujo produto tem `alert_quantity` (o "estoque
 *    mínimo" do núcleo, o mesmo que a tela de requisição usa em `getRequisitionProducts`) e cujo
 *    saldo no local está ABAIXO dele;
 *  - por local e fornecedor, grava UMA requisição de compra do núcleo (`transactions.type =
 *    purchase_requisition`, status `ordered` + `purchase_lines`) — ou acrescenta linhas à
 *    requisição automática ainda aberta daquele local e fornecedor;
 *  - quantidade sugerida = teto(mínimo − saldo);
 *  - fornecedor (`transactions.contact_id`) e preço da linha = os da última compra do item
 *    (`ultimaCompra`); item nunca comprado vai para a requisição sem fornecedor, com preço 0;
 *  - variação que já está numa requisição aberta (`ordered`/`partial`) do mesmo local fica de fora.
 *
 * Não cria pedido de compra e não mexe em estoque: só nasce a requisição. O preço é sugestão
 * para o comprador — a requisição não tem total e o pedido gerado dela não lê o preço da linha.
 * Liga/desliga por negócio em `business.common_settings[CHAVE]` — **desligado por padrão**.
 */
class RequisicaoEstoqueMinimoService implements RequisicaoEstoqueMinimo
{
    public const CHAVE = 'compras_requisicao_estoque_minimo';

    /** Marca a requisição automática, para acrescentar a ela em vez de abrir outra. */
    public const MARCADOR = 'Gerada pelo estoque mínimo (UC-FLX-19)';

    public const ABERTOS = ['ordered', 'partial'];

    public function ativo(int $businessId): bool
    {
        return (bool) (($this->ler($businessId)[self::CHAVE] ?? [])['ativo'] ?? false);
    }

    public function definir(int $businessId, bool $ativo): void
    {
        $settings = $this->ler($businessId);
        $settings[self::CHAVE] = ['ativo' => $ativo];

        DB::table('business')->where('id', $businessId)->update(['common_settings' => json_encode($settings)]);
    }

    /** Quantidade sugerida: teto(mínimo − saldo); zero quando não está abaixo. */
    public static function quantidadeSugerida(string|float|int $minimo, string|float|int $saldo): int
    {
        $falta = round((float) $minimo - (float) $saldo, 4);

        return $falta > 0 ? (int) ceil($falta) : 0;
    }

    /** @return array{requisicoes_criadas: int, linhas: int} */
    public function gerar(int $businessId): array
    {
        $resumo = ['requisicoes_criadas' => 0, 'linhas' => 0];
        if (! $this->ativo($businessId)) {
            return $resumo;
        }

        $abaixo = DB::table('variation_location_details as vld')
            ->join('products as p', 'p.id', '=', 'vld.product_id')
            ->join('variations as v', 'v.id', '=', 'vld.variation_id')
            ->join('business_locations as l', 'l.id', '=', 'vld.location_id')
            ->where('p.business_id', $businessId)
            ->where('l.business_id', $businessId)
            ->where('p.enable_stock', 1)
            ->where('p.is_inactive', 0)
            ->whereNull('v.deleted_at')
            ->whereNotNull('p.alert_quantity')
            ->where('p.alert_quantity', '>', 0)
            ->whereColumn('vld.qty_available', '<', 'p.alert_quantity')
            ->orderBy('vld.location_id')
            ->orderBy('vld.variation_id')
            ->get(['vld.location_id', 'vld.product_id', 'vld.variation_id', 'vld.qty_available', 'p.alert_quantity']);

        if ($abaixo->isEmpty()) {
            return $resumo;
        }

        $jaAbertas = DB::table('purchase_lines as pl')
            ->join('transactions as t', 't.id', '=', 'pl.transaction_id')
            ->where('t.business_id', $businessId)
            ->where('t.type', 'purchase_requisition')
            ->whereIn('t.status', self::ABERTOS)
            ->get(['t.location_id', 'pl.variation_id'])
            ->map(fn ($r) => $r->location_id.':'.$r->variation_id)
            ->flip();

        $donoId = (int) DB::table('business')->where('id', $businessId)->value('owner_id');
        $agora = Carbon::now();

        foreach ($abaixo->groupBy('location_id') as $localId => $linhasDoLocal) {
            $novas = [];
            foreach ($linhasDoLocal as $r) {
                $qtd = self::quantidadeSugerida($r->alert_quantity, $r->qty_available);
                if ($qtd <= 0 || $jaAbertas->has($localId.':'.$r->variation_id)) {
                    continue;
                }
                $ultima = $this->ultimaCompra($businessId, (int) $r->variation_id);
                $fornecedor = $ultima?->contact_id !== null ? (int) $ultima->contact_id : 0;
                $novas[$fornecedor][] = [
                    'product_id' => (int) $r->product_id, 'variation_id' => (int) $r->variation_id,
                    'quantity' => $qtd,
                    'purchase_price' => $ultima?->purchase_price ?? 0,
                    'purchase_price_inc_tax' => $ultima?->purchase_price_inc_tax ?? 0,
                    'item_tax' => $ultima?->item_tax ?? 0,
                    'tax_id' => $ultima?->tax_id,
                    'created_at' => $agora, 'updated_at' => $agora,
                ];
            }

            foreach ($novas as $fornecedor => $linhas) {
                $this->gravar($businessId, (int) $localId, $fornecedor === 0 ? null : $fornecedor, $linhas, $donoId, $agora, $resumo);
            }
        }

        return $resumo;
    }

    /**
     * Última compra do item: compra (`purchase`) recebida, não devolvida por inteiro — o mesmo
     * recorte do custo `ultimo` em CustoDoInsumoService. Empate de data: a linha mais nova.
     */
    public function ultimaCompra(int $businessId, int $variationId): ?object
    {
        return DB::table('purchase_lines as pl')
            ->join('transactions as t', 't.id', '=', 'pl.transaction_id')
            ->where('t.business_id', $businessId)
            ->where('t.type', 'purchase')
            ->where('t.status', 'received')
            ->where('pl.variation_id', $variationId)
            ->whereRaw('pl.quantity - pl.quantity_returned > 0')
            ->orderByDesc('t.transaction_date')
            ->orderByDesc('pl.id')
            ->first(['t.contact_id', 'pl.purchase_price', 'pl.purchase_price_inc_tax', 'pl.item_tax', 'pl.tax_id']);
    }

    /** @param array<int, array<string, mixed>> $novas */
    private function gravar(int $businessId, int $localId, ?int $fornecedorId, array $novas, int $donoId, Carbon $agora, array &$resumo): void
    {
        DB::transaction(function () use ($businessId, $localId, $fornecedorId, $novas, $donoId, $agora, &$resumo) {
            $requisicaoId = DB::table('transactions')
                ->where('business_id', $businessId)
                ->where('type', 'purchase_requisition')
                ->where('location_id', $localId)
                ->where('status', 'ordered')
                ->where('additional_notes', self::MARCADOR)
                ->when($fornecedorId === null, fn ($q) => $q->whereNull('contact_id'), fn ($q) => $q->where('contact_id', $fornecedorId))
                ->lockForUpdate()
                ->value('id');

            if ($requisicaoId === null) {
                $util = app(Util::class);
                $contagem = $util->setAndGetReferenceCount('purchase_requisition', $businessId);
                $requisicaoId = DB::table('transactions')->insertGetId([
                    'business_id' => $businessId, 'location_id' => $localId, 'contact_id' => $fornecedorId,
                    'type' => 'purchase_requisition', 'status' => 'ordered',
                    'ref_no' => $util->generateReferenceNumber('purchase_requisition', $contagem, $businessId),
                    'transaction_date' => $agora, 'additional_notes' => self::MARCADOR,
                    'created_by' => $donoId, 'essentials_duration' => 0,
                    'created_at' => $agora, 'updated_at' => $agora,
                ]);
                $resumo['requisicoes_criadas']++;
            }

            DB::table('purchase_lines')->insert(array_map(
                fn (array $l) => $l + ['transaction_id' => $requisicaoId],
                $novas
            ));
            $resumo['linhas'] += count($novas);
        });
    }

    /** @return array<string, mixed> */
    private function ler(int $businessId): array
    {
        $bruto = DB::table('business')->where('id', $businessId)->value('common_settings');
        $settings = is_string($bruto) ? json_decode($bruto, true) : null;

        return is_array($settings) ? $settings : [];
    }
}
