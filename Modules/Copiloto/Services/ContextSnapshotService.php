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
            metasAtivas:     [], // TODO: ler de copiloto_metas (sem recursão se for este contexto)
            observacoes:     null,
        );
    }
}
