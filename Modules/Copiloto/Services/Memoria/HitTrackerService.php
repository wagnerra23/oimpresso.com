<?php

namespace Modules\Copiloto\Services\Memoria;

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
 */
class HitTrackerService
{
    public function registrarUso(array $fatoIds): void
    {
        if (empty($fatoIds)) {
            return;
        }

        try {
            $threshold = (int) config('copiloto.hits.core_memory_threshold', 5);
            $now = now();

            // Incrementa hits e atualiza timestamp
            DB::table('copiloto_memoria_facts')
                ->whereIn('id', $fatoIds)
                ->whereNull('deleted_at')
                ->update([
                    'hits_count'    => DB::raw('hits_count + 1'),
                    'ultimo_hit_em' => $now,
                    'updated_at'    => $now,
                ]);

            // Promove a core_memory quem atingiu o threshold
            $promovidos = DB::table('copiloto_memoria_facts')
                ->whereIn('id', $fatoIds)
                ->where('hits_count', '>=', $threshold)
                ->where('core_memory', false)
                ->whereNull('deleted_at')
                ->pluck('id');

            if ($promovidos->isNotEmpty()) {
                DB::table('copiloto_memoria_facts')
                    ->whereIn('id', $promovidos)
                    ->update(['core_memory' => true, 'updated_at' => $now]);

                Log::channel('copiloto-ai')->info('HitTracker: core_memory promovidos', [
                    'ids' => $promovidos->all(),
                    'count' => $promovidos->count(),
                ]);
            }

            Log::channel('copiloto-ai')->debug('HitTracker: hits registrados', [
                'fato_ids' => $fatoIds,
                'count'    => count($fatoIds),
            ]);
        } catch (\Throwable $e) {
            Log::channel('copiloto-ai')->warning('HitTracker: falhou (silente): ' . $e->getMessage());
        }
    }
}
