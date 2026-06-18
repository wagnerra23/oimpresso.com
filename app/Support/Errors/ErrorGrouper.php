<?php

declare(strict_types=1);

namespace App\Support\Errors;

use App\Models\ErrorGroup;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * ErrorGrouper — deduplica erros por dedupKey (Fase 2 · E-2 "Absorver").
 *
 * `record()` faz upsert por `dedup_key` com incremento atômico de `count`:
 * 1000 ocorrências do mesmo erro = 1 linha com count=1000, nunca 1000 alertas.
 *
 * Resiliente por contrato: NUNCA lança — DB fora não pode derrubar o report/alerta
 * da E-1 (justo o cenário do S0 "banco indisponível"). Falha → null + log.
 *
 * NÃO toca a régua da E-1 (ErrorClassifier) — só absorve o que ela carimbou.
 *
 * @see prototipo-ui/handoffs/erros-dedup.md
 */
class ErrorGrouper
{
    /**
     * Registra 1 ocorrência. Upsert por dedup_key + incremento atômico de count.
     *
     * @param  array<string, mixed>  $sample  Amostra SEM PII (ex: ['exception' => ..., 'local' => ...]).
     */
    public function record(Classification $c, array $sample = []): ?ErrorGroup
    {
        if (! Schema::hasTable('error_groups')) {
            return null;
        }

        try {
            $now = now();

            // Incremento atômico se o grupo já existe.
            $affected = DB::table('error_groups')
                ->where('dedup_key', $c->dedupKey)
                ->update([
                    'count'      => DB::raw('count + 1'),
                    'last_seen'  => $now,
                    'updated_at' => $now,
                ]);

            if ($affected === 0) {
                // Primeira ocorrência — insere. A unique de dedup_key trata corrida.
                try {
                    DB::table('error_groups')->insert([
                        'dedup_key'      => $c->dedupKey,
                        'severity'       => $c->severity->value,
                        'audience'       => $c->audience->value,
                        'owner'          => $c->owner,
                        'count'          => 1,
                        'status'         => ErrorGroup::STATUS_OPEN,
                        'first_seen'     => $now,
                        'last_seen'      => $now,
                        'sample_payload' => $sample === [] ? null : json_encode($sample),
                        'created_at'     => $now,
                        'updated_at'     => $now,
                    ]);
                } catch (QueryException) {
                    // Corrida: outra ocorrência inseriu antes → incrementa.
                    DB::table('error_groups')
                        ->where('dedup_key', $c->dedupKey)
                        ->update([
                            'count'      => DB::raw('count + 1'),
                            'last_seen'  => $now,
                            'updated_at' => $now,
                        ]);
                }
            }

            $group = ErrorGroup::query()->where('dedup_key', $c->dedupKey)->first();

            // Reincidência reabre grupo arquivado/resolvido.
            if ($group !== null && in_array($group->status, [ErrorGroup::STATUS_ARCHIVED, ErrorGroup::STATUS_RESOLVED], true)) {
                $group->status = ErrorGroup::STATUS_OPEN;
                $group->save();
            }

            return $group;
        } catch (Throwable $e) {
            Log::warning('error.group_record_failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /** Arquiva grupos abertos sem ocorrência há $days dias. Devolve quantos arquivou. */
    public function archiveStale(int $days): int
    {
        if (! Schema::hasTable('error_groups')) {
            return 0;
        }

        try {
            return ErrorGroup::query()->stale($days)->update(['status' => ErrorGroup::STATUS_ARCHIVED]);
        } catch (Throwable $e) {
            Log::warning('error.archive_stale_failed', ['error' => $e->getMessage()]);

            return 0;
        }
    }
}
