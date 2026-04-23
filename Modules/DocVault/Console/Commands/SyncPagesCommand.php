<?php

namespace Modules\DocVault\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Modules\DocVault\Entities\DocPage;

/**
 * Varre resources/js/Pages/**\/*.tsx procurando blocos `@docvault` e
 * sincroniza com a tabela docs_pages.
 *
 * Formato esperado do bloco (nas primeiras 30 linhas do arquivo):
 *
 *   // @docvault
 *   //   tela: /ponto/espelho
 *   //   stories: US-PONTO-003, US-PONTO-004
 *   //   rules: R-PONTO-002
 *   //   adrs: 0001, 0003
 *   //   tests: Modules/PontoWr2/Tests/Feature/PontoEspelhoTest
 *   //   status: implementada
 *   //   module: PontoWr2
 *
 * Uso:
 *   php artisan docvault:sync-pages
 *   php artisan docvault:sync-pages --dry  (só mostra, não grava)
 */
class SyncPagesCommand extends Command
{
    protected $signature = 'docvault:sync-pages
                            {--dry : Não grava — só mostra o que seria sincronizado}';

    protected $description = 'Varre telas React (.tsx) procurando blocos @docvault e popula docs_pages';

    public function handle(): int
    {
        $pagesRoot = resource_path('js/Pages');
        if (! File::isDirectory($pagesRoot)) {
            $this->error("Pasta {$pagesRoot} não encontrada.");
            return 1;
        }

        $files = $this->findTsxRecursive($pagesRoot);
        $found = 0;
        $missing = 0;
        $synced = 0;

        foreach ($files as $file) {
            $relative = str_replace($pagesRoot . DIRECTORY_SEPARATOR, '', $file);
            $head = $this->readHead($file, 40);

            $meta = $this->parseDocvaultBlock($head);
            if (! $meta) {
                $missing++;
                continue;
            }

            $found++;

            $data = [
                'path'           => $meta['tela'] ?? ('/' . strtolower(str_replace(['\\', '.tsx'], ['/', ''], $relative))),
                'component'      => str_replace('\\', '/', $relative),
                'module'         => $meta['module'] ?? $this->inferModule($relative),
                'status'         => $meta['status'] ?? 'implementada',
                'stories'        => $this->splitList($meta['stories'] ?? ''),
                'rules'          => $this->splitList($meta['rules'] ?? ''),
                'adrs'           => $this->splitList($meta['adrs'] ?? ''),
                'tests'          => $this->splitList($meta['tests'] ?? ''),
                'file_path'      => 'resources/js/Pages/' . str_replace('\\', '/', $relative),
                'last_synced_at' => now(),
            ];

            if ($this->option('dry')) {
                $this->line(sprintf('[DRY] %s → %s (module=%s, stories=%d, rules=%d, adrs=%d, tests=%d)',
                    $relative, $data['path'], $data['module'],
                    count($data['stories']), count($data['rules']),
                    count($data['adrs']), count($data['tests'])));
            } else {
                DocPage::updateOrCreate(['path' => $data['path']], $data);
                $synced++;
            }
        }

        $this->info("Total .tsx examinados: " . count($files));
        $this->info("Com bloco @docvault:   {$found}");
        $this->line("Sem bloco @docvault:   {$missing}");
        if (! $this->option('dry')) {
            $this->info("Sincronizados em docs_pages: {$synced}");
        }

        return 0;
    }

    protected function findTsxRecursive(string $dir): array
    {
        $out = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->isFile() && str_ends_with($f->getFilename(), '.tsx')) {
                $out[] = $f->getPathname();
            }
        }
        return $out;
    }

    protected function readHead(string $path, int $lines): string
    {
        $fh = fopen($path, 'r');
        $out = '';
        for ($i = 0; $i < $lines && ! feof($fh); $i++) {
            $out .= fgets($fh);
        }
        fclose($fh);
        return $out;
    }

    /**
     * Procura por:
     *   // @docvault
     *   //   campo: valor
     *   //   campo: valor
     *
     * Retorna array associativo ou null se não encontrar.
     */
    protected function parseDocvaultBlock(string $head): ?array
    {
        if (! preg_match('/\/\/\s*@docvault\b(.*?)(?=\n(?:[^\/\s]|\/\/\s*[^\s]))/s', $head . "\n\nSTOP", $m)) {
            return null;
        }

        $block = $m[1];
        $meta = [];
        foreach (preg_split('/\R/', $block) as $line) {
            $line = trim($line);
            if (! str_starts_with($line, '//')) continue;
            $line = trim(substr($line, 2));
            if (preg_match('/^(\w+)\s*:\s*(.+)$/', $line, $mm)) {
                $meta[strtolower($mm[1])] = trim($mm[2]);
            }
        }

        return !empty($meta) ? $meta : null;
    }

    protected function splitList(string $raw): array
    {
        if ($raw === '') return [];
        $parts = preg_split('/[,;]\s*/', $raw);
        return array_values(array_filter(array_map('trim', $parts)));
    }

    protected function inferModule(string $relative): string
    {
        $parts = preg_split('/[\/\\\\]/', $relative);
        return $parts[0] ?? 'Unknown';
    }
}
