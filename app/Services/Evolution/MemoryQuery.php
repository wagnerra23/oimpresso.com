<?php

declare(strict_types=1);

namespace App\Services\Evolution;

use Symfony\Component\Finder\Finder;

/**
 * MemoryQuery — busca textual simples em memory/*.md.
 *
 * Fase 1a: sem vetor, sem LLM. Score por contagem de termos.
 * Fase 1b adiciona embeddings Voyage + cosine similarity (ver TESTS.md).
 *
 * Determinístico: mesma query retorna mesma ordem (gate pra eval LLM-as-Judge).
 *
 * @see memory/requisitos/EvolutionAgent/SPEC.md
 */
class MemoryQuery
{
    public function __construct(
        private readonly string $memoryPath,
        private readonly array $excludePaths = ['memory_backup', '_arquivo'],
    ) {}

    /**
     * Retorna top-K chunks relevantes pra query.
     *
     * @return array<int, array{file:string, heading:string, content:string, score:float}>
     */
    public function search(string $query, int $topK = 5): array
    {
        $terms = $this->tokenize($query);

        if (empty($terms)) {
            return [];
        }

        $chunks = $this->collectChunks();
        $scored = $this->score($chunks, $terms);

        usort($scored, function ($a, $b) {
            $cmp = $b['score'] <=> $a['score'];

            return $cmp !== 0 ? $cmp : strcmp($a['file'], $b['file']);
        });

        return array_slice(
            array_filter($scored, fn ($c) => $c['score'] > 0),
            0,
            $topK
        );
    }

    /**
     * @return array<int, array{file:string, heading:string, content:string}>
     */
    private function collectChunks(): array
    {
        if (! is_dir($this->memoryPath)) {
            return [];
        }

        $finder = (new Finder)
            ->files()
            ->in($this->memoryPath)
            ->name('*.md')
            ->ignoreVCS(true);

        foreach ($this->excludePaths as $exclude) {
            $finder->notPath($exclude);
        }

        $chunks = [];

        foreach ($finder as $file) {
            $relative = str_replace('\\', '/', $file->getRelativePathname());

            foreach ($this->splitByHeader($file->getContents()) as [$heading, $content]) {
                if (trim($content) === '') {
                    continue;
                }

                $chunks[] = [
                    'file' => $relative,
                    'heading' => $heading,
                    'content' => $content,
                ];
            }
        }

        return $chunks;
    }

    /**
     * Split markdown em chunks por header H2/H3. Heading "" pra preâmbulo.
     *
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

    /**
     * @param  array<int, array{file:string, heading:string, content:string}>  $chunks
     * @param  array<int, string>  $terms
     * @return array<int, array{file:string, heading:string, content:string, score:float}>
     */
    private function score(array $chunks, array $terms): array
    {
        $scored = [];

        foreach ($chunks as $chunk) {
            $haystack = mb_strtolower($chunk['file'].' '.$chunk['heading'].' '.$chunk['content']);
            $score = 0.0;

            foreach ($terms as $term) {
                $hits = mb_substr_count($haystack, $term);
                if ($hits === 0) {
                    continue;
                }

                $score += $hits;

                if (str_contains(mb_strtolower($chunk['file']), $term)) {
                    $score += 3.0;
                }
                if (str_contains(mb_strtolower($chunk['heading']), $term)) {
                    $score += 2.0;
                }
            }

            $scored[] = [...$chunk, 'score' => $score];
        }

        return $scored;
    }

    /**
     * @return array<int, string>
     */
    private function tokenize(string $query): array
    {
        $normalized = mb_strtolower(trim($query));
        $tokens = preg_split('/[\s,;.!?]+/u', $normalized) ?: [];
        $stopwords = ['o', 'a', 'os', 'as', 'de', 'da', 'do', 'um', 'uma', 'e', 'ou', 'no', 'na', 'em', 'para', 'pra', 'que', 'com'];

        return array_values(array_filter(
            $tokens,
            fn ($t) => $t !== '' && mb_strlen($t) >= 2 && ! in_array($t, $stopwords, true)
        ));
    }
}
