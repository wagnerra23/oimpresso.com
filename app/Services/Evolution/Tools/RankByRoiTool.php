<?php

declare(strict_types=1);

namespace App\Services\Evolution\Tools;

use Symfony\Component\Finder\Finder;

/**
 * Lê SPEC.md do escopo + tabelas ROI e retorna top-N oportunidades.
 *
 * Heurística (sem LLM): pondera score textual + presença em tabelas "ROI", "Onda",
 * "Próximo". Quando LLM-as-Judge estiver online (eval), o ranking pode ser
 * substituído pela saída do agente.
 */
class RankByRoiTool implements Tool
{
    public function __construct(private readonly ?string $memoryPath = null) {}

    public function name(): string
    {
        return 'RankByRoi';
    }

    public function description(): string
    {
        return 'Ranqueia top-N oportunidades por ROI a partir do SPEC.md de um escopo.';
    }

    public function __invoke(array $args = [])
    {
        $base = $this->memoryPath ?? (string) config('evolution.memory_path', base_path('memory'));
        $scope = isset($args['scope']) ? (string) $args['scope'] : null;
        $top = (int) ($args['top'] ?? 3);

        $items = [];

        $dirs = $scope !== null
            ? [$base.'/requisitos/'.$scope]
            : [$base.'/requisitos'];

        foreach ($dirs as $dir) {
            if (! is_dir($dir)) {
                continue;
            }

            $finder = (new Finder)->files()->in($dir)->name('SPEC.md')->depth('< 4');

            foreach ($finder as $file) {
                foreach ($this->extractRoiTable($file->getContents()) as $row) {
                    $row['source'] = 'memory/'.ltrim(str_replace([$base, '\\'], ['', '/'], $file->getRealPath()), '/');
                    $items[] = $row;
                }
            }
        }

        usort($items, fn ($a, $b) => ($b['roi_score'] ?? 0) <=> ($a['roi_score'] ?? 0));

        return array_slice($items, 0, $top);
    }

    /**
     * Extrai linhas tipo `| Componente | ... | ~10× |` de tabelas markdown.
     *
     * @return array<int, array<string, mixed>>
     */
    private function extractRoiTable(string $content): array
    {
        $rows = [];
        $lines = explode("\n", $content);

        foreach ($lines as $line) {
            if (! str_contains($line, '|')) {
                continue;
            }

            if (preg_match('/\|\s*([^|]+?)\s*\|.*?(\d+(?:[.,]\d+)?)\s*[×x]/i', $line, $m)) {
                $rows[] = [
                    'titulo' => trim($m[1]),
                    'roi_score' => (float) str_replace(',', '.', $m[2]),
                    'linha' => trim($line),
                ];
            }
        }

        return $rows;
    }
}
