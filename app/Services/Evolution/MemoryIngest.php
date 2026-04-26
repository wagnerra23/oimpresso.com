<?php

declare(strict_types=1);

namespace App\Services\Evolution;

use App\Models\Evolution\MemoryChunk;
use App\Services\Evolution\Embeddings\EmbeddingDriver;
use App\Services\Evolution\Embeddings\EmbeddingDriverFactory;
use Symfony\Component\Finder\Finder;

/**
 * Pipeline de ingest: lê memory/**.md, chunking por header, gera embedding,
 * upsert idempotente em vizra_memory_chunks (por hash).
 *
 * @see memory/requisitos/EvolutionAgent/SPEC.md US-EVOL-001
 */
class MemoryIngest
{
    /** @var array<int, string> */
    private array $excludePaths = ['memory_backup', '_arquivo', 'sessions'];

    public function __construct(
        private readonly string $memoryPath,
        private readonly ?EmbeddingDriver $driver = null,
    ) {}

    /**
     * @return array{indexed:int, skipped:int, deleted:int, chunks:int}
     */
    public function run(): array
    {
        if (! is_dir($this->memoryPath)) {
            return ['indexed' => 0, 'skipped' => 0, 'deleted' => 0, 'chunks' => 0];
        }

        $driver = $this->driver ?? EmbeddingDriverFactory::make();

        $finder = (new Finder)
            ->files()
            ->in($this->memoryPath)
            ->name('*.md')
            ->ignoreVCS(true);

        foreach ($this->excludePaths as $exclude) {
            $finder->notPath($exclude);
        }

        $indexed = 0;
        $skipped = 0;
        $totalChunks = 0;
        $seenHashes = [];

        $batchTexts = [];
        $batchMeta = [];

        foreach ($finder as $file) {
            $relative = str_replace('\\', '/', $file->getRelativePathname());
            $scope = $this->scopeFromPath($relative);
            $type = $this->typeFromPath($relative);

            foreach ($this->splitByHeader($file->getContents()) as [$heading, $content]) {
                $text = trim($content);
                if ($text === '') {
                    continue;
                }

                $hash = hash('sha256', $relative.'|'.$heading.'|'.$text);
                $seenHashes[] = $hash;

                $existing = MemoryChunk::query()
                    ->where('source_path', $relative)
                    ->where('content_hash', $hash)
                    ->first();

                if ($existing !== null && $existing->embedding !== null) {
                    $skipped++;
                    $totalChunks++;

                    continue;
                }

                $batchTexts[] = $text;
                $batchMeta[] = [
                    'source_path' => $relative,
                    'content_hash' => $hash,
                    'heading' => $heading !== '' ? mb_substr($heading, 0, 250) : null,
                    'chunk_text' => $text,
                    'tokens' => $this->estimateTokens($text),
                    'scope_module' => $scope,
                    'scope_type' => $type,
                ];

                $indexed++;
                $totalChunks++;
            }
        }

        // Embed em batches de 32 (limite Voyage = 128, conservador).
        foreach (array_chunk($batchTexts, 32, true) as $i => $textChunk) {
            $vectors = $driver->embed(array_values($textChunk));
            $keys = array_keys($textChunk);

            foreach ($keys as $j => $globalIdx) {
                $meta = $batchMeta[$globalIdx];
                $vector = $vectors[$j] ?? [];

                MemoryChunk::query()->updateOrCreate(
                    ['source_path' => $meta['source_path'], 'content_hash' => $meta['content_hash']],
                    array_merge($meta, [
                        'embedding' => MemoryChunk::encodeEmbedding($vector),
                        'indexed_at' => now(),
                    ])
                );
            }
        }

        return [
            'indexed' => $indexed,
            'skipped' => $skipped,
            'deleted' => 0,
            'chunks' => $totalChunks,
            'driver' => $driver->name(),
            'dimensions' => $driver->dimensions(),
        ];
    }

    /**
     * @return array<int, array{0:string, 1:string}>
     */
    private function splitByHeader(string $markdown): array
    {
        $lines = explode("\n", $markdown);
        $chunks = [];
        $heading = '';
        $buffer = [];

        foreach ($lines as $line) {
            if (preg_match('/^#{2,3}\s+(.+)$/', $line, $m)) {
                if (! empty($buffer)) {
                    $chunks[] = [$heading, implode("\n", $buffer)];
                }
                $heading = trim($m[1]);
                $buffer = [];

                continue;
            }
            $buffer[] = $line;
        }

        if (! empty($buffer)) {
            $chunks[] = [$heading, implode("\n", $buffer)];
        }

        return $chunks;
    }

    private function scopeFromPath(string $path): ?string
    {
        if (preg_match('#^requisitos/([^/]+)/#', $path, $m)) {
            return $m[1];
        }

        if (str_starts_with($path, 'decisions/')) {
            return null;
        }

        return null;
    }

    private function typeFromPath(string $path): string
    {
        if (str_contains($path, '/adr/')) {
            return 'adr';
        }
        if (str_ends_with($path, 'SPEC.md')) {
            return 'spec';
        }
        if (str_starts_with($path, 'decisions/')) {
            return 'decision';
        }
        if (str_starts_with($path, 'sessions/')) {
            return 'session';
        }

        return 'doc';
    }

    private function estimateTokens(string $text): int
    {
        // Rough: 1 token ≈ 4 chars
        return (int) ceil(mb_strlen($text) / 4);
    }
}
