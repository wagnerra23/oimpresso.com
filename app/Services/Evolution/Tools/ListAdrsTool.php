<?php

declare(strict_types=1);

namespace App\Services\Evolution\Tools;

use Symfony\Component\Finder\Finder;

/**
 * Lista ADRs em memory/requisitos/{scope}/adr/ ou memory/decisions/.
 */
class ListAdrsTool implements Tool
{
    public function __construct(private readonly ?string $memoryPath = null) {}

    public function name(): string
    {
        return 'ListAdrs';
    }

    public function description(): string
    {
        return 'Lista ADRs disponíveis em memory/requisitos/<escopo>/adr ou memory/decisions/.';
    }

    public function __invoke(array $args = [])
    {
        $base = $this->memoryPath ?? (string) config('evolution.memory_path', base_path('memory'));
        $scope = isset($args['scope']) ? (string) $args['scope'] : null;
        $limit = (int) ($args['limit'] ?? 50);

        $candidates = [];

        if ($scope !== null) {
            $candidates[] = $base.'/requisitos/'.$scope.'/adr';
        } else {
            $candidates[] = $base.'/decisions';
        }

        $found = [];

        foreach ($candidates as $dir) {
            if (! is_dir($dir)) {
                continue;
            }

            $finder = (new Finder)->files()->in($dir)->name('*.md')->sortByName();

            foreach ($finder as $file) {
                $rel = ltrim(str_replace([$base, '\\'], ['', '/'], $file->getRealPath()), '/');
                $found[] = [
                    'path' => 'memory/'.$rel,
                    'name' => $file->getRelativePathname(),
                    'size' => $file->getSize(),
                ];

                if (count($found) >= $limit) {
                    break 2;
                }
            }
        }

        return $found;
    }
}
