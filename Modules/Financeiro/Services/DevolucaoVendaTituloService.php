<?php

namespace Modules\Financeiro\Services;

use App\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Financeiro\Models\Concerns\BusinessScopeImpl;
use Modules\Financeiro\Models\Titulo;
use Modules\Financeiro\Models\TituloBaixa;

/**
 * Devolução de venda (`transactions.type = sell_return`) abate o título a receber da venda.
 *
 * Regra decidida pelo [W] em 2026-11-03 ("Abater o título aberto"): a devolução dá baixa no
 * título da venda até o valor que ainda está em aberto. O que sobra é crédito do cliente e
 * continua onde o core já o guarda — no próprio `sell_return` em aberto (é o que o extrato do
 * cliente soma). Até aqui o Financeiro ignorava a devolução, e o título da venda devolvida
 * seguia aberto cobrando o valor inteiro (31 casos em produção, medido em 2026-11-03).
 *
 * - A baixa é `meio_pagamento = compensacao`, sem movimento de caixa: não entrou dinheiro.
 * - O que a devolução já pagou ao cliente (`transaction_payments` do sell_return) não abate:
 *   o dinheiro voltou, então o cliente segue devendo a venda.
 * - Append-only: mudar o valor da devolução estorna a baixa ativa e cria outra; excluir a
 *   devolução só estorna.
 * - Título cancelado (venda paga no caixa ou excluída) não recebe baixa.
 */
class DevolucaoVendaTituloService
{
    public const MEIO = 'compensacao';

    public function __construct(private TituloAutoService $titulos)
    {
    }

    /**
     * `transactions.type = sell_return`. Lido por getAttribute: o PHPDoc do Transaction
     * estreita `type` a 'sell'|'purchase', e com `->type` o PHPStan dá a devolução como impossível.
     */
    public static function ehDevolucao(Transaction $tx): bool
    {
        return (string) $tx->getAttribute('type') === 'sell_return';
    }

    /** Abate o título da venda pelo valor devido da devolução. Idempotente. */
    public function sincronizar(Transaction $devolucao): ?TituloBaixa
    {
        if (! self::ehDevolucao($devolucao) || empty($devolucao->return_parent_id)) {
            return null;
        }

        return DB::transaction(function () use ($devolucao) {
            $titulo = $this->tituloDaVenda($devolucao);
            $ativa = $this->baixaAtiva($devolucao);

            if (! $titulo || $titulo->status === 'cancelado') {
                if ($ativa) {
                    $this->estornar($ativa, $devolucao, 'título da venda cancelado');
                    $this->recalcular((int) $ativa->titulo_id);
                }

                return null;
            }

            $valor = $this->valorAAbater($devolucao, $titulo, $ativa);

            if ($ativa && abs((float) $ativa->valor_baixa - $valor) < 0.005) {
                return $ativa;
            }

            if ($ativa) {
                $this->estornar($ativa, $devolucao, 'devolução alterada');
            }

            $nova = null;
            if ($valor > 0) {
                // SUPERADMIN: Observer sem session — INSERT com business_id da devolução
                $nova = TituloBaixa::query()
                    ->withoutGlobalScope(BusinessScopeImpl::class)
                    ->create([
                        'business_id' => $devolucao->business_id,
                        'titulo_id' => $titulo->id,
                        'conta_bancaria_id' => null,
                        'valor_baixa' => $valor,
                        'data_baixa' => Carbon::parse($devolucao->transaction_date ?? now(), config('app.timezone'))->toDateString(),
                        'meio_pagamento' => self::MEIO,
                        'idempotency_key' => $this->chave($devolucao),
                        'transaction_payment_id' => null,
                        'observacoes' => sprintf('Devolução %s abatida da venda', $devolucao->invoice_no ?: '#'.$devolucao->id),
                        'created_by' => $devolucao->created_by ?? 1,
                    ]);
            }

            $this->titulos->recalcularTitulo($titulo->refresh());

            return $nova;
        });
    }

    /** Devolução excluída: estorna a baixa que ela fez. */
    public function estornarSeExistir(Transaction $devolucao): ?TituloBaixa
    {
        if (! self::ehDevolucao($devolucao)) {
            return null;
        }

        return DB::transaction(function () use ($devolucao) {
            $ativa = $this->baixaAtiva($devolucao);
            if (! $ativa) {
                return null;
            }

            $estorno = $this->estornar($ativa, $devolucao, 'devolução excluída');
            $this->recalcular((int) $ativa->titulo_id);

            return $estorno;
        });
    }

    /**
     * Quanto esta devolução abate: o devido da devolução (total − já pago ao cliente),
     * limitado ao aberto do título sem contar a própria baixa ativa.
     */
    public function valorAAbater(Transaction $devolucao, Titulo $titulo, ?TituloBaixa $ativa = null): float
    {
        // Pagamentos da devolução = dinheiro já devolvido ao cliente (transaction_id é da própria devolução).
        $pagoAoCliente = (float) DB::table('transaction_payments')
            ->where('transaction_id', $devolucao->id)
            ->sum('amount');

        $devido = max(0.0, (float) $devolucao->final_total - $pagoAoCliente);
        $abertoSemEsta = $this->titulos->abertoPorBaixas((int) $titulo->id, (float) $titulo->valor_total)
            + (float) ($ativa?->valor_baixa ?? 0);

        return round(max(0.0, min($devido, $abertoSemEsta)), 2);
    }

    public function tituloDaVenda(Transaction $devolucao): ?Titulo
    {
        // SUPERADMIN: Observer sem session — título da venda pelo business_id da devolução
        return Titulo::query()
            ->withoutGlobalScope(BusinessScopeImpl::class)
            ->where('business_id', $devolucao->business_id)
            ->where('origem', 'venda')
            ->where('origem_id', $devolucao->return_parent_id)
            ->whereNull('parcela_numero')
            ->lockForUpdate()
            ->first();
    }

    public function baixaAtiva(Transaction $devolucao): ?TituloBaixa
    {
        $businessId = (int) $devolucao->business_id;

        // SUPERADMIN: Observer sem session — baixa ativa da devolução pelo business_id dela
        return TituloBaixa::query()
            ->withoutGlobalScope(BusinessScopeImpl::class)
            ->where('business_id', $businessId)
            ->where('meio_pagamento', self::MEIO)
            ->whereIn('idempotency_key', $this->chavesPossiveis($devolucao))
            ->whereNull('estorno_de_id')
            ->whereNotIn('id', function ($q) use ($businessId) {
                $q->select('estorno_de_id')
                    ->from('fin_titulo_baixas')
                    ->where('business_id', $businessId)
                    ->whereNotNull('estorno_de_id');
            })
            ->first();
    }

    protected function estornar(TituloBaixa $ativa, Transaction $devolucao, string $motivo): TituloBaixa
    {
        // SUPERADMIN: Observer sem session — INSERT estorno com business_id da baixa original
        return TituloBaixa::query()
            ->withoutGlobalScope(BusinessScopeImpl::class)
            ->create([
                'business_id' => $ativa->business_id,
                'titulo_id' => $ativa->titulo_id,
                'conta_bancaria_id' => null,
                'valor_baixa' => -1 * (float) $ativa->valor_baixa,
                'data_baixa' => Carbon::now(config('app.timezone'))->toDateString(),
                'meio_pagamento' => self::MEIO,
                'idempotency_key' => 'dv_est_'.$ativa->id,
                'transaction_payment_id' => null,
                'estorno_de_id' => $ativa->id,
                'observacoes' => 'Estorno de abatimento por devolução: '.$motivo,
                'created_by' => $devolucao->created_by ?? 1,
            ]);
    }

    protected function recalcular(int $tituloId): void
    {
        // SUPERADMIN: Observer sem session — título pelo id da baixa (business preservado pela FK)
        $titulo = Titulo::query()->withoutGlobalScope(BusinessScopeImpl::class)->find($tituloId);
        if ($titulo) {
            $this->titulos->recalcularTitulo($titulo);
        }
    }

    /** Chaves `dv_{id}`, `dv_{id}_v2`… — uma por versão do abatimento desta devolução. */
    private function chavesPossiveis(Transaction $devolucao): array
    {
        $base = 'dv_'.$devolucao->id;
        $chaves = [$base];
        for ($v = 2; $v <= 50; $v++) {
            $chaves[] = $base.'_v'.$v;
        }

        return $chaves;
    }

    protected function chave(Transaction $devolucao): string
    {
        // SUPERADMIN: Observer sem session — versões anteriores pelo business_id da devolução
        $versoes = TituloBaixa::query()
            ->withoutGlobalScope(BusinessScopeImpl::class)
            ->where('business_id', $devolucao->business_id)
            ->whereIn('idempotency_key', $this->chavesPossiveis($devolucao))
            ->count();

        return $versoes === 0 ? 'dv_'.$devolucao->id : 'dv_'.$devolucao->id.'_v'.($versoes + 1);
    }
}
