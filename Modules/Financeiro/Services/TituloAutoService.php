<?php

namespace Modules\Financeiro\Services;

use App\Transaction;
use App\TransactionPayment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Financeiro\Models\CaixaMovimento;
use Modules\Financeiro\Models\Concerns\BusinessScopeImpl;
use Modules\Financeiro\Models\ContaBancaria;
use Modules\Financeiro\Models\Titulo;
use Modules\Financeiro\Models\TituloBaixa;

/**
 * Sincroniza Titulo financeiro a partir de Transaction (core UltimatePOS).
 *
 * Origens suportadas:
 *  - 'venda'   — Transaction type='sell'     + payment_status in ('due','partial')
 *  - 'compra'  — Transaction type='purchase' + payment_status in ('due','partial')
 *  - 'despesa' — Transaction type='expense'  (futuro / onda 3)
 *
 * Numeração: sequencial business-isolado com prefixo (R000001 receber / P000001 pagar),
 * gerado com lockForUpdate pra evitar dupla numeração em concorrência.
 *
 * Idempotencia: UNIQUE (business_id, origem, origem_id, parcela_numero) em
 * fin_titulos. Re-chamadas updateam, nao duplicam (TECH-0001).
 *
 * Onda 2 (2026-04-25): + suporte purchase + numeração R/P + baixa automática
 * via registrarPagamento/cancelarPagamento (TransactionPayment).
 */
class TituloAutoService
{
    /**
     * Cria ou atualiza Titulo a partir de Transaction (sell ou purchase).
     * No-op se transaction não for sell/purchase ou se já estiver paga.
     *
     * Onda 2: este é o ponto de entrada canônico (renomeado de sincronizarDeVenda).
     */
    public function sincronizarDeTransacao(Transaction $tx): ?Titulo
    {
        $tipo = match ($tx->type) {
            'sell' => 'receber',
            'purchase' => 'pagar',
            default => null,
        };

        if ($tipo === null) {
            return null;
        }

        // Transação paga em dia (status='paid') não gera título financeiro.
        if (! in_array($tx->payment_status, ['due', 'partial'], true)) {
            return $this->cancelarSeExistir($tx, motivo: 'transação quitada no caixa');
        }

        $origem = $tx->type === 'sell' ? 'venda' : 'compra';

        $valorTotal = (float) $tx->final_total;
        $valorAberto = (float) ($tx->total_remaining_amount ?? $tx->final_total);
        $valorPago = $valorTotal - $valorAberto;

        return DB::transaction(function () use ($tx, $tipo, $origem, $valorTotal, $valorAberto, $valorPago) {
            // Procura existente pra preservar `numero` (idempotente).
            $existing = Titulo::query()
                ->withoutGlobalScope(BusinessScopeImpl::class)
                ->where('business_id', $tx->business_id)
                ->where('origem', $origem)
                ->where('origem_id', $tx->id)
                ->whereNull('parcela_numero')
                ->first();

            $numero = $existing?->numero ?? $this->proximoNumero($tx->business_id, $tipo);

            return Titulo::query()
                ->withoutGlobalScope(BusinessScopeImpl::class)
                ->updateOrCreate(
                    [
                        'business_id' => $tx->business_id,
                        'origem' => $origem,
                        'origem_id' => $tx->id,
                        'parcela_numero' => null,
                    ],
                    [
                        'numero' => $numero,
                        'tipo' => $tipo,
                        'status' => $valorAberto > 0 ? ($valorPago > 0 ? 'parcial' : 'aberto') : 'quitado',
                        'cliente_id' => $tx->contact_id,
                        'valor_total' => $valorTotal,
                        'valor_aberto' => $valorAberto,
                        'moeda' => 'BRL',
                        'emissao' => Carbon::parse($tx->transaction_date)->toDateString(),
                        'vencimento' => $this->calcularVencimento($tx),
                        'competencia_mes' => Carbon::parse($tx->transaction_date)->format('Y-m'),
                        'observacoes' => $tx->additional_notes,
                        'created_by' => $tx->created_by,
                        'metadata' => [
                            'auto_created' => true,
                            'transaction_invoice_no' => $tx->invoice_no,
                        ],
                    ]
                );
        });
    }

    /**
     * Alias compatibilidade — TransactionObserver chama esse nome.
     * Cobre sell e purchase (delegação direta).
     *
     * @deprecated Use sincronizarDeTransacao(). Mantido pra não quebrar Observer + tests.
     */
    public function sincronizarDeVenda(Transaction $tx): ?Titulo
    {
        return $this->sincronizarDeTransacao($tx);
    }

    /**
     * Cancela titulo da transação se existir (nao deleta — append-only por
     * TECH-0002). No-op se nao existir.
     */
    public function cancelarSeExistir(Transaction $tx, string $motivo = ''): ?Titulo
    {
        $origem = match ($tx->type) {
            'sell' => 'venda',
            'purchase' => 'compra',
            default => null,
        };

        if ($origem === null) {
            return null;
        }

        $titulo = Titulo::query()
            ->withoutGlobalScope(BusinessScopeImpl::class)
            ->where('business_id', $tx->business_id)
            ->where('origem', $origem)
            ->where('origem_id', $tx->id)
            ->whereNull('parcela_numero')
            ->first();

        if (! $titulo) {
            return null;
        }

        $titulo->status = 'cancelado';
        $titulo->observacoes = trim(($titulo->observacoes ?? '')."\nCancelado: {$motivo}");
        $titulo->save();

        return $titulo;
    }

    /**
     * Registra pagamento (TransactionPayment) → cria TituloBaixa + CaixaMovimento.
     *
     * Idempotência: usa idempotency_key = "tp_{transaction_payment_id}". Re-chamada
     * com mesmo TransactionPayment não duplica baixa (UNIQUE business_id+idempotency_key).
     *
     * Se Titulo não existir ainda (caso edge: pagamento criado antes do auto-sync
     * processar), cria primeiro chamando sincronizarDeTransacao.
     *
     * @return TituloBaixa|null null se TransactionPayment não corresponder a sell/purchase
     */
    public function registrarPagamento(TransactionPayment $tp): ?TituloBaixa
    {
        $tx = Transaction::find($tp->transaction_id);
        if (! $tx) {
            return null;
        }

        $origem = match ($tx->type) {
            'sell' => 'venda',
            'purchase' => 'compra',
            default => null,
        };

        if ($origem === null) {
            return null;
        }

        return DB::transaction(function () use ($tp, $tx, $origem) {
            // Garante que Titulo existe (caso edge: pagamento veio antes/junto de
            // criar a transação).
            $titulo = Titulo::query()
                ->withoutGlobalScope(BusinessScopeImpl::class)
                ->where('business_id', $tx->business_id)
                ->where('origem', $origem)
                ->where('origem_id', $tx->id)
                ->whereNull('parcela_numero')
                ->lockForUpdate()
                ->first();

            if (! $titulo) {
                $titulo = $this->sincronizarDeTransacao($tx);
                if (! $titulo) {
                    return null;
                }
                // Re-fetch com lock pra atualizar valor_aberto consistentemente.
                $titulo = Titulo::query()
                    ->withoutGlobalScope(BusinessScopeImpl::class)
                    ->where('id', $titulo->id)
                    ->lockForUpdate()
                    ->first();
            }

            // Busca baixa "ativa" pra esse TP — não estornada, e ainda não estornada
            // por outra (cabeça da cadeia). Se achar ativa, é re-fire idempotente.
            $existenteAtiva = $this->baixaAtivaPorTp($tx->business_id, $tp);
            if ($existenteAtiva) {
                return $existenteAtiva;
            }

            $contaBancaria = $this->resolverContaBancaria($tx->business_id, $tp->account_id);
            $valor = (float) $tp->amount;

            $baixa = TituloBaixa::query()
                ->withoutGlobalScope(BusinessScopeImpl::class)
                ->create([
                    'business_id' => $tx->business_id,
                    'titulo_id' => $titulo->id,
                    'conta_bancaria_id' => $contaBancaria->id,
                    'valor_baixa' => $valor,
                    'data_baixa' => $tp->paid_on
                        ? Carbon::parse($tp->paid_on)->toDateString()
                        : Carbon::now()->toDateString(),
                    'meio_pagamento' => $this->mapearMeioPagamento($tp->method),
                    'idempotency_key' => $this->idempotencyKeyParaBaixa($tx->business_id, $tp),
                    'transaction_payment_id' => $tp->id,
                    'observacoes' => $tp->note ?: null,
                    'created_by' => $tp->created_by ?? $tx->created_by ?? 1,
                ]);

            // Recalcula valor_aberto a partir das baixas líquidas (criado - estornado).
            $this->recalcularTitulo($titulo);

            // Cria CaixaMovimento — entrada se receber (venda paga), saída se pagar (compra paga).
            CaixaMovimento::query()
                ->withoutGlobalScope(BusinessScopeImpl::class)
                ->create([
                    'business_id' => $tx->business_id,
                    'conta_bancaria_id' => $contaBancaria->id,
                    'tipo' => $origem === 'venda' ? 'entrada' : 'saida',
                    'valor' => $valor,
                    'data' => $baixa->data_baixa,
                    'saldo_apos' => 0, // Snapshot real é responsabilidade de service de saldo (futuro)
                    'origem_tipo' => 'titulo_baixa',
                    'origem_id' => $baixa->id,
                    'descricao' => sprintf(
                        '%s %s — %s',
                        $origem === 'venda' ? 'Recebimento' : 'Pagamento',
                        $titulo->numero,
                        $tp->method ?? 'pagamento'
                    ),
                    'metadata' => [
                        'transaction_id' => $tx->id,
                        'transaction_payment_id' => $tp->id,
                        'invoice_no' => $tx->invoice_no,
                    ],
                    'created_by' => $tp->created_by ?? $tx->created_by ?? 1,
                ]);

            return $baixa;
        });
    }

    /**
     * Reverte pagamento — cria baixa de estorno (append-only, NÃO hard delete).
     * E lança CaixaMovimento de ajuste oposto pro ledger ficar consistente.
     */
    public function cancelarPagamento(TransactionPayment $tp): ?TituloBaixa
    {
        return DB::transaction(function () use ($tp) {
            // Busca a baixa "ativa" pra esse TP — não-estorno e ainda não estornada.
            $original = $this->baixaAtivaPorTp((int) $tp->business_id, $tp);

            if (! $original) {
                return null;
            }

            $titulo = Titulo::query()
                ->withoutGlobalScope(BusinessScopeImpl::class)
                ->where('id', $original->titulo_id)
                ->lockForUpdate()
                ->first();

            // Cria baixa de estorno (valor negativo + estorno_de_id apontando).
            $estorno = TituloBaixa::query()
                ->withoutGlobalScope(BusinessScopeImpl::class)
                ->create([
                    'business_id' => $original->business_id,
                    'titulo_id' => $original->titulo_id,
                    'conta_bancaria_id' => $original->conta_bancaria_id,
                    'valor_baixa' => -1 * (float) $original->valor_baixa,
                    'data_baixa' => Carbon::now()->toDateString(),
                    'meio_pagamento' => $original->meio_pagamento,
                    'idempotency_key' => $this->idempotencyKeyParaEstorno((int) $tp->business_id, $tp, $original),
                    'transaction_payment_id' => $tp->id,
                    'estorno_de_id' => $original->id,
                    'observacoes' => 'Estorno automático: TransactionPayment removido/zerado',
                    'created_by' => $tp->created_by ?? 1,
                ]);

            // Recalcula valor_aberto do título.
            $this->recalcularTitulo($titulo);

            // Lança CaixaMovimento oposto pra reverter o ledger.
            $movimentoOriginal = CaixaMovimento::query()
                ->withoutGlobalScope(BusinessScopeImpl::class)
                ->where('origem_tipo', 'titulo_baixa')
                ->where('origem_id', $original->id)
                ->first();

            if ($movimentoOriginal) {
                CaixaMovimento::query()
                    ->withoutGlobalScope(BusinessScopeImpl::class)
                    ->create([
                        'business_id' => $movimentoOriginal->business_id,
                        'conta_bancaria_id' => $movimentoOriginal->conta_bancaria_id,
                        'tipo' => 'ajuste',
                        'valor' => $movimentoOriginal->valor,
                        'data' => Carbon::now()->toDateString(),
                        'saldo_apos' => 0,
                        'origem_tipo' => 'titulo_baixa',
                        'origem_id' => $estorno->id,
                        'descricao' => sprintf('Estorno do movimento #%d', $movimentoOriginal->id),
                        'metadata' => [
                            'estorna_movimento_id' => $movimentoOriginal->id,
                            'transaction_payment_id' => $tp->id,
                        ],
                        'created_by' => $tp->created_by ?? 1,
                    ]);
            }

            return $estorno;
        });
    }

    /**
     * Recalcula valor_aberto do Titulo a partir da soma líquida das baixas.
     * Status: aberto (= total) / parcial (entre 0 e total) / quitado (<= 0).
     *
     * NÃO atualiza status='cancelado' — esse é manual via cancelarSeExistir.
     */
    public function recalcularTitulo(Titulo $titulo): void
    {
        $somaBaixas = (float) TituloBaixa::query()
            ->withoutGlobalScope(BusinessScopeImpl::class)
            ->where('titulo_id', $titulo->id)
            ->sum('valor_baixa');

        $valorTotal = (float) $titulo->valor_total;
        $valorAberto = max(0, $valorTotal - $somaBaixas);

        if ($titulo->status === 'cancelado') {
            // Cancelado fica cancelado — só atualiza valor_aberto pra refletir baixas.
            $titulo->valor_aberto = $valorAberto;
            $titulo->save();
            return;
        }

        $titulo->valor_aberto = $valorAberto;

        if ($valorAberto <= 0) {
            $titulo->status = 'quitado';
        } elseif ($valorAberto < $valorTotal) {
            $titulo->status = 'parcial';
        } else {
            $titulo->status = 'aberto';
        }

        $titulo->save();
    }

    /**
     * Vencimento do titulo: prefere transaction.due_date; senão calcula de pay_term;
     * fallback: 30 dias após emissão.
     */
    private function calcularVencimento(Transaction $tx): string
    {
        if (! empty($tx->due_date)) {
            return Carbon::parse($tx->due_date)->toDateString();
        }

        $base = Carbon::parse($tx->transaction_date);

        if (! empty($tx->pay_term_number) && ! empty($tx->pay_term_type)) {
            return ($tx->pay_term_type === 'months'
                ? $base->copy()->addMonths((int) $tx->pay_term_number)
                : $base->copy()->addDays((int) $tx->pay_term_number)
            )->toDateString();
        }

        return $base->copy()->addDays(30)->toDateString();
    }

    /**
     * Sequencial business-isolado, prefixado (R000001 receber, P000001 pagar).
     * lockForUpdate pra evitar dupla numeração em concorrência.
     */
    private function proximoNumero(int $businessId, string $tipo): string
    {
        $prefix = $tipo === 'receber' ? 'R' : 'P';

        $ultimo = Titulo::query()
            ->withoutGlobalScope(BusinessScopeImpl::class)
            ->where('business_id', $businessId)
            ->where('tipo', $tipo)
            ->where('numero', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->value('numero');

        $seq = $ultimo ? ((int) preg_replace('/\D/', '', $ultimo)) + 1 : 1;

        return $prefix . str_pad((string) $seq, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Resolve a ContaBancaria pra usar na baixa.
     *  - Se TransactionPayment.account_id mapeia 1-1 a fin_contas_bancarias.account_id, usa.
     *  - Senão, fallback: primeira conta ativa para boleto do business.
     *  - Senão (último recurso), primeira conta bancária do business.
     */
    private function resolverContaBancaria(int $businessId, ?int $accountId): ContaBancaria
    {
        if ($accountId) {
            $conta = ContaBancaria::query()
                ->withoutGlobalScope(BusinessScopeImpl::class)
                ->where('business_id', $businessId)
                ->where('account_id', $accountId)
                ->first();

            if ($conta) {
                return $conta;
            }
        }

        $conta = ContaBancaria::query()
            ->withoutGlobalScope(BusinessScopeImpl::class)
            ->where('business_id', $businessId)
            ->where('ativo_para_boleto', true)
            ->orderBy('id')
            ->first();

        if ($conta) {
            return $conta;
        }

        $conta = ContaBancaria::query()
            ->withoutGlobalScope(BusinessScopeImpl::class)
            ->where('business_id', $businessId)
            ->orderBy('id')
            ->first();

        if (! $conta) {
            throw new \DomainException(
                "Business {$businessId} nao tem nenhuma conta bancaria cadastrada. " .
                'Cadastre uma em /financeiro/contas-bancarias antes de registrar pagamento.'
            );
        }

        return $conta;
    }

    /**
     * Mapeia transaction_payments.method (enum core UltimatePOS) →
     * fin_titulo_baixas.meio_pagamento (enum Financeiro). Default: 'outro'.
     */
    private function mapearMeioPagamento(?string $method): string
    {
        return match ($method) {
            'cash' => 'dinheiro',
            'card' => 'cartao_credito',
            'cheque' => 'cheque',
            'bank_transfer' => 'transferencia',
            'pix' => 'pix',
            default => 'outro',
        };
    }

    /**
     * Acha a baixa "ativa" pra um TransactionPayment: não é estorno (estorno_de_id null)
     * e ainda não foi estornada por outra. Cabeça da cadeia atual.
     */
    private function baixaAtivaPorTp(int $businessId, TransactionPayment $tp): ?TituloBaixa
    {
        return TituloBaixa::query()
            ->withoutGlobalScope(BusinessScopeImpl::class)
            ->where('business_id', $businessId)
            ->where('transaction_payment_id', $tp->id)
            ->whereNull('estorno_de_id')
            ->whereNotIn('id', function ($q) use ($businessId) {
                $q->select('estorno_de_id')
                    ->from('fin_titulo_baixas')
                    ->where('business_id', $businessId)
                    ->whereNotNull('estorno_de_id');
            })
            ->first();
    }

    /**
     * Idempotency key pra baixa nova vinculada a um TP. Suporta múltiplas
     * baixas pro mesmo TP (caso de updated → estorno+nova): sufixo _v<N>.
     */
    private function idempotencyKeyParaBaixa(int $businessId, TransactionPayment $tp): string
    {
        $base = 'tp_' . $tp->id;

        // Se ainda nenhuma baixa pra esse tp, usa key simples.
        $temBaixaAnterior = TituloBaixa::query()
            ->withoutGlobalScope(BusinessScopeImpl::class)
            ->where('business_id', $businessId)
            ->where('transaction_payment_id', $tp->id)
            ->whereNull('estorno_de_id')
            ->exists();

        if (! $temBaixaAnterior) {
            return $base;
        }

        // Conta versões prévias e gera sufixo.
        $versoes = TituloBaixa::query()
            ->withoutGlobalScope(BusinessScopeImpl::class)
            ->where('business_id', $businessId)
            ->where('transaction_payment_id', $tp->id)
            ->whereNull('estorno_de_id')
            ->count();

        return $base . '_v' . ($versoes + 1);
    }

    /**
     * Idempotency key pra row de estorno apontando pra uma baixa específica.
     * Garante UNIQUE mesmo se a mesma baixa for estornada em retry.
     */
    private function idempotencyKeyParaEstorno(int $businessId, TransactionPayment $tp, TituloBaixa $original): string
    {
        return 'estorno_baixa_' . $original->id;
    }
}
