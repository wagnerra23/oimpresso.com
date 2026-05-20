<?php

namespace Modules\Financeiro\Services;

use App\Transaction;
use App\TransactionPayment;
use App\Util\OtelHelper;
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
        return OtelHelper::spanBiz('financeiro.titulo_auto.sincronizar', function () use ($tx): ?Titulo {
            return $this->sincronizarDeTransacaoInternal($tx);
        }, [
            'transaction_id' => $tx->id,
            'transaction_type' => $tx->type,
            'business_id' => $tx->business_id,
            'payment_status' => $tx->payment_status,
        ]);
    }

    private function sincronizarDeTransacaoInternal(Transaction $tx): ?Titulo
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
            // SUPERADMIN: Observer Transaction sem session — business_id deduzido da Transaction recebida
            // Procura existente pra preservar `numero` (idempotente).
            $existing = Titulo::query()
                ->withoutGlobalScope(BusinessScopeImpl::class)
                ->where('business_id', $tx->business_id)
                ->where('origem', $origem)
                ->where('origem_id', $tx->id)
                ->whereNull('parcela_numero')
                ->first();

            $numero = $existing?->numero ?? $this->proximoNumero($tx->business_id, $tipo);

            // Onda Edit 2026-05-18 — cross-link auto-pop em `cliente_descricao`.
            // Formato: "{ContactName} · #V-{txId}" (venda) ou "{ContactName} · #PC-{txId}" (compra).
            // FinCrossLinkify (frontend) parseia esses tokens e renderiza pills clicáveis
            // que roteiam pro Sells/Compras de origem. Preserva edit manual do user:
            // se $existing já tem cliente_descricao preenchido, NÃO sobrescreve.
            $cliente = $tx->contact_id ? \App\Contact::find($tx->contact_id) : null;
            $contactName = $cliente?->name ?: ($cliente?->supplier_business_name ?: null);
            $crossLinkPrefix = $tipo === 'receber' ? 'V' : 'PC';
            $crossLink = sprintf('#%s-%d', $crossLinkPrefix, $tx->id);
            $descricaoSugerida = $contactName ? "{$contactName} · {$crossLink}" : $crossLink;
            $clienteDescricaoFinal = ($existing && ! empty($existing->cliente_descricao))
                ? $existing->cliente_descricao
                : $descricaoSugerida;

            // SUPERADMIN: idem Observer — updateOrCreate com business_id da Transaction
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
                        'cliente_descricao' => $clienteDescricaoFinal,
                        'valor_total' => $valorTotal,
                        'valor_aberto' => $valorAberto,
                        'moeda' => 'BRL',
                        'emissao' => Carbon::parse($tx->transaction_date, config('app.timezone'))->toDateString(),
                        'vencimento' => $this->calcularVencimento($tx),
                        'competencia_mes' => Carbon::parse($tx->transaction_date, config('app.timezone'))->format('Y-m'),
                        'observacoes' => $tx->additional_notes,
                        'created_by' => $tx->created_by,
                        'metadata' => [
                            'auto_created' => true,
                            'transaction_invoice_no' => $tx->invoice_no,
                            'cross_link' => $crossLink,
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

        // SUPERADMIN: Observer Transaction cancelarSeExistir — business_id da Transaction recebida
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
            // SUPERADMIN: Observer TransactionPayment sem session — business_id da Transaction associada
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
                // SUPERADMIN: re-fetch com lock pelo ID recém-criado — Observer sem session
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

            // ADR 0175 (2026-05-20) — conta_bancaria_id agora é nullable em fin_titulo_baixas
            // e fin_caixa_movimentos. Bridge dispara mesmo sem fin_contas_bancarias cadastrada
            // (cenário PME que opera só PIX/dinheiro — caso real Larissa biz=4 ROTA LIVRE).
            // UI mostra pill "conta indefinida" + CTA "vincular conta agora".
            // Reconciliação pós-cadastro: `php artisan financeiro:vincular-baixas-sem-conta {biz} {conta_id}`.
            $contaBancaria = $this->resolverContaBancaria($tx->business_id, $tp->account_id);

            if (! $contaBancaria) {
                // D7.a Wave 14 — log informativo via FinanceiroAuditLogger (PII redacted).
                // NÃO é mais no-op — apenas registra que baixa será criada sem conta vinculada.
                app(FinanceiroAuditLogger::class)->info(
                    'TituloAutoService.registrarPagamento: baixa sem conta — biz sem fin_contas_bancarias (ADR 0175)',
                    [
                        'business_id' => $tx->business_id,
                        'tp_id' => $tp->id,
                        'tx_id' => $tx->id,
                        'invoice_no' => $tx->invoice_no,
                    ]
                );
            }

            $valor = (float) $tp->amount;

            // SUPERADMIN: Observer TransactionPayment registrarPagamento — INSERT TituloBaixa com business_id da Transaction
            $baixa = TituloBaixa::query()
                ->withoutGlobalScope(BusinessScopeImpl::class)
                ->create([
                    'business_id' => $tx->business_id,
                    'titulo_id' => $titulo->id,
                    'conta_bancaria_id' => $contaBancaria?->id,
                    'valor_baixa' => $valor,
                    'data_baixa' => $tp->paid_on
                        ? Carbon::parse($tp->paid_on, config('app.timezone'))->toDateString()
                        : Carbon::now(config('app.timezone'))->toDateString(),
                    'meio_pagamento' => $this->mapearMeioPagamento($tp->method),
                    'idempotency_key' => $this->idempotencyKeyParaBaixa($tx->business_id, $tp),
                    'transaction_payment_id' => $tp->id,
                    'observacoes' => $tp->note ?: null,
                    'created_by' => $tp->created_by ?? $tx->created_by ?? 1,
                ]);

            // Recalcula valor_aberto a partir das baixas líquidas (criado - estornado).
            $this->recalcularTitulo($titulo);

            // SUPERADMIN: Observer TransactionPayment — INSERT CaixaMovimento com business_id da Transaction
            // Cria CaixaMovimento — entrada se receber (venda paga), saída se pagar (compra paga).
            // ADR 0175: conta_bancaria_id pode ser null se biz não tem fin_contas_bancarias cadastrada.
            CaixaMovimento::query()
                ->withoutGlobalScope(BusinessScopeImpl::class)
                ->create([
                    'business_id' => $tx->business_id,
                    'conta_bancaria_id' => $contaBancaria?->id,
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

            // SUPERADMIN: Observer TransactionPayment cancelarPagamento — busca Titulo pelo id da baixa original (biz da baixa)
            $titulo = Titulo::query()
                ->withoutGlobalScope(BusinessScopeImpl::class)
                ->where('id', $original->titulo_id)
                ->lockForUpdate()
                ->first();

            // SUPERADMIN: idem cancelarPagamento — INSERT estorno com business_id da baixa original
            // Cria baixa de estorno (valor negativo + estorno_de_id apontando).
            $estorno = TituloBaixa::query()
                ->withoutGlobalScope(BusinessScopeImpl::class)
                ->create([
                    'business_id' => $original->business_id,
                    'titulo_id' => $original->titulo_id,
                    'conta_bancaria_id' => $original->conta_bancaria_id,
                    'valor_baixa' => -1 * (float) $original->valor_baixa,
                    'data_baixa' => Carbon::now(config('app.timezone'))->toDateString(),
                    'meio_pagamento' => $original->meio_pagamento,
                    'idempotency_key' => $this->idempotencyKeyParaEstorno((int) $tp->business_id, $tp, $original),
                    'transaction_payment_id' => $tp->id,
                    'estorno_de_id' => $original->id,
                    'observacoes' => 'Estorno automático: TransactionPayment removido/zerado',
                    'created_by' => $tp->created_by ?? 1,
                ]);

            // Recalcula valor_aberto do título.
            $this->recalcularTitulo($titulo);

            // SUPERADMIN: cancelarPagamento — busca movimento original pelo origem_id da baixa (biz preservado pela FK)
            // Lança CaixaMovimento oposto pra reverter o ledger.
            $movimentoOriginal = CaixaMovimento::query()
                ->withoutGlobalScope(BusinessScopeImpl::class)
                ->where('origem_tipo', 'titulo_baixa')
                ->where('origem_id', $original->id)
                ->first();

            if ($movimentoOriginal) {
                // SUPERADMIN: idem — INSERT estorno CaixaMovimento com business_id do movimento original
                CaixaMovimento::query()
                    ->withoutGlobalScope(BusinessScopeImpl::class)
                    ->create([
                        'business_id' => $movimentoOriginal->business_id,
                        'conta_bancaria_id' => $movimentoOriginal->conta_bancaria_id,
                        'tipo' => 'ajuste',
                        'valor' => $movimentoOriginal->valor,
                        'data' => Carbon::now(config('app.timezone'))->toDateString(),
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
        // SUPERADMIN: Observer/Job sem session — soma baixas pelo titulo_id (biz preservado pela FK Titulo)
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
            return Carbon::parse($tx->due_date, config('app.timezone'))->toDateString();
        }

        $base = Carbon::parse($tx->transaction_date, config('app.timezone'));

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

        // SUPERADMIN: Observer/Job sem session — sequencial business-isolado, business_id explícito como param
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
     *
     * Retorna null se o business ainda não cadastrou nenhuma conta no Financeiro.
     * Caller decide como degradar (registrarPagamento faz no-op gracioso).
     *
     * fix BUG-2 (2026-05-08): antes lançava DomainException, o que bloqueava o
     * save do TransactionPayment no UltimatePOS core (ROTA LIVRE biz=4 sem
     * fin_contas_bancarias). Financeiro é módulo opcional — não pode quebrar
     * fluxo core de Sells/Purchases.
     */
    private function resolverContaBancaria(int $businessId, ?int $accountId): ?ContaBancaria
    {
        if ($accountId) {
            // SUPERADMIN: Observer/Job sem session — busca conta por account_id (UltimatePOS) + business_id explícito
            $conta = ContaBancaria::query()
                ->withoutGlobalScope(BusinessScopeImpl::class)
                ->where('business_id', $businessId)
                ->where('account_id', $accountId)
                ->first();

            if ($conta) {
                return $conta;
            }
        }

        // SUPERADMIN: fallback conta ativa pra boleto — sem session, business_id explícito
        $conta = ContaBancaria::query()
            ->withoutGlobalScope(BusinessScopeImpl::class)
            ->where('business_id', $businessId)
            ->where('ativo_para_boleto', true)
            ->orderBy('id')
            ->first();

        if ($conta) {
            return $conta;
        }

        // SUPERADMIN: último recurso — primeira conta do business sem filtro boleto
        return ContaBancaria::query()
            ->withoutGlobalScope(BusinessScopeImpl::class)
            ->where('business_id', $businessId)
            ->orderBy('id')
            ->first();
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
        // SUPERADMIN: Observer/Job sem session — busca baixa ativa por transaction_payment_id + business_id explícito
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

        // SUPERADMIN: Observer/Job sem session — checa idempotência por transaction_payment_id + business_id explícito
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

        // SUPERADMIN: idem — conta versões prévias pra gerar sufixo _v<N>
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
