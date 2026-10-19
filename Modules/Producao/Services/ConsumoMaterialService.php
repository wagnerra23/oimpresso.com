<?php

declare(strict_types=1);

namespace Modules\Producao\Services;

use App\Exceptions\PurchaseSellMismatch;
use App\Scopes\ScopeByBusiness;
use App\Transaction;
use App\TransactionSellLine;
use App\Utils\ProductUtil;
use App\Utils\TransactionUtil;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Producao\Entities\Consumo;
use Modules\Producao\Entities\Ordem;
use Modules\Producao\Entities\Programacao;
use Modules\Producao\Exceptions\ApontamentoRecusado;

/**
 * Material consumido na etapa da OS por item (UC-FLX-37 · US-PCP-021) — mexe em ESTOQUE e em VALOR
 * (regra mestre, proibicoes.md). Decisão [W] 2026-10-19: "a OS baixa do estoque o material apontado
 * em cada etapa, pelo custo do lote que saiu."
 *
 * Como baixa: cada apontamento é uma transação `production_sell` (ref `OS-<id>`) no local da venda que
 * gerou a OS, com uma linha só (o core já a reconhece no histórico de estoque). Uma por baixa, e não
 * uma por OS, porque `transaction_sell_lines` tem `uk_tsl_dup_prevent (transaction_id, product_id,
 * variation_id)`: a 2ª baixa do mesmo material na mesma transação estoura. O `mapPurchaseSell`
 * (`production_purchase`) amarra a linha ao lote pelo método da empresa (FIFO/LIFO) e soma em
 * `purchase_lines.mfg_quantity_used`; o `decreaseProductQuantity` tira do saldo do local.
 *
 * Custo: Σ (quantidade que saiu de cada lote × `purchase_lines.purchase_price_inc_tax`), arredondado
 * a 4 casas pelo MySQL (DECIMAL exato), guardado em `pcp_consumos.custo`. Compra posterior não muda.
 * NÃO é o `dpp_inc_tax` do dia, que o Manufacturing usa: esse é o último preço, não o lote.
 * Empresa que permite vender sem estoque: o que faltou sai sem lote, sem custo, e fica em
 * `quantidade_sem_lote` para ninguém confundir com custo zero.
 *
 * Quem aponta: o responsável da etapa, com a etapa iniciada ou feita e a OS Em execução ou Pausada.
 * OS concluída tem o custo congelado: material depois disso é recusado. Tier 0: `$businessId` em
 * toda consulta (o app não tem sessão).
 */
final class ConsumoMaterialService
{
    public const SITUACOES = ['em_execucao', 'pausada'];

    public function __construct(private readonly ProductUtil $productUtil, private readonly TransactionUtil $transactionUtil) {}

    public function registrar(int $businessId, int $userId, int $programacaoId, int $variationId, string $quantidade, string $chave, CarbonInterface $momento): Consumo
    {
        $quantidade = $this->quantidade($quantidade);
        $momento = Carbon::instance($momento)->setTimezone(config('app.timezone'))->startOfSecond();

        if ($repetido = $this->repeticao($businessId, $chave, $programacaoId, $variationId, $quantidade)) {
            return $repetido;
        }

        $etapa = Programacao::query()->where('business_id', $businessId)->where('os_tipo', Ordem::OS_TIPO)->find($programacaoId)
            ?? throw new ApontamentoRecusado(ApontamentoRecusado::NAO_ENCONTRADO, 'Etapa não encontrada.');
        if ((int) $etapa->user_id !== $userId) {
            throw new ApontamentoRecusado(ApontamentoRecusado::ETAPA_ALHEIA, 'Esta etapa está com outro responsável.',
                ['programacao_id' => $etapa->id, 'posto_id' => $etapa->posto_id, 'user_id' => $etapa->user_id]);
        }
        if (! in_array($etapa->status, ['iniciada', 'concluida'], true)) {
            throw new ApontamentoRecusado(ApontamentoRecusado::ETAPA_ENCERRADA, $etapa->status === 'pendente'
                ? 'Inicie a etapa antes de apontar o material.' : 'Esta etapa foi cancelada.');
        }

        $material = DB::table('variations as v')->join('products as p', 'p.id', '=', 'v.product_id')
            ->where('p.business_id', $businessId)->where('v.id', $variationId)->whereNull('v.deleted_at')
            ->first(['v.id', 'v.product_id', 'p.enable_stock']);
        if (! $material) {
            throw new ApontamentoRecusado(ApontamentoRecusado::MATERIAL_INVALIDO, 'Material não encontrado.');
        }
        if ((int) $material->enable_stock !== 1) {
            throw new ApontamentoRecusado(ApontamentoRecusado::MATERIAL_INVALIDO, 'Este produto não controla estoque.');
        }

        try {
            return DB::transaction(fn () => $this->baixar($businessId, $userId, $etapa, $material, $quantidade, $chave, $momento));
        } catch (PurchaseSellMismatch $e) {
            throw new ApontamentoRecusado(ApontamentoRecusado::ESTOQUE_INSUFICIENTE, 'Não há estoque deste material no local da venda.');
        }
    }

    /** Material baixado na OS, por linha (o que a Ficha mostra e o custo soma). */
    public function daOrdem(int $businessId, int $ordemId): \Illuminate\Support\Collection
    {
        return Consumo::query()->where('business_id', $businessId)->where('ordem_id', $ordemId)->orderBy('momento')->orderBy('id')->get();
    }

    private function baixar(int $businessId, int $userId, Programacao $etapa, object $material, string $quantidade, string $chave, Carbon $momento): Consumo
    {
        // Trava a OS: uma baixa não passa pela conclusão que congela o custo.
        $ordem = Ordem::withoutGlobalScope(ScopeByBusiness::class)->where('business_id', $businessId)->with('currentStage')
            ->lockForUpdate()->find((int) $etapa->os_id)
            ?? throw new ApontamentoRecusado(ApontamentoRecusado::NAO_ENCONTRADO, 'OS não encontrada.');
        $situacao = $ordem->currentStage?->key;
        if (! in_array($situacao, self::SITUACOES, true)) {
            throw new ApontamentoRecusado(ApontamentoRecusado::OS_NAO_LIBERADA, match ($situacao) {
                'concluida' => 'Esta OS já foi concluída: o custo está congelado.',
                'cancelada' => 'Esta OS foi cancelada.',
                default => 'Esta OS não está em produção.',
            }, ['programacao_id' => $etapa->id, 'situacao' => $situacao]);
        }

        $localId = DB::table('transactions')->where('business_id', $businessId)->where('id', $ordem->transaction_id)->value('location_id')
            ?? throw new ApontamentoRecusado(ApontamentoRecusado::NAO_ENCONTRADO, 'A venda desta OS não tem local de estoque.');

        $saida = Transaction::create([
            'business_id' => $businessId, 'location_id' => (int) $localId, 'type' => 'production_sell', 'status' => 'final',
            'payment_status' => 'due', 'transaction_date' => $momento, 'ref_no' => 'OS-'.$ordem->id,
            'created_by' => $userId, 'final_total' => 0, 'total_before_tax' => 0, 'essentials_duration' => 0,
        ]);
        $linha = TransactionSellLine::create([
            'transaction_id' => $saida->id, 'product_id' => (int) $material->product_id, 'variation_id' => (int) $material->id,
            'quantity' => $quantidade, 'unit_price_before_discount' => 0, 'unit_price' => 0, 'unit_price_inc_tax' => 0,
            'item_tax' => 0, 'line_discount_amount' => 0,
        ]);

        $empresa = DB::table('business')->where('id', $businessId)->first(['accounting_method', 'pos_settings']);
        $this->transactionUtil->mapPurchaseSell([
            'id' => $businessId, 'location_id' => (int) $localId,
            'accounting_method' => $empresa?->accounting_method ?? 'fifo', 'pos_settings' => $empresa?->pos_settings,
        ], [$linha], 'production_purchase', false);
        $this->productUtil->decreaseProductQuantity((int) $material->product_id, (int) $material->id, (int) $localId, $quantidade);

        $lote = DB::table('transaction_sell_lines_purchase_lines as m')
            ->leftJoin('purchase_lines as pl', 'pl.id', '=', 'm.purchase_line_id')
            ->where('m.sell_line_id', $linha->id)
            ->selectRaw('ROUND(COALESCE(SUM(CASE WHEN m.purchase_line_id > 0 THEN m.quantity * pl.purchase_price_inc_tax ELSE 0 END), 0), 4) as custo')
            ->selectRaw('COALESCE(SUM(CASE WHEN m.purchase_line_id = 0 THEN m.quantity ELSE 0 END), 0) as sem_lote')
            ->first();
        $custo = $this->quatroCasas((string) $lote->custo);
        $semLote = $this->quatroCasas((string) $lote->sem_lote);

        // Preço unitário da linha = custo do lote ÷ quantidade, para o histórico de estoque mostrar o valor real.
        $unitario = $this->quatroCasas((string) DB::selectOne('SELECT ROUND(? / ?, 4) AS u', [$custo, $quantidade])->u);
        DB::table('transaction_sell_lines')->where('id', $linha->id)
            ->update(['unit_price_before_discount' => $unitario, 'unit_price' => $unitario, 'unit_price_inc_tax' => $unitario]);
        DB::table('transactions')->where('business_id', $businessId)->where('id', $saida->id)
            ->update(['final_total' => $custo, 'total_before_tax' => $custo]);

        return Consumo::query()->create([
            'business_id' => $businessId, 'chave' => $chave, 'ordem_id' => $ordem->id, 'programacao_id' => $etapa->id,
            'product_id' => (int) $material->product_id, 'variation_id' => (int) $material->id, 'quantidade' => $quantidade,
            'quantidade_sem_lote' => $semLote, 'custo' => $custo,
            'transaction_id' => $saida->id, 'transaction_sell_line_id' => $linha->id, 'user_id' => $userId, 'momento' => $momento,
        ]);
    }

    /** Mesma chave e mesmo corpo devolve o consumo gravado; corpo diferente é conflito. */
    private function repeticao(int $businessId, string $chave, int $programacaoId, int $variationId, string $quantidade): ?Consumo
    {
        $linha = Consumo::query()->where('business_id', $businessId)->where('chave', $chave)->first();
        if (! $linha) {
            return null;
        }
        $igual = (int) $linha->programacao_id === $programacaoId && (int) $linha->variation_id === $variationId
            && $this->quatroCasas((string) $linha->quantidade) === $quantidade;

        return $igual ? $linha : throw new ApontamentoRecusado(ApontamentoRecusado::IDEMPOTENCIA_CONFLITO, 'Esta chave já foi usada em outro apontamento.');
    }

    /** Decimal do MySQL com 4 casas, sem passar por float (regra mestre de valor). */
    private function quatroCasas(string $valor): string
    {
        [$inteiro, $fracao] = array_pad(explode('.', trim($valor) === '' ? '0' : trim($valor), 2), 2, '');

        return ($inteiro === '' ? '0' : $inteiro).'.'.substr(str_pad($fracao, 4, '0'), 0, 4);
    }

    /** Quantidade positiva com até 4 casas, ponto decimal (o app e a PWA mandam assim). Volta com 4 casas. */
    private function quantidade(string $quantidade): string
    {
        $q = trim($quantidade);
        if (! preg_match('/^\d{1,14}(\.\d{1,4})?$/', $q)) {
            throw new ApontamentoRecusado(ApontamentoRecusado::QUANTIDADE_INVALIDA, 'Informe a quantidade com ponto e até 4 casas (ex.: 2.5).');
        }
        [$inteiro, $fracao] = array_pad(explode('.', $q, 2), 2, '');
        $normal = ltrim($inteiro, '0').'.'.str_pad($fracao, 4, '0');
        $normal = str_starts_with($normal, '.') ? '0'.$normal : $normal;
        if ($normal === '0.0000') {
            throw new ApontamentoRecusado(ApontamentoRecusado::QUANTIDADE_INVALIDA, 'A quantidade tem de ser maior que zero.');
        }

        return $normal;
    }
}
