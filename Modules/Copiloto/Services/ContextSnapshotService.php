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

        $faturamento90d = DB::table('transactions')
            ->when($businessId, fn ($q) => $q->where('business_id', $businessId))
            ->where('type', 'sell')
            ->where('status', 'final')
            ->where('transaction_date', '>=', now()->subDays(90))
            ->selectRaw("DATE_FORMAT(transaction_date, '%Y-%m') as mes, SUM(final_total) as valor")
            ->groupBy('mes')
            ->orderBy('mes')
            ->get()
            ->map(fn ($r) => ['mes' => $r->mes, 'valor' => (float) $r->valor])
            ->all();

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
