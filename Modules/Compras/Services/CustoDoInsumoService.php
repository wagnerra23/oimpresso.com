<?php

declare(strict_types=1);

namespace Modules\Compras\Services;

use App\Domain\Fsm\Modelos\RegrasCompra;
use App\Domain\Fsm\Modelos\RegrasDaEmpresa;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Custo do insumo numa data (UC-FLX-38 · US-COM-024).
 *
 * Responde "quanto custava esta variação no dia X" a partir das compras RECEBIDAS até
 * aquele dia. O histórico já existe no core: cada recebimento é uma linha em
 * `purchase_lines`, com o preço por unidade BASE (ProductUtil::createOrUpdatePurchaseLines
 * divide pelo multiplicador da subunidade e aplica o câmbio) e a data em
 * `transactions.transaction_date`. Nada aqui grava: o serviço só lê.
 *
 * Por que não `variations.dpp_inc_tax`: ele guarda um número só, sobrescrito pelo último
 * preço a cada compra (ProductUtil::updateProductFromPurchase). Não responde "e no dia X".
 *
 * Métodos (os da regra "Atualizar o custo dos insumos", RegrasCompra::OPCOES['metodo']):
 *  - `medio`  — média ponderada pela quantidade líquida (recebida − devolvida) de todas as
 *               compras até a data.
 *  - `ultimo` — preço da compra mais recente até a data (empate de data: a linha mais nova).
 *  - `por_insumo` — ainda não existe campo no insumo para guardar o método; devolve custo
 *               nulo com o motivo, em vez de inventar um comportamento.
 *
 * Preço usado: `purchase_price_inc_tax`, a mesma base de `dpp_inc_tax` que a receita do
 * Manufacturing soma hoje, para os dois números serem comparáveis.
 *
 * Fora da conta, de propósito:
 *  - compra não recebida (`status != received`): não entrou no estoque;
 *  - `opening_stock`, `purchase_transfer` e `production_purchase`: não são preço de compra
 *    (saldo inicial digitado, movimento interno e custo de fabricação);
 *  - linha totalmente devolvida (`quantity − quantity_returned <= 0`).
 *
 * Tier 0 (ADR 0093): sempre o `$businessId` recebido, filtrado em `transactions.business_id`.
 */
final class CustoDoInsumoService
{
    public const METODOS = ['medio', 'ultimo', 'por_insumo'];

    public function __construct(private readonly RegrasDaEmpresa $regras) {}

    /** O método da regra de custo dos insumos gravada pela empresa (padrão: `medio`). */
    public function metodoDaEmpresa(int $businessId): string
    {
        foreach ($this->regras->compra($businessId) as $regra) {
            if ($regra['id'] === RegrasCompra::CUSTO) {
                return (string) ($regra['params']['metodo'] ?? 'medio');
            }
        }

        return 'medio';
    }

    /**
     * @return array{custo: ?string, metodo: string, data: string, compras: int, ultima_compra_em: ?string, motivo: ?string}
     *         `custo` com 4 casas, por unidade base, imposto incluso; nulo quando não há
     *         compra recebida até a data ou o método não tem como responder.
     */
    public function naData(int $businessId, int $variationId, CarbonInterface|string $data, ?string $metodo = null): array
    {
        $metodo ??= $this->metodoDaEmpresa($businessId);
        if (! in_array($metodo, self::METODOS, true)) {
            throw new InvalidArgumentException("Método de custo desconhecido: {$metodo}");
        }

        $ate = Carbon::parse($data)->endOfDay();
        $resposta = ['custo' => null, 'metodo' => $metodo, 'data' => $ate->toDateString(), 'compras' => 0, 'ultima_compra_em' => null, 'motivo' => null];

        if ($metodo === 'por_insumo') {
            return ['motivo' => 'O insumo ainda não tem campo para o método próprio.'] + $resposta;
        }

        $base = DB::table('purchase_lines as pl')
            ->join('transactions as t', 't.id', '=', 'pl.transaction_id')
            ->where('t.business_id', $businessId)
            ->where('t.type', 'purchase')
            ->where('t.status', 'received')
            ->where('pl.variation_id', $variationId)
            ->where('t.transaction_date', '<=', $ate->toDateTimeString())
            ->whereRaw('pl.quantity - pl.quantity_returned > 0');

        $resumo = (clone $base)
            ->selectRaw('COUNT(*) as compras, MAX(t.transaction_date) as ultima')
            ->first();
        $compras = (int) ($resumo->compras ?? 0);
        if ($compras === 0) {
            return ['motivo' => 'Nenhuma compra recebida até a data.'] + $resposta;
        }

        $custo = $metodo === 'ultimo'
            ? (clone $base)->orderByDesc('t.transaction_date')->orderByDesc('pl.id')
                ->selectRaw('ROUND(pl.purchase_price_inc_tax, 4) as custo')->value('custo')
            : (clone $base)
                ->selectRaw('ROUND(SUM(pl.purchase_price_inc_tax * (pl.quantity - pl.quantity_returned)) / SUM(pl.quantity - pl.quantity_returned), 4) as custo')
                ->value('custo');

        return [
            'custo' => number_format((float) $custo, 4, '.', ''),
            'compras' => $compras,
            'ultima_compra_em' => Carbon::parse($resumo->ultima)->toDateString(),
        ] + $resposta;
    }
}
