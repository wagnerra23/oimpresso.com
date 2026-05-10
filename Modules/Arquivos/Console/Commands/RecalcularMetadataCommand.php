<?php

namespace Modules\Arquivos\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * arquivos:recalcular-metadata — Sprint 6 ADR 0123.
 *
 * Migrations backfill (PR #398 e PR #402) criaram rows em arquivos com
 * placeholders pra md5 e size_bytes (md5 = hash de "type:id:path" e size=0)
 * porque file físico podia estar em disk ausente OU não havia tempo de
 * stream cada file durante migration.
 *
 * Este command recalcula os placeholders lendo file físico do disk, batch
 * 200 rows por vez, idempotente (skip se md5 não-placeholder).
 *
 * Uso:
 *   php artisan arquivos:recalcular-metadata
 *     --tag=backfill-us-arq-020   (só uma tag)
 *     --tag=backfill-us-arq-026,backfill-us-arq-027  (múltiplas)
 *     --limit=1000                (cap rows processados)
 *     --dry-run                   (não escreve, só log)
 *
 * Heurística "é placeholder?":
 * - size_bytes = 0
 * - md5 começa com hash de pattern "type:id:path" (impossível distinguir
 *   sem armazenar marker — Sprint 7 adiciona coluna `metadata_recalculated_at`)
 *
 * @see memory/decisions/0123-modules-arquivos-backbone.md Sprint 6
 */
class RecalcularMetadataCommand extends Command
{
    protected $signature = 'arquivos:recalcular-metadata
        {--tag=* : Filtra por classified_by tags (ex: backfill-us-arq-020). Vazio = todos backfill}
        {--limit=1000 : Cap rows a processar (default 1000)}
        {--dry-run : Não escreve no DB, só loga o que faria}';

    protected $description = 'Recalcular md5/size_bytes em arquivos rows com placeholders (post-backfill).';

    public function handle(): int
    {
        if (! Schema::hasTable('arquivos')) {
            $this->error('arquivos table missing — rode Modules/Arquivos migrate primeiro.');
            return 1;
        }

        $tags = (array) $this->option('tag');
        $limit = (int) $this->option('limit');
        $dryRun = (bool) $this->option('dry-run');

        $query = DB::table('arquivos')
            ->where('size_bytes', 0) // placeholder candidates
            ->whereNull('deleted_at');

        if (! empty($tags)) {
            $query->whereIn('classified_by', $tags);
        } else {
            // Default: todos backfill tags conhecidos
            $query->where('classified_by', 'like', 'backfill-%');
        }

        $total = (clone $query)->count();
        $this->info("Encontradas {$total} rows com placeholders" . ($dryRun ? ' [DRY-RUN]' : ''));

        if ($total === 0) {
            $this->info('Nada pra processar.');
            return 0;
        }

        $stats = [
            'updated' => 0,
            'missing_file' => 0,
            'errored' => 0,
        ];

        $query->orderBy('id')
            ->limit($limit)
            ->chunk(200, function ($rows) use ($dryRun, &$stats) {
                foreach ($rows as $row) {
                    try {
                        $disk = Storage::disk($row->disk ?: 'local');

                        if (! $disk->exists($row->storage_path)) {
                            $stats['missing_file']++;
                            continue;
                        }

                        $size = $disk->size($row->storage_path);
                        $contents = $disk->get($row->storage_path);
                        $md5 = is_string($contents) ? md5($contents) : null;

                        if ($md5 === null) {
                            $stats['errored']++;
                            continue;
                        }

                        if ($dryRun) {
                            $this->line("  [dry] arquivo:{$row->id} size={$size} md5={$md5}");
                            $stats['updated']++;
                            continue;
                        }

                        DB::table('arquivos')
                            ->where('id', $row->id)
                            ->update([
                                'size_bytes' => $size,
                                'md5'        => $md5,
                                'updated_at' => now(),
                            ]);

                        $stats['updated']++;
                    } catch (\Throwable $e) {
                        $stats['errored']++;
                        Log::warning('arquivos.recalcular_metadata.error', [
                            'arquivo_id' => $row->id ?? null,
                            'error'      => substr($e->getMessage(), 0, 200),
                        ]);
                    }
                }
            });

        $this->newLine();
        $this->info("Atualizados: {$stats['updated']}");
        $this->warn("File ausente:  {$stats['missing_file']}");
        $this->error("Errored:       {$stats['errored']}");

        return $stats['errored'] > $total / 2 ? 2 : 0;
    }
}
