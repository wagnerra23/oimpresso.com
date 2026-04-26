<?php

declare(strict_types=1);

namespace App\Services\Evolution\Tools;

use App\Models\Evolution\MemoryChunk;
use App\Services\Evolution\Embeddings\CosineSimilarity;
use App\Services\Evolution\Embeddings\EmbeddingDriver;
use App\Services\Evolution\Embeddings\EmbeddingDriverFactory;
use App\Services\Evolution\MemoryQuery;

/**
 * MemoryQueryTool — busca semântica em vizra_memory_chunks.
 *
 * Fallback: se o índice estiver vazio (não rodaram evolution:index),
 * cai em busca textual via MemoryQuery em memory/.
 *
 * Vizra-compat: __invoke(['query' => ..., 'top_k' => 5, 'scope' => null]).
 */
class MemoryQueryTool implements Tool
{
    public function __construct(
        private readonly ?EmbeddingDriver $driver = null,
        private readonly ?string $memoryPath = null,
    ) {}

    public function name(): string
    {
        return 'MemoryQuery';
    }

    public function description(): string
    {
        return 'Busca top-K chunks relevantes da memória do projeto via embedding ou textual.';
    }

    public function __invoke(array $args = [])
    {
        $query = (string) ($args['query'] ?? '');
        $topK = (int) ($args['top_k'] ?? 5);
        $scope = isset($args['scope']) ? (string) $args['scope'] : null;

        if ($query === '') {
            return [];
        }

        try {
            $hasIndex = MemoryChunk::query()
                ->whereNotNull('embedding')
                ->when($scope, fn ($q, $s) => $q->where('scope_module', $s))
                ->exists();
        } catch (\Throwable $e) {
            // DB indisponível (sqlite memória sem migrate, MySQL offline, etc).
            return $this->fallbackTextual($query, $topK, $scope);
        }

        if (! $hasIndex) {
            return $this->fallbackTextual($query, $topK, $scope);
        }

        $driver = $this->driver ?? EmbeddingDriverFactory::make();
        [$queryVector] = $driver->embed([$query]);

        try {
            $rows = MemoryChunk::query()
                ->when($scope, fn ($q, $s) => $q->where('scope_module', $s))
                ->whereNotNull('embedding')
                ->get();
        } catch (\Throwable $e) {
            return $this->fallbackTextual($query, $topK, $scope);
        }

        $scored = [];
        foreach ($rows as $row) {
            $vec = $row->getEmbeddingVector();
            $score = CosineSimilarity::compute($queryVector, $vec);
            $scored[] = [
                'file' => $row->source_path,
                'heading' => $row->heading ?? '',
                'content' => $row->chunk_text,
                'score' => $score,
                'scope' => $row->scope_module,
            ];
        }

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($scored, 0, $topK);
    }

    /**
     * @return array<int, array{file:string, heading:string, content:string, score:float}>
     */
    private function fallbackTextual(string $query, int $topK, ?string $scope): array
    {
        $path = $this->memoryPath
            ?? config('evolution.memory_path', base_path('memory'));

        $service = new MemoryQuery(memoryPath: $path);
        $results = $service->search($query, $topK);

        if ($scope !== null) {
            $results = array_values(array_filter(
                $results,
                fn ($r) => str_contains($r['file'], $scope)
            ));
        }

        return $results;
    }
}
