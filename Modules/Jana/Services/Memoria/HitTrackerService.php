<?php

namespace Modules\Jana\Services\Memoria;

use App\Util\OtelHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * MEM-FASE6 — Rastreia uso real de fatos no chat.
 *
 * Fase 6 do ciclo de vida da memória: um fato "usado" é aquele que foi
 * retornado pelo recall E o LLM efetivamente respondeu (não foi cache hit).
 *
 * Lógica:
 *   hits_count++  e  ultimo_hit_em = now()  para cada ID de fato.
 *   Quando hits_count >= 5: promove a core_memory = true.
 *
 * core_memory = fact injetado direto no system prompt sem passar pelo recall,
 * economizando latência + tokens de embedding/search.
 *
 * Execução síncrona mas leve (1 UPDATE por lote). Falha silente — tracking
 * nunca pode quebrar o chat.
 *
 * Threshold configurável via copiloto.hits.core_memory_threshold (default 5).
 *
 * ## Multi-tenant (defesa de segurança 2026-05-06)
 *
 * `$businessId` é OBRIGATÓRIO. Todas as queries escopam por business_id pra
 * impedir cross-tenant fact incrementing — se um ID de fato vazar entre tenants
 * via bug em outro lugar (recall, cache compartilhado, etc.), o tracker NÃO
 * incrementa o contador do tenant errado. Skill `multi-tenant-patterns`.
 */
class HitTrackerService
{
    public function registrarUso(int $businessId, array $fatoIds): void
    {
        if (empty($fatoIds)) {
            return;
        }

        // D9.a (Wave 18 SATURATION) — span hit tracking memória; Tier 0 business_id explícito.
        OtelHelper::span('jana.memoria.hit_tracker', [
            'business_id' => $businessId,
            'fato_ids_count' => count($fatoIds),
        ], function () use ($businessId, $fatoIds) {
            $this->registrarUsoInternal($businessId, $fatoIds);
        });
    }

    private function registrarUsoInternal(int $businessId, array $fatoIds): void
    {
        try {
            $threshold = (int) config('copiloto.hits.core_memory_threshold', 5);
            $now = now();

            // Incrementa hits e atualiza timestamp — escopo multi-tenant
            DB::table('jana_memoria_facts')
                ->where('business_id', $businessId)
                ->whereIn('id', $fatoIds)
                ->whereNull('deleted_at')
                ->update([
                    'hits_count'    => DB::raw('hits_count + 1'),
                    'ultimo_hit_em' => $now,
                    'updated_at'    => $now,
                ]);

            // Promove a core_memory quem atingiu o threshold — escopo multi-tenant
            $promovidos = DB::table('jana_memoria_facts')
                ->where('business_id', $businessId)
                ->whereIn('id', $fatoIds)
                ->where('hits_count', '>=', $threshold)
                ->where('core_memory', false)
                ->whereNull('deleted_at')
                ->pluck('id');

            if ($promovidos->isNotEmpty()) {
                DB::table('jana_memoria_facts')
                    ->where('business_id', $businessId)
                    ->whereIn('id', $promovidos)
                    ->update(['core_memory' => true, 'updated_at' => $now]);

                Log::channel('copiloto-ai')->info('HitTracker: core_memory promovidos', [
                    'business_id' => $businessId,
                    'ids'         => $promovidos->all(),
                    'count'       => $promovidos->count(),
                ]);
            }

            Log::channel('copiloto-ai')->debug('HitTracker: hits registrados', [
                'business_id' => $businessId,
                'fato_ids'    => $fatoIds,
                'count'       => count($fatoIds),
            ]);
        } catch (\Throwable $e) {
            Log::channel('copiloto-ai')->warning('HitTracker: falhou (silente): ' . $e->getMessage());
        }
    }
}
