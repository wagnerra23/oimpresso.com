<?php

namespace Modules\Copiloto\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\Copiloto\Support\ContextoNegocio;

/**
 * ContextSnapshotService — coleta dados atuais do negócio pra alimentar a IA.
 *
 * STUB: queries principais montadas; pode e deve ser otimizado/evoluído
 * conforme novos módulos precisem virar parte do contexto.
 */
class ContextSnapshotService
{
    public function paraBusiness(?int $businessId): ContextoNegocio
    {
        $ttl = (int) config('copiloto.context_cache_ttl_minutes', 10);

        return Cache::remember(
            "copiloto.contexto.business_{$businessId}",
            $ttl * 60,
            fn () => $this->montar($businessId),
        );
    }

    protected function montar(?int $businessId): ContextoNegocio
    {
        $businessName = $businessId
            ? (DB::table('business')->where('id', $businessId)->value('name') ?? 'Business')
            : 'oimpresso (plataforma)';

        $faturamento90d = $this->faturamento90d($businessId);

        $clientesAtivos = $businessId
            ? (int) DB::table('contacts')->where('business_id', $businessId)->where('type', 'customer')->count()
            : (int) DB::table('business')->count();

        return new ContextoNegocio(
            businessId:      $businessId,
            businessName:    $businessName,
            faturamento90d:  $faturamento90d,
            clientesAtivos:  $clientesAtivos,
            modulosAtivos:   [], // TODO: ler de modules_statuses.json
            metasAtivas:     $this->metasAtivas($businessId),
            observacoes:     null,
        );
    }

    /**
     * MEM-FAT-1 (29-abr) — Faturamento 90d agregado por mês com 3 ângulos
     * distintos pra LLM responder corretamente "vendi"/"líquido"/"caixa":
     *
     *   bruto    = SUM(sell.final.final_total)             — o que foi vendido
     *   liquido  = bruto - SUM(sell_return.final.final_total) — descontando devoluções
     *   caixa    = SUM(transaction_payments.amount no mês descontando estornos) — o que entrou
     *
     * Caixa usa `transaction_payments.paid_on` (data real do pagamento), não
     * `transactions.transaction_date` — venda de Mar com pagamento Abr conta
     * em Abr no caixa, conforme regime de caixa.
     *
     * Compatibilidade: campo `valor` é mantido como alias do `bruto` pra
     * código legado (ex: BriefingAgent::montarPromptBriefing).
     *
     * @return array<array{mes: string, valor: float, bruto: float, liquido: float, caixa: float}>
     */
    protected function faturamento90d(?int $businessId): array
    {
        $cutoff = now()->subDays(90)->startOfDay();

        // 1. Bruto (sell + sell_return) agregado por mês via single query
        //    com SUM condicional pra pegar os 2 sinais de uma vez.
        $vendasPorMes = DB::table('transactions')
            ->when($businessId, fn ($q) => $q->where('business_id', $businessId))
            ->whereIn('type', ['sell', 'sell_return'])
            ->where('status', 'final')
            ->where('transaction_date', '>=', $cutoff)
            ->selectRaw(
                "DATE_FORMAT(transaction_date, '%Y-%m') as mes,
                 SUM(CASE WHEN type='sell' THEN final_total ELSE 0 END) as bruto,
                 SUM(CASE WHEN type='sell_return' THEN final_total ELSE 0 END) as devolucoes"
            )
            ->groupBy('mes')
            ->orderBy('mes')
            ->get()
            ->keyBy('mes');

        // 2. Caixa entrado por mês via paid_on de transaction_payments,
        //    descontando estornos (is_return=1) — só vendas (não compra/expense).
        $caixaPorMes = DB::table('transaction_payments as tp')
            ->join('transactions as t', 't.id', '=', 'tp.transaction_id')
            ->when($businessId, fn ($q) => $q->where('t.business_id', $businessId))
            ->whereIn('t.type', ['sell', 'sell_return'])
            ->where('tp.paid_on', '>=', $cutoff)
            ->selectRaw(
                "DATE_FORMAT(tp.paid_on, '%Y-%m') as mes,
                 SUM(CASE WHEN tp.is_return=1 THEN -tp.amount ELSE tp.amount END) as caixa"
            )
            ->groupBy('mes')
            ->orderBy('mes')
            ->get()
            ->keyBy('mes');

        // 3. Merge dos meses (alguns podem ter venda e zero caixa, ou vice-versa)
        $meses = collect($vendasPorMes->keys())
            ->merge($caixaPorMes->keys())
            ->unique()
            ->sort()
            ->values();

        return $meses
            ->map(function (string $mes) use ($vendasPorMes, $caixaPorMes) {
                $vendas = $vendasPorMes->get($mes);
                $bruto       = (float) ($vendas->bruto ?? 0);
                $devolucoes  = (float) ($vendas->devolucoes ?? 0);
                $liquido     = $bruto - $devolucoes;
                $caixa       = (float) ($caixaPorMes->get($mes)->caixa ?? 0);

                return [
                    'mes'     => $mes,
                    'valor'   => $bruto, // alias legado pra BriefingAgent
                    'bruto'   => $bruto,
                    'liquido' => $liquido,
                    'caixa'   => $caixa,
                ];
            })
            ->all();
    }

    /**
     * MEM-HOT-2 (ADR 0047) — top 5 metas ativas do tenant com último realizado.
     *
     * Defensivo: se as tabelas copiloto_metas/* não existirem (ambiente novo,
     * migrations não rodadas), retorna [] silenciosamente. Não há recursão —
     * ContextoNegocio aqui só ALIMENTA o chat, não é consumido por SugestoesMetasAgent.
     *
     * @return array<array{nome: string, valor_alvo: float, realizado: float}>
     */
    protected function metasAtivas(?int $businessId): array
    {
        if ($businessId === null) {
            return [];
        }

        try {
            // Joins mínimos: meta + período corrente + última apuração desse período
            $rows = DB::table('copiloto_metas as m')
                ->where('m.ativo', true)
                ->where('m.business_id', $businessId)
                ->leftJoin('copiloto_meta_periodos as p', function ($join) {
                    $join->on('p.meta_id', '=', 'm.id')
                         ->where('p.data_inicio', '<=', now())
                         ->where('p.data_fim', '>=', now());
                })
                ->leftJoinSub(
                    DB::table('copiloto_meta_apuracoes')
                        ->select('meta_periodo_id', DB::raw('MAX(valor) as realizado'))
                        ->groupBy('meta_periodo_id'),
                    'a',
                    'a.meta_periodo_id', '=', 'p.id'
                )
                ->orderByDesc('m.id')
                ->limit(5)
                ->select('m.nome', 'p.valor_alvo', 'a.realizado')
                ->get();

            return $rows
                ->filter(fn ($r) => $r->valor_alvo !== null)
                ->map(fn ($r) => [
                    'nome'       => (string) $r->nome,
                    'valor_alvo' => (float) $r->valor_alvo,
                    'realizado'  => (float) ($r->realizado ?? 0),
                ])
                ->values()
                ->all();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::channel('copiloto-ai')->debug(
                'ContextSnapshotService::metasAtivas degradou: ' . $e->getMessage()
            );
            return [];
        }
    }
}
