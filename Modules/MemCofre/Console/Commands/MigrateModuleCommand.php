<?php

namespace Modules\MemCofre\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Migra um módulo do formato plano (memory/requisitos/{Nome}.md) para o
 * formato pasta (memory/requisitos/{Nome}/README|ARCHITECTURE|SPEC|CHANGELOG.md + adr/).
 *
 * Mantém o arquivo original como .bak pra rollback.
 *
 * Uso:
 *   php artisan memcofre:migrate-module PontoWr2
 *   php artisan memcofre:migrate-module PontoWr2 --force
 */
class MigrateModuleCommand extends Command
{
    protected $signature = 'memcofre:migrate-module
                            {name : Nome do módulo (ex: PontoWr2)}
                            {--force : Sobrescreve pasta existente}';

    protected $description = 'Migra um módulo do formato .md plano pra estrutura de pasta com README/ARCHITECTURE/SPEC/CHANGELOG/adr';

    public function handle(): int
    {
        $name = $this->argument('name');
        $requisitosDir = base_path('memory/requisitos');
        $flat = $requisitosDir . DIRECTORY_SEPARATOR . $name . '.md';
        $folder = $requisitosDir . DIRECTORY_SEPARATOR . $name;

        if (! File::exists($flat)) {
            $this->error("Arquivo '{$flat}' não encontrado.");
            return 1;
        }

        if (File::isDirectory($folder) && ! $this->option('force')) {
            $this->error("Pasta '{$folder}' já existe. Use --force pra sobrescrever.");
            return 1;
        }

        File::ensureDirectoryExists($folder);
        File::ensureDirectoryExists($folder . DIRECTORY_SEPARATOR . 'adr');

        $content = File::get($flat);
        $fm = $this->extractFrontmatter($content);
        $body = preg_replace('/^---\s*\n.*?\n---\s*\n/s', '', $content, 1);

        // Separa seções do body
        $sections = $this->splitBody($body);

        // README: frontmatter + intro + objetivo + propósito
        $readme = $this->buildReadme($name, $fm, $sections);
        File::put($folder . DIRECTORY_SEPARATOR . 'README.md', $readme);

        // ARCHITECTURE: áreas funcionais + modelo + fluxos (tudo exceto US-/R- e sumário)
        $arch = $this->buildArchitecture($sections);
        File::put($folder . DIRECTORY_SEPARATOR . 'ARCHITECTURE.md', $arch);

        // SPEC: só as seções com US-/R-
        $spec = $this->buildSpec($body, $sections);
        File::put($folder . DIRECTORY_SEPARATOR . 'SPEC.md', $spec);

        // CHANGELOG: stub com entrada inicial baseada em last_generated do frontmatter
        $changelog = $this->buildChangelog($fm);
        File::put($folder . DIRECTORY_SEPARATOR . 'CHANGELOG.md', $changelog);

        // Backup do arquivo original
        File::move($flat, $flat . '.bak');

        $this->info("✓ Migrado: {$name}");
        $this->line("  README.md       " . $this->fmtBytes(strlen($readme)));
        $this->line("  ARCHITECTURE.md " . $this->fmtBytes(strlen($arch)));
        $this->line("  SPEC.md         " . $this->fmtBytes(strlen($spec)));
        $this->line("  CHANGELOG.md    " . $this->fmtBytes(strlen($changelog)));
        $this->line("  adr/            (vazio — adicione ADRs conforme decidir)");
        $this->line("  Backup: {$flat}.bak");

        return 0;
    }

    protected function extractFrontmatter(string $content): string
    {
        if (preg_match('/^(---\s*\n.*?\n---\s*\n)/s', $content, $m)) {
            return $m[1];
        }
        return "---\n---\n";
    }

    /**
     * Quebra o body em seções por cabeçalho `## `. Retorna array ['titulo' => 'corpo'].
     */
    protected function splitBody(string $body): array
    {
        $sections = [];
        $parts = preg_split('/(?=^##\s+[^#])/m', $body);
        foreach ($parts as $p) {
            if (! preg_match('/^##\s+(.+?)$/m', $p, $m)) continue;
            $title = trim($m[1]);
            $sections[$title] = trim($p);
        }
        return $sections;
    }

    protected function buildReadme(string $name, string $fm, array $sections): string
    {
        $intro = $sections['Sumário'] ?? $sections['1. Objetivo'] ?? '';
        $propose = $sections['1. Objetivo'] ?? $sections['Objetivo'] ?? '';

        return $fm . "\n# {$name}\n\n"
            . ($intro ? preg_replace('/^##\s+.+?\n/m', '', $intro, 1) . "\n\n" : '')
            . "## Índice\n\n"
            . "- **[ARCHITECTURE.md](ARCHITECTURE.md)** — camadas, modelos, áreas funcionais\n"
            . "- **[SPEC.md](SPEC.md)** — user stories e regras Gherkin\n"
            . "- **[CHANGELOG.md](CHANGELOG.md)** — histórico de mudanças\n"
            . "- **[adr/](adr/)** — decisões arquiteturais numeradas\n";
    }

    protected function buildArchitecture(array $sections): string
    {
        $out = "# Arquitetura\n\n";
        foreach ($sections as $title => $content) {
            // Pula Sumário (vira README) e qualquer seção que contenha só US-/R-
            if (in_array($title, ['Sumário', 'Índice'], true)) continue;
            if (preg_match('/###\s+(US|R)-/', $content)) continue;
            $out .= $content . "\n\n";
        }
        return trim($out) . "\n";
    }

    protected function buildSpec(string $body, array $sections): string
    {
        $out = "# Especificação funcional\n\n";

        // Pega seções que contêm US- ou R-
        $hasStories = false;
        $hasRules = false;
        foreach ($sections as $title => $content) {
            if (preg_match('/###\s+US-/', $content)) $hasStories = true;
            if (preg_match('/###\s+R-/', $content)) $hasRules = true;
        }

        if (! $hasStories && ! $hasRules) {
            // Tenta extrair direto do body se não encaixou em seção
            if (preg_match_all('/###\s+(US-[A-Z]+-\d+).*?(?=\n###\s+(?:US|R)-|\n##\s+|\z)/s', $body, $mS)) {
                $out .= "## User stories\n\n" . implode("\n\n", $mS[0]) . "\n\n";
            }
            if (preg_match_all('/###\s+(R-[A-Z]+-\d+).*?(?=\n###\s+(?:US|R)-|\n##\s+|\z)/s', $body, $mR)) {
                $out .= "## Regras\n\n" . implode("\n\n", $mR[0]) . "\n";
            }
            return $out;
        }

        foreach ($sections as $title => $content) {
            if (preg_match('/###\s+(US|R)-/', $content)) {
                $out .= $content . "\n\n";
            }
        }
        return trim($out) . "\n";
    }

    protected function buildChangelog(string $fm): string
    {
        $date = date('Y-m-d');
        if (preg_match('/last_generated:\s*(\d{4}-\d{2}-\d{2})/', $fm, $m)) {
            $date = $m[1];
        }

        return "# Changelog\n\n"
            . "Formato: [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/) · [Semver](https://semver.org/).\n\n"
            . "## [0.1.0] - {$date}\n\n"
            . "### Added\n\n"
            . "- Documentação inicial consolidada a partir do arquivo plano.\n"
            . "- Migrado para estrutura de pasta (README + ARCHITECTURE + SPEC + CHANGELOG + adr/).\n";
    }

    protected function fmtBytes(int $n): string
    {
        if ($n < 1024) return "{$n}B";
        return round($n / 1024, 1) . 'kB';
    }
}
