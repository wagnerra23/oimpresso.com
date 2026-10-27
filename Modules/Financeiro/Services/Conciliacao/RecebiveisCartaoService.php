<?php

namespace Modules\Financeiro\Services\Conciliacao;

use App\Transaction;
use App\TransactionPayment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Financeiro\Models\BankStatementLine;
use Modules\Financeiro\Models\CartaoAdquirente;
use Modules\Financeiro\Models\CartaoRecebivel;
use Modules\Financeiro\Models\ConciliacaoRegra;
use Modules\Financeiro\Models\Concerns\BusinessScopeImpl;
use Modules\Financeiro\Services\FinanceiroAuditLogger;

/**
 * Cartão da maquininha (US-FIN-081 · UC-FCC-23..26).
 *
 * 1. Pagamento de venda no cartão → recebível previsto na adquirente padrão do business:
 *    bruto, taxa (débito ou crédito), líquido e data prevista (data do pagamento + prazo, em dias
 *    corridos). A taxa é calculada em centavos inteiros, arredondada meio-para-cima.
 * 2. Repasse no extrato → baixa os recebíveis: uma linha de ENTRADA cuja descrição traz o trecho
 *    da adquirente (mesmo critério de descrição/conta das regras da US-FIN-080) e cujo valor é o
 *    líquido de um recebível, ou a soma dos líquidos previstos para um mesmo dia, deixa esses
 *    recebíveis `recebido` e a linha `conciliado`.
 *
 * Nada aqui mexe em título: a venda já é baixada no pagamento (TituloAutoService). O recebível
 * registra o que falta chegar do cartão e quanto a taxa levou.
 *
 * Desligado por padrão: `financeiro.cartao.recebiveis_enabled` (FINANCEIRO_CARTAO_RECEBIVEIS_ENABLED).
 */
class RecebiveisCartaoService
{
    public function __construct(private RegrasConciliacaoService $regras)
    {
    }

    public static function ligado(): bool
    {
        return (bool) config('financeiro.cartao.recebiveis_enabled', false)
            && Schema::hasTable('fin_cartao_recebiveis');
    }

    /** Pagamento criado ou alterado → cria ou recalcula o recebível previsto. */
    public function registrarDoPagamento(TransactionPayment $tp): void
    {
        if (! self::ligado()) {
            return;
        }
        if ($tp->method !== 'card' || (float) $tp->amount <= 0 || ! $tp->transaction_id) {
            $this->cancelarDoPagamento($tp); // trocou de cartão para outro meio: o previsto sai

            return;
        }

        $businessId = (int) $tp->business_id;
        // SUPERADMIN: observer sem sessão — business_id do próprio pagamento.
        $tx = Transaction::withoutGlobalScopes()->where('business_id', $businessId)->find($tp->transaction_id);
        if (! $tx || $tx->type !== 'sell') {
            return; // cartão pago a fornecedor não é recebível
        }

        // SUPERADMIN: observer sem sessão — business_id do próprio pagamento.
        $adq = CartaoAdquirente::withoutGlobalScope(BusinessScopeImpl::class)
            ->where('business_id', $businessId)
            ->where('ativo', true)
            ->orderByDesc('padrao')
            ->orderBy('id')
            ->first();
        if (! $adq) {
            return;
        }

        $valores = self::calcular(
            (float) $tp->amount,
            CarbonImmutable::parse((string) $tp->paid_on)->toDateString(),
            $tp->card_type === 'debit' ? 'debito' : 'credito',
            $adq,
        );

        // SUPERADMIN: observer sem sessão — business_id explícito.
        $atual = CartaoRecebivel::withoutGlobalScope(BusinessScopeImpl::class)
            ->where('business_id', $businessId)
            ->where('transaction_payment_id', $tp->id)
            ->first();

        if ($atual && $atual->status === CartaoRecebivel::RECEBIDO) {
            return; // já chegou no banco: o recebível é história, não se recalcula
        }

        $dados = $valores + [
            'business_id' => $businessId, 'adquirente_id' => $adq->id, 'transaction_id' => $tx->id,
            'transaction_payment_id' => $tp->id, 'status' => CartaoRecebivel::PREVISTO,
        ];

        $atual ? $atual->fill($dados)->save() : CartaoRecebivel::create($dados);
    }

    /** Pagamento apagado → o recebível que ainda não chegou é cancelado. */
    public function cancelarDoPagamento(TransactionPayment $tp): void
    {
        if (! self::ligado()) {
            return;
        }
        // SUPERADMIN: observer sem sessão — business_id do próprio pagamento.
        CartaoRecebivel::withoutGlobalScope(BusinessScopeImpl::class)
            ->where('business_id', (int) $tp->business_id)
            ->where('transaction_payment_id', $tp->id)
            ->where('status', CartaoRecebivel::PREVISTO)
            ->get()
            ->each(fn (CartaoRecebivel $r) => $r->update(['status' => CartaoRecebivel::CANCELADO]));
    }

    /**
     * Bruto → taxa, líquido e data prevista. Centavos inteiros; taxa em milésimos de ponto
     * percentual (3,190% = 3190), arredondada meio-para-cima.
     *
     * @return array{modalidade:string,data_venda:string,data_prevista:string,valor_bruto:float,taxa_percentual:float,valor_taxa:float,valor_liquido:float}
     */
    public static function calcular(float $bruto, string $dataVenda, string $modalidade, CartaoAdquirente $adq): array
    {
        $taxa = (float) ($modalidade === 'debito' ? $adq->taxa_debito : $adq->taxa_credito);
        $prazo = (int) ($modalidade === 'debito' ? $adq->prazo_debito_dias : $adq->prazo_credito_dias);

        $brutoCent = (int) round($bruto * 100);
        $taxaMil = (int) round($taxa * 1000);
        $taxaCent = intdiv($brutoCent * $taxaMil + 50000, 100000);

        return [
            'modalidade'      => $modalidade,
            'data_venda'      => $dataVenda,
            'data_prevista'   => CarbonImmutable::parse($dataVenda)->addDays($prazo)->toDateString(),
            'valor_bruto'     => $brutoCent / 100,
            'taxa_percentual' => $taxaMil / 1000,
            'valor_taxa'      => $taxaCent / 100,
            'valor_liquido'   => ($brutoCent - $taxaCent) / 100,
        ];
    }

    /**
     * Casa os repasses do extrato com os recebíveis previstos. Roda dentro do
     * `ConciliacaoController::sugerirMatches`, depois das regras e antes do valor + data.
     *
     * @return int linhas conciliadas como repasse
     */
    public function conciliarRepasses(int $businessId): int
    {
        if (! self::ligado()) {
            return 0;
        }

        // SUPERADMIN: o motor também roda sem sessão; business do parâmetro, filtro explícito.
        $adquirentes = CartaoAdquirente::withoutGlobalScope(BusinessScopeImpl::class)
            ->where('business_id', $businessId)
            ->where('ativo', true)
            ->get();
        if ($adquirentes->isEmpty()) {
            return 0;
        }

        $janela = max(0, (int) config('financeiro.cartao.janela_repasse_dias', 3));
        $conciliadas = 0;

        foreach ($this->regras->linhasPendentes($businessId) as $linha) {
            if ($linha['valor'] <= 0) {
                continue;
            }
            foreach ($adquirentes as $adq) {
                // Mesmo critério de descrição + sentido + conta das regras (US-FIN-080).
                $criterio = new ConciliacaoRegra([
                    'descricao_contem' => $adq->descricao_extrato, 'sentido' => 'C',
                    'conta_bancaria_id' => $adq->conta_bancaria_id,
                ]);
                if (! $criterio->atende($linha['descricao'], $linha['valor'], $linha['conta_bancaria_id'])) {
                    continue;
                }

                $ids = $this->recebiveisDoRepasse($businessId, (int) $adq->id, $linha, $janela);
                if ($ids === []) {
                    continue;
                }

                $this->baixar($businessId, $linha, $ids, (int) $adq->id);
                $conciliadas++;
                break;
            }
        }

        return $conciliadas;
    }

    /**
     * Quais recebíveis previstos este repasse paga: um recebível de mesmo líquido (se for o
     * único), ou todos os previstos de um mesmo dia cuja soma dá o valor da linha (o mais perto
     * da data da linha primeiro). Sem casamento exato, nenhum.
     *
     * @return list<int>
     */
    private function recebiveisDoRepasse(int $businessId, int $adquirenteId, array $linha, int $janela): array
    {
        $data = CarbonImmutable::parse($linha['data']);
        $alvo = (int) round($linha['valor'] * 100);

        // SUPERADMIN: business do parâmetro, filtro explícito.
        $previstos = CartaoRecebivel::withoutGlobalScope(BusinessScopeImpl::class)
            ->where('business_id', $businessId)
            ->where('adquirente_id', $adquirenteId)
            ->where('status', CartaoRecebivel::PREVISTO)
            ->whereBetween('data_prevista', [$data->subDays($janela)->toDateString(), $data->addDays($janela)->toDateString()])
            ->get(['id', 'data_prevista', 'valor_liquido']);

        $cent = fn ($r) => (int) round((float) $r->valor_liquido * 100);

        $unicos = $previstos->filter(fn ($r) => $cent($r) === $alvo)->values();
        if ($unicos->count() === 1) {
            return [(int) $unicos[0]->id];
        }

        $porDia = $previstos->groupBy(fn ($r) => substr((string) $r->data_prevista, 0, 10))
            ->sortBy(fn ($g, $dia) => abs($data->diffInDays(CarbonImmutable::parse($dia))));
        foreach ($porDia as $grupo) {
            if ($grupo->count() > 1 && $grupo->sum($cent) === $alvo) {
                return $grupo->pluck('id')->map(fn ($id) => (int) $id)->all();
            }
        }

        return [];
    }

    /** @param  list<int>  $ids */
    private function baixar(int $businessId, array $linha, array $ids, int $adquirenteId): void
    {
        DB::transaction(function () use ($businessId, $linha, $ids) {
            $tabela = $linha['origem'] === 'api'
                ? DB::table('fin_extrato_lancamentos')
                // SUPERADMIN: business do parâmetro, filtro explícito logo abaixo.
                : BankStatementLine::withoutGlobalScope(BusinessScopeImpl::class)->toBase();

            $tabela->where('id', $linha['id'])
                ->where('business_id', $businessId)
                ->update([
                    'status' => 'conciliado', 'titulo_id' => null, 'match_score' => 1.00,
                    'conciliado_by' => null, 'conciliado_at' => now(), 'updated_at' => now(),
                ]);

            // SUPERADMIN: business do parâmetro, filtro explícito.
            CartaoRecebivel::withoutGlobalScope(BusinessScopeImpl::class)
                ->where('business_id', $businessId)
                ->whereIn('id', $ids)
                ->where('status', CartaoRecebivel::PREVISTO)
                ->get()
                ->each(fn (CartaoRecebivel $r) => $r->update([
                    'status' => CartaoRecebivel::RECEBIDO, 'extrato_origem' => $linha['origem'],
                    'extrato_linha_id' => $linha['id'], 'recebido_em' => $linha['data'],
                ]));
        });

        app(FinanceiroAuditLogger::class)->info('conciliacao.cartao: repasse da adquirente baixou recebíveis', [
            'business_id' => $businessId, 'line_id' => $linha['id'], 'origem' => $linha['origem'],
            'adquirente_id' => $adquirenteId, 'recebiveis' => $ids, 'status' => 'conciliado',
        ]);
    }
}
