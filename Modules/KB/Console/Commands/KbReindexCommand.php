<?php

declare(strict_types=1);

namespace Modules\KB\Console\Commands;

use Illuminate\Console\Command;
use Modules\KB\Services\KbCorpusBuilder;

/**
 * KbReindexCommand — rebuilda o índice Meilisearch do KB pra um business.
 *
 * Uso:
 *   php artisan kb:reindex --business=1            # rebuild biz 1
 *   php artisan kb:reindex --business=4 --dry-run  # só conta, não envia ao Meilisearch
 *   php artisan kb:reindex --all                   # todos os businesses ativos (TODO)
 *
 * Útil em:
 *   - desenvolvimento (fresh DB + seeders → reindex)
 *   - troubleshoot (corpus mudou mas search não reflete)
 *   - migração inicial (após Agent A entregar Models + Agent B finalizar bridge job)
 *
 * NÃO é executado em produção automaticamente — o pipeline canônico é o
 * `KbBridgeFromMcpJob` (SCHEMA-DB-V1 §10) rodando a cada 15min com Scout
 * Searchable observers que indexam DELTA em tempo real.
 *
 * Este command é o "BIG HAMMER" pra recompor o índice do zero.
 *
 * @see memory/requisitos/KB/SCHEMA-DB-V1.md §10
 * @see memory/decisions/0036-replanejamento-meilisearch-first.md
 */
class KbReindexCommand extends Command
{
    /**
     * Convenção projeto (ADR sobre `--detail` vs `--verbose`):
     * uso `--detail` quando precisar de output verboso adicional (não conflita
     * com `-v/-vv/-vvv` nativo do Symfony Console).
     */
    protected $signature = 'kb:reindex
        {--business= : business_id alvo (obrigatório se --all não passado)}
        {--all : reindexa TODOS businesses (TODO)}
        {--dry-run : conta documentos mas NÃO envia ao Meilisearch}
        {--detail : log detalhado de cada documento (lento, usar com cuidado)}';

    protected $description = 'Reindexa o corpus KB no Meilisearch (kb_nodes + bridge mcp_memory_documents).';

    public function handle(): int
    {
        $businessOpt = $this->option('business');
        $all         = (bool) $this->option('all');
        $dryRun      = (bool) $this->option('dry-run');
        $detail      = (bool) $this->option('detail');

        if (! $all && ! $businessOpt) {
            $this->error('Passe --business=N OU --all (TODO).');
            return self::FAILURE;
        }

        if ($all) {
            // TODO[F]: iterar businesses ativos pelo Business model. Por enquanto
            // pedimos --business explícito pra evitar reindex global acidental.
            $this->error('--all ainda não implementado — passe --business=N explícito.');
            return self::FAILURE;
        }

        $businessId = (int) $businessOpt;
        if ($businessId <= 0) {
            $this->error('--business deve ser inteiro positivo.');
            return self::FAILURE;
        }

        $this->info("KbReindex iniciado · business_id={$businessId} · dry_run=" . ($dryRun ? 'sim' : 'não'));

        $corpus = new KbCorpusBuilder($businessId);
        $total  = $corpus->count();

        $this->line("Documentos candidatos: <fg=cyan>{$total}</>");

        if ($total === 0) {
            $this->warn('Corpus vazio. Rodar seeders primeiro (Agent A → KbBridgeFromMcpSeeder + KbOperacionalSeeder).');
            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $indexed = 0;
        $skipped = 0;

        try {
            // Em --dry-run só iteramos e contamos. Em modo real, batch + send ao Meilisearch.
            // Como o pattern canônico é Scout Searchable observers (Agent A vai
            // adicionar trait Searchable em KbNode), o command real apenas
            // re-toca updated_at pra disparar reindex automático. Aqui exemplificamos
            // o stream + agregação.
            $batch = [];
            foreach ($corpus->streamDocuments() as $doc) {
                if ($detail) {
                    $this->line(" · {$doc['type']} · {$doc['slug']} (id={$doc['kb_node_id']})");
                }

                if (! $dryRun) {
                    $batch[] = $doc;
                    if (count($batch) >= 200) {
                        $this->sendBatchToMeilisearch($batch);
                        $batch = [];
                    }
                }

                $indexed++;
                $bar->advance();
            }

            if (! $dryRun && ! empty($batch)) {
                $this->sendBatchToMeilisearch($batch);
            }

            $bar->finish();
            $this->newLine(2);

            $corpusHash = $corpus->corpusVersionHash();
            $this->info("OK · indexados {$indexed} · corpus_hash=" . substr($corpusHash, 0, 12));

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $bar->finish();
            $this->newLine();
            $this->error('Erro: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    /**
     * Envia batch ao Meilisearch direto (sem Scout).
     *
     * TODO[Agent A]: quando KbNode tiver trait Searchable, trocar isto por
     *   KbNode::query()->withoutGlobalScopes()->where('business_id', $biz)
     *     ->cursor()->each->searchable();
     * Aí o pattern fica idiomático Laravel Scout.
     *
     * @param  array<int,array<string,mixed>>  $batch
     */
    protected function sendBatchToMeilisearch(array $batch): void
    {
        if (! class_exists(\Meilisearch\Client::class)) {
            throw new \RuntimeException(
                'Meilisearch client PHP não instalado — rodar `composer require meilisearch/meilisearch-php`.'
            );
        }

        $host = (string) config('scout.meilisearch.host', env('MEILISEARCH_HOST', 'http://127.0.0.1:7700'));
        $key  = (string) config('scout.meilisearch.key',  env('MEILISEARCH_KEY', ''));

        $client = new \Meilisearch\Client($host, $key);
        $index  = $client->index(KbCorpusBuilder::INDEX_NAME);

        $index->addDocuments($batch, 'id');
    }
}
