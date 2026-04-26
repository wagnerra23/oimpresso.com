<?php

declare(strict_types=1);

namespace App\Console\Commands\Evolution;

use App\Services\Evolution\Embeddings\EmbeddingDriverFactory;
use App\Services\Evolution\MemoryIngest;
use Illuminate\Console\Command;

/**
 * evolution:index — re-ingest memory/ pra vizra_memory_chunks.
 *
 * Idempotente por hash do conteúdo (re-rodar não duplica chunks).
 *
 * @see memory/requisitos/EvolutionAgent/SPEC.md US-EVOL-001
 */
class IndexCommand extends Command
{
    protected $signature = 'evolution:index
                            {--rebuild : Trunca a tabela antes de re-indexar}
                            {--json : Saída JSON pra consumo programático}';

    protected $description = 'Indexa memory/**.md em vizra_memory_chunks (chunking por header + embeddings)';

    public function handle(): int
    {
        $rebuild = (bool) $this->option('rebuild');
        $json = (bool) $this->option('json');

        if ($rebuild) {
            \App\Models\Evolution\MemoryChunk::query()->truncate();
            $this->warn('vizra_memory_chunks truncada (--rebuild).');
        }

        $memoryPath = (string) config('evolution.memory_path', base_path('memory'));
        $driver = EmbeddingDriverFactory::make();

        $this->info(sprintf('Indexando %s usando driver %s (%dD)...',
            $memoryPath, $driver->name(), $driver->dimensions()
        ));

        $service = new MemoryIngest(memoryPath: $memoryPath, driver: $driver);
        $stats = $service->run();

        if ($json) {
            $this->line(json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Indexed %d new, %d skipped (already current). Total chunks: %d. Driver: %s.',
            $stats['indexed'],
            $stats['skipped'],
            $stats['chunks'],
            $stats['driver'] ?? 'n/a'
        ));

        return self::SUCCESS;
    }
}
