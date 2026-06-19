<?php

declare(strict_types=1);

namespace Modules\Jana\Console\Commands;

use Illuminate\Console\Command;
use Modules\Jana\Services\Memoria\DesignIngestPlanner;

/**
 * PR-2b da estação de ingestão de design ([plano] vectorized-badger · ADR 0270).
 *
 * `design:ingest-zip --zip=<x.zip> --tela=vendas` (prepare-only): descompacta SEMPRE
 * no mesmo lugar (`prototipo-ui/_incoming/<tela>/`, gitignored) → roteia contra o
 * `cowork-map.json` (DesignIngestPlanner) → diff por conteúdo (sha) vs a tela commitada
 * → escreve os entregáveis em `_prepared/`: PLANO-MUDANCAS-<tela>.md + a memória de
 * sessão da ingestão. NADA é aplicado: a aplicação real (`prototipos/<tela>/`) é gate
 * Wagner/CT100 (ADR 0291 D-E). Raiz configurável (`jana.dossie_root`) p/ fixtures.
 */
class DesignIngestZipCommand extends Command
{
    protected $signature = 'design:ingest-zip
                            {--zip= : Caminho do zip de design do Cowork}
                            {--tela= : Tela alvo (chave no cowork-map.json, ex: vendas)}
                            {--dry-run : (DEFAULT) prepara e NÃO aplica}';

    protected $description = 'Ingere um zip de design (pré-aplicação): unzip + map-roteamento + diff + PLANO-MUDANCAS + memória de sessão. Prepare-only.';

    public function handle(): int
    {
        $zip = (string) $this->option('zip');
        $tela = (string) $this->option('tela');
        if ($zip === '' || $tela === '') {
            $this->error('Use --zip=<arquivo.zip> --tela=<tela>.');

            return self::FAILURE;
        }
        if (! is_file($zip)) {
            $this->error("Zip não encontrado: {$zip}");

            return self::FAILURE;
        }

        $root = rtrim((string) config('jana.dossie_root', base_path()), '/\\');
        $incomingDir = "{$root}/prototipo-ui/_incoming/{$tela}";
        $committedDir = "{$root}/prototipo-ui/prototipos/{$tela}";
        $preparedDir = "{$incomingDir}/_prepared";

        $files = $this->extractZip($zip, $incomingDir);
        if ($files === []) {
            $this->warn('Zip vazio — nada a ingerir.');

            return self::SUCCESS;
        }

        $map = $this->loadMap($root);
        $routing = DesignIngestPlanner::route($map, $files, $tela);
        // diff dos arquivos ROTEADOS (pelo nome final do destino) vs a baseline commitada —
        // NÃO o dump bruto do zip: o handoff vem aninhado (project/inbox-page.jsx) e a baseline
        // é flat (inbox-page.jsx); casar pelo basename do destino faz add/mod/del baterem.
        $incomingMap = [];
        foreach ($routing['routed'] as $r) {
            $incomingMap[basename($r['to'])] = sha1_file("{$incomingDir}/{$r['from']}") ?: '';
        }
        $diff = DesignIngestPlanner::diffByContent($this->hashDir($committedDir), $incomingMap);
        // extras: separa "de outra tela conhecida do handoff" (ruído, agrega) de "desconhecido" (avaliar)
        $extras = DesignIngestPlanner::classifyExtras($map, $routing['extras'], $tela);

        $plano = DesignIngestPlanner::renderPlano($tela, $routing, $diff, $extras);
        $session = DesignIngestPlanner::renderSession($tela, $routing, $diff, now()->toDateString(), $extras);

        @mkdir($preparedDir, 0o775, true);
        file_put_contents("{$preparedDir}/PLANO-MUDANCAS-{$tela}.md", $plano);
        file_put_contents("{$preparedDir}/SESSION-design-ingest-{$tela}.md", $session);

        $this->info("✓ Ingestão preparada (NADA aplicado) em {$preparedDir}:");
        $this->line('  • PLANO-MUDANCAS-' . $tela . '.md — ' . count($routing['routed']) . ' roteados, '
            . count($extras['desconhecidos']) . ' a avaliar, ' . count($extras['outras_telas']) . ' de outras telas');
        $this->line("  • SESSION-design-ingest-{$tela}.md — memória da ingestão");
        if ($extras['desconhecidos'] !== []) {
            $this->warn('  ⚠ ' . count($extras['desconhecidos']) . ' arquivo(s) desconhecido(s) fora do cowork-map — avaliar antes de aplicar.');
        }
        $this->line('  Aplicar = mover os roteados pra prototipos/' . $tela . '/ via PR (gate Wagner/CT100) — esta estação só prepara.');

        return self::SUCCESS;
    }

    /** @return list<string> nomes relativos extraídos (ordenados, determinístico) */
    private function extractZip(string $zipPath, string $destDir): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            $this->error("Falha ao abrir o zip: {$zipPath}");

            return [];
        }
        if (! is_dir($destDir)) {
            @mkdir($destDir, 0o775, true);
        }
        $zip->extractTo($destDir);
        $zip->close();

        $out = [];
        if (is_dir($destDir)) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($destDir, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                if ($f instanceof \SplFileInfo && $f->isFile()) {
                    $rel = ltrim(str_replace($destDir, '', $f->getPathname()), '/\\');
                    if (! str_starts_with($rel, '_prepared')) {
                        $out[] = str_replace('\\', '/', $rel);
                    }
                }
            }
        }
        sort($out);

        return $out;
    }

    /** @return array<string, mixed> */
    private function loadMap(string $root): array
    {
        $path = "{$root}/prototipo-ui/cowork-map.json";
        if (! is_file($path)) {
            return [];
        }
        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Mapa relpath => sha1 dos arquivos de um diretório (pula `_prepared/`). Sem git —
     * o diff é por conteúdo (DesignIngestPlanner::diffByContent). Dir inexistente → [].
     *
     * @return array<string, string>
     */
    private function hashDir(string $dir): array
    {
        if (! is_dir($dir)) {
            return [];
        }
        $out = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if (! $f instanceof \SplFileInfo || ! $f->isFile()) {
                continue;
            }
            $rel = str_replace('\\', '/', ltrim(str_replace($dir, '', $f->getPathname()), '/\\'));
            if (str_starts_with($rel, '_prepared')) {
                continue;
            }
            $out[$rel] = sha1_file($f->getPathname()) ?: '';
        }

        return $out;
    }
}
