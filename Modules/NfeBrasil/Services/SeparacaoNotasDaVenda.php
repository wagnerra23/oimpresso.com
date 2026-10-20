<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Services;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Modules\NfeBrasil\Models\NfeNaturezaProduto;
use Modules\NfeBrasil\Services\Tributacao\NaturezaFiscalService;
use RuntimeException;

/**
 * US-NFE-069 · UC-FLX-27 / UC-FLX-31 — separa as linhas de uma venda em parte MERCADORIA (vai
 * para NF-e) e parte SERVIÇO (vai para NFS-e), repartindo desconto, frete e acréscimos.
 *
 * Só calcula. Não emite, não grava, não muda a emissão de hoje: `NfeService::recusarItensDeServico`
 * continua recusando a NF-e com item de serviço, e `NfseController::emitirParaTransaction` não chama
 * este serviço. Ligar a divisão no Faturado é outro PR.
 *
 * A natureza vem do que o contador decidiu (`nfe_naturezas_produto`, US-NFE-068): sem linha =
 * mercadoria; `pendente` também fica na mercadoria (é o que a emissão faz hoje) e volta listado
 * em `pendentes`, porque este serviço não escolhe imposto (D-NATUREZA).
 *
 * REGRA MESTRE de valor — a regra de rateio vive só em {@see self::dividir()} e tem nome em
 * {@see self::REGRA_RATEIO}. É PROPOSTA pendente de decisão [W]:
 *   1. bruto da linha = round(quantidade × unit_price_inc_tax, 2), em centavos (o mesmo vProd que
 *      `NfeService::montarItensNfe` usa); linha de valor 0 não entra (NF-e não aceita item zerado).
 *   2. frete (`shipping_charges`) vai INTEIRO para a NF-e, como o `vFrete` que a NF-e já monta.
 *      Venda sem mercadoria leva o frete para a NFS-e e marca `frete_sem_mercadoria`.
 *   3. o resto — final_total − Σ bruto − frete — é desconto (negativo) ou acréscimo (positivo:
 *      imposto da venda, despesas adicionais). É rateado proporcional ao bruto de cada parte.
 *   4. a parte SERVIÇO é arredondada a 1 centavo (meio para cima, pelo módulo); a parte MERCADORIA
 *      recebe o que sobra. Assim a soma das duas é o final_total, centavo a centavo, por construção.
 */
class SeparacaoNotasDaVenda
{
    public const REGRA_RATEIO = 'proporcional-ao-bruto · servico-arredonda · mercadoria-absorve-o-centavo · frete-na-nfe (proposta, pendente [W])';

    public const MERCADORIA = 'mercadoria';

    public const SERVICO = 'servico';

    /**
     * @return array{regra:string,final_total:float,mercadoria:array,servico:array,pendentes:list<int>,frete_sem_mercadoria:bool}
     */
    public function separar(int $businessId, int $transactionId): array
    {
        $tx = DB::table('transactions')
            ->where('business_id', $businessId)
            ->where('id', $transactionId)
            ->where('type', 'sell')
            ->first(['id', 'final_total', 'shipping_charges']);
        if ($tx === null) {
            throw (new ModelNotFoundException())->setModel('transactions', [$transactionId]);
        }

        $rows = DB::table('transaction_sell_lines as tsl')
            ->join('products as p', 'p.id', '=', 'tsl.product_id')
            ->where('tsl.transaction_id', $transactionId)
            ->where('p.business_id', $businessId) // Tier 0: produto de outro tenant não entra
            ->orderBy('tsl.id')
            ->get(['tsl.id as sell_line_id', 'tsl.product_id', 'tsl.quantity', 'tsl.unit_price_inc_tax', 'p.name']);

        $naturezas = [];
        if (NaturezaFiscalService::disponivel() && $rows->isNotEmpty()) {
            $naturezas = NfeNaturezaProduto::withoutGlobalScopes() // SUPERADMIN: roda em fila; escopo no where
                ->where('business_id', $businessId)
                ->whereIn('product_id', $rows->pluck('product_id')->map(fn ($id) => (int) $id)->all())
                ->get(['product_id', 'natureza', 'codigo_servico'])
                ->keyBy('product_id')
                ->all();
        }

        $linhas = [];
        foreach ($rows as $r) {
            $nat = $naturezas[(int) $r->product_id] ?? null;
            $linhas[] = [
                'sell_line_id'   => (int) $r->sell_line_id,
                'product_id'     => (int) $r->product_id,
                'descricao'      => (string) $r->name,
                'quantidade'     => (float) $r->quantity,
                'valor_unitario' => (float) $r->unit_price_inc_tax,
                'natureza'       => $nat !== null ? (string) $nat->natureza : self::MERCADORIA,
                'codigo_servico' => $nat !== null && $nat->codigo_servico !== null ? (string) $nat->codigo_servico : null,
            ];
        }

        return self::dividir($linhas, (float) $tx->final_total, (float) ($tx->shipping_charges ?? 0));
    }

    /**
     * A regra de rateio, sem banco. Ver o docblock da classe.
     *
     * @param list<array{natureza:string,quantidade:float,valor_unitario:float}> $linhas
     */
    public static function dividir(array $linhas, float $finalTotal, float $frete): array
    {
        $partes = [self::MERCADORIA => [], self::SERVICO => []];
        $pendentes = [];
        foreach ($linhas as $l) {
            $cent = (int) round((float) $l['quantidade'] * round((float) $l['valor_unitario'], 10) * 100);
            if ((float) $l['quantidade'] <= 0 || $cent <= 0) {
                continue;
            }
            if (($l['natureza'] ?? '') === NfeNaturezaProduto::PENDENTE && isset($l['product_id'])) {
                $pendentes[] = (int) $l['product_id'];
            }
            $lado = ($l['natureza'] ?? '') === self::SERVICO ? self::SERVICO : self::MERCADORIA;
            $partes[$lado][] = $l + ['bruto_cent' => $cent, 'bruto' => $cent / 100.0];
        }

        $brutoM = (int) array_sum(array_column($partes[self::MERCADORIA], 'bruto_cent'));
        $brutoS = (int) array_sum(array_column($partes[self::SERVICO], 'bruto_cent'));
        if ($brutoM + $brutoS === 0) {
            throw new RuntimeException('Venda sem item de valor positivo — não há o que separar.');
        }

        $totalCent = (int) round($finalTotal * 100);
        $freteCent = (int) round(max($frete, 0) * 100);
        $resto     = $totalCent - $brutoM - $brutoS - $freteCent;

        $restoS = self::fatiaArredondada($resto, $brutoS, $brutoM + $brutoS);
        $restoM = $resto - $restoS;
        $freteM = $brutoM > 0 ? $freteCent : 0;
        $freteS = $freteCent - $freteM;

        $totalM = $brutoM + $restoM + $freteM;
        $totalS = $brutoS + $restoS + $freteS;
        if ($totalM < 0 || $totalS < 0) {
            throw new RuntimeException('Desconto maior que a parte da nota — confira o total da venda.');
        }

        $parte = fn (string $lado, int $bruto, int $r, int $f, int $total) => [
            'linhas'    => $partes[$lado],
            'bruto'     => $bruto / 100.0,
            'desconto'  => max(-$r, 0) / 100.0,
            'acrescimo' => max($r, 0) / 100.0,
            'frete'     => $f / 100.0,
            'total'     => $total / 100.0,
        ];

        return [
            'regra'                => self::REGRA_RATEIO,
            'final_total'          => $totalCent / 100.0,
            self::MERCADORIA       => $parte(self::MERCADORIA, $brutoM, $restoM, $freteM, $totalM),
            self::SERVICO          => $parte(self::SERVICO, $brutoS, $restoS, $freteS, $totalS),
            'pendentes'            => array_values(array_unique($pendentes)),
            'frete_sem_mercadoria' => $freteS > 0,
        ];
    }

    /** round(valor × peso / soma) a 1 centavo, meio para cima pelo módulo, em inteiros. */
    private static function fatiaArredondada(int $valor, int $peso, int $soma): int
    {
        if ($valor === 0 || $peso === 0) {
            return 0;
        }
        $abs = intdiv(2 * abs($valor) * $peso + $soma, 2 * $soma);

        return $valor < 0 ? -$abs : $abs;
    }
}
