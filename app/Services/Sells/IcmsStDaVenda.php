<?php

declare(strict_types=1);

namespace App\Services\Sells;

use App\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * ICMS-ST cobrado no fechamento da venda (US-SELL-078 · [W] 2026-10-26).
 *
 * "O ST entra no fechamento da venda: com cliente contribuinte de outra UF, o caixa calcula o ST
 * pelo mesmo motor da NF-e, mostra no total e o cliente paga junto. O título já nasce com o ST."
 *
 * Mesma conta da nota, sem cópia: monta as linhas e chama `NfeService::montarItensNfe` com as
 * mesmas entradas da emissão da NF-e (55) — emitente do local, destinatário do cadastro do
 * cliente, alíquota interna do destino, NCM padrão. O ST devolvido é o `v_st` da nota. A
 * emissão parte de `final_total − icms_st_valor` e soma o ST de volta, então
 * venda = vNF = título.
 *
 * Só há ST na NF-e com cliente identificado de outra UF e a interna do destino cadastrada (é o
 * que o R-NFE-043 calcula hoje). Fora disso, 0. Regra fiscal que impede a nota (NCM padrão
 * ausente, CST sem ST com MVA) dá 0 aqui e a própria emissão recusa depois, com a saída dela.
 */
final class IcmsStDaVenda
{
    // Sem barra inicial: é a chave do container (app()), que precisa ser igual ao nome da classe.
    private const NFE = 'Modules\\NfeBrasil\\Services\\NfeService';

    /** ST das linhas do carrinho (antes de gravar). `itens`: variation_id, quantidade, valor_unitario. */
    public function doCarrinho(int $businessId, ?int $locationId, ?int $contactId, array $itens, float $valorSemSt, float $frete): float
    {
        $linhas = $this->linhasDoCarrinho($businessId, $itens);

        return $this->calcular($businessId, $locationId, $contactId, $linhas, $valorSemSt, $frete);
    }

    /** ST de uma venda gravada, pela base `final_total − icms_st_valor`. */
    public function daVenda(Transaction $venda): float
    {
        if (! class_exists(self::NFE)) {
            return 0.0;
        }
        $nfe = app(self::NFE);
        $linhas = $nfe->linhasDaVenda((int) $venda->business_id, (int) $venda->id);
        $base = (float) $venda->final_total - (float) ($venda->icms_st_valor ?? 0);

        return $this->calcular((int) $venda->business_id, $venda->location_id ? (int) $venda->location_id : null,
            $venda->contact_id ? (int) $venda->contact_id : null, $linhas, $base, (float) ($venda->shipping_charges ?? 0));
    }

    /**
     * No fechamento: a tela mandou `final_total` já com o ST que ela mostrou (`icms_st_valor`).
     * Recalcula pela venda gravada; divergiu mais de 1 centavo → recusa (a transação volta).
     * Igual → guarda o ST na venda.
     */
    public function conferirEGravar(Transaction $venda, string $stEnviado): void
    {
        if (! Schema::hasColumn('transactions', 'icms_st_valor')) {
            return;
        }
        $enviado = round((float) (is_numeric($stEnviado) ? $stEnviado : 0), 2);
        $venda->icms_st_valor = $enviado; // a base do cálculo é final_total − ST enviado
        $calculado = round($this->daVenda($venda), 2);

        if (abs($calculado - $enviado) > 0.01) {
            throw new IcmsStDivergenteException($calculado, $enviado);
        }
        DB::table('transactions')->where('id', $venda->id)->where('business_id', $venda->business_id)
            ->update(['icms_st_valor' => $calculado]);
        $venda->icms_st_valor = $calculado;
    }

    /** @param list<array<string,mixed>> $linhas */
    private function calcular(int $businessId, ?int $locationId, ?int $contactId, array $linhas, float $valorSemSt, float $frete): float
    {
        if (! class_exists(self::NFE) || ! $contactId || $linhas === [] || $valorSemSt <= 0) {
            return 0.0;
        }
        try {
            $nfe = app(self::NFE);
            $business = DB::table('business')->where('id', $businessId)->first();
            $contato = DB::table('contacts')->where('business_id', $businessId)->where('id', $contactId)->first();
            if ($business === null || $contato === null) {
                return 0.0;
            }
            $emitente = $nfe->resolverEmitente($business, $locationId, '55');
            $ufOrigem = $emitente['uf'];
            $dest = $nfe->destinatarioDoContato($contato, $ufOrigem);
            if ($dest === null) {
                return 0.0; // sem destinatário identificado: a NF-e não leva ST
            }
            // P-153: a mesma regra da NF-e — na mesma UF, só o contribuinte (quem revende) paga ST.
            $interna = $nfe->internaDoSt($businessId, $ufOrigem, $dest['uf'], $dest['ind_ie_dest'] === '1');
            if ($interna === null) {
                return 0.0;
            }
            // NCM padrão: a mesma leitura da emissão (NfeService::emitirParaTransaction).
            $config = \Modules\NfeBrasil\Models\NfeBusinessConfig::where('business_id', $businessId)->first();
            $ncmDefault = (string) ($config?->tributacao_default['ncm_default'] ?? $business->ncm_padrao ?? '');

            $itens = $nfe->montarItensNfe(
                linhas: $linhas, valorNota: $valorSemSt, frete: $frete, ncmDefault: $ncmDefault,
                motor: app(\Modules\NfeBrasil\Services\MotorTributarioService::class),
                businessId: $businessId, ufOrigem: $ufOrigem, ufDestino: $dest['uf'],
                internaDestino: $interna, destinatarioContribuinte: $dest['ind_ie_dest'] === '1',
                crtEmitente: $emitente['crt'] ?? null, // P-155: o regime do local que vai emitir
            );

            return (float) $itens['total']['v_st'];
        } catch (\Throwable $e) {
            Log::warning('icms_st_da_venda: sem ST (a emissão da NF-e recusa com a saída dela)', [
                'business_id' => $businessId, 'erro' => $e->getMessage(),
            ]);

            return 0.0;
        }
    }

    /**
     * Mesmos campos de `NfeService::linhasDaVenda`, a partir do carrinho. Tier 0: produto só da
     * empresa.
     *
     * @param list<array{variation_id:int|string, quantidade:float|string, valor_unitario:float|string}> $itens
     * @return list<array<string,mixed>>
     */
    private function linhasDoCarrinho(int $businessId, array $itens): array
    {
        $ids = array_values(array_unique(array_map(fn ($i) => (int) $i['variation_id'], $itens)));
        $rows = DB::table('variations as v')
            ->join('products as p', 'p.id', '=', 'v.product_id')
            ->leftJoin('units as u', 'u.id', '=', 'p.unit_id')
            ->where('p.business_id', $businessId)->whereIn('v.id', $ids)
            ->get(['v.id', 'v.product_id', 'v.sub_sku', 'p.name', 'p.sku', 'p.ncm', 'p.origem_mercadoria', 'u.short_name'])
            ->keyBy('id');

        $linhas = [];
        foreach ($itens as $i) {
            $r = $rows->get((int) $i['variation_id']);
            if ($r === null) {
                continue;
            }
            $linhas[] = [
                'sell_line_id'   => null,
                'product_id'     => (int) $r->product_id,
                'cprod'          => (string) ($r->sub_sku ?: $r->sku ?: $r->product_id),
                'xprod'          => (string) $r->name,
                'ncm'            => (string) ($r->ncm ?? ''),
                'origem'         => (int) ($r->origem_mercadoria ?? 0),
                'unidade'        => (string) ($r->short_name ?: 'UN'),
                'quantidade'     => (float) $i['quantidade'],
                'valor_unitario' => (float) $i['valor_unitario'],
            ];
        }

        return $linhas;
    }
}
