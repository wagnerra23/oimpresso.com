<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Camada 3.5 — análise fina de cobertura de 1 auto-mem específico.
 *
 * Extrai "fatos críticos" (parágrafos com SHA, número de linha, comando shell,
 * ou bullets de 30+ chars) e checa quais já estão cobertos por ADRs/sessions.
 *
 * Uso:
 *   php artisan mem:coverage feedback_form_shim_bool_attrs
 *   php artisan mem:coverage feedback_carbon_timezone_bug
 *
 * Ver: ADR 0061 (zero auto-mem privada), ADR 0066 (formato canônico de promoção).
 */
class MemCoverageCommand extends Command
{
    protected $signature = 'mem:coverage {automem : Nome do arquivo auto-mem (sem .md)}
                                          {--automem-dir= : Override do diretório}';

    protected $description = 'Analisa cobertura de fatos de 1 auto-mem em git';

    public function handle(): int
    {
        $automemDir = $this->option('automem-dir')
            ?: ($_SERVER['USERPROFILE'] ?? $_SERVER['HOME'] ?? '') . '/.claude/projects/D--oimpresso-com/memory';
        $automemDir = str_replace('\\', '/', $automemDir);

        $name = $this->argument('automem');
        $name = preg_replace('/\.md$/', '', $name);
        $path = "$automemDir/$name.md";

        if (! file_exists($path)) {
            $this->error("Auto-mem não encontrado: $path");
            return 1;
        }

        $conteudo = file_get_contents($path);
        $linhas = substr_count($conteudo, "\n") + 1;

        $fatos = $this->extrairFatosCriticos($conteudo);

        $this->line("<info>Auto-mem:</info> $name.md");
        $this->line("  $linhas linhas, " . count($fatos) . " fatos críticos extraídos");
        $this->line('');

        if (empty($fatos)) {
            $this->warn('Nenhum fato crítico identificável (SHA, comando, bullet denso).');
            $this->line('Recomendação: <comment>auto-mem fraco — provavelmente preferência ou contexto, não conhecimento canônico.</comment>');
            return 0;
        }

        // Busca cobertura em git (memory/decisions, memory/sessions, INFRA.md, CLAUDE.md)
        $arquivosGit = $this->arquivosCanonicos();

        $cobertos = [];
        $faltando = [];

        foreach ($fatos as $fato) {
            $cobertura = $this->buscarCobertura($fato, $arquivosGit);
            if (! empty($cobertura)) {
                $cobertos[] = ['fato' => $fato, 'fontes' => $cobertura];
            } else {
                $faltando[] = $fato;
            }
        }

        $this->line("<info>Match em git (" . count($cobertos) . " fatos):</info>");
        foreach ($cobertos as $c) {
            $this->line('  ✓ ' . $this->truncar($c['fato'], 80));
            $this->line('    em: ' . implode(', ', array_slice($c['fontes'], 0, 3)));
        }

        $this->line('');
        $this->line("<info>Faltando em git (" . count($faltando) . " fatos):</info>");
        foreach ($faltando as $f) {
            $this->line('  ✗ ' . $this->truncar($f, 100));
        }

        $this->line('');
        $cobertura = count($fatos) > 0 ? round((count($cobertos) / count($fatos)) * 100) : 0;
        $this->line("<info>Cobertura: $cobertura%</info>");

        if ($cobertura >= 80) {
            $this->line('<comment>Recomendação: info já em git, deletar auto-mem (ADR 0061).</comment>');
        } elseif ($cobertura >= 30) {
            $this->line('<comment>Recomendação: criar ADR absorvendo fatos faltantes acima.</comment>');
        } else {
            $next = $this->proximoNumeroAdr();
            $this->line("<comment>Recomendação: forte candidato a ADR {$next} novo (cobertura baixa).</comment>");
        }

        return 0;
    }

    /** @return string[] */
    private function extrairFatosCriticos(string $conteudo): array
    {
        $fatos = [];

        // Linhas com SHA git (7+ hex)
        if (preg_match_all('/^.*\b[0-9a-f]{7,12}\b.*$/m', $conteudo, $m)) {
            $fatos = array_merge($fatos, $m[0]);
        }
        // Comandos shell (linhas começando com $, php artisan, ssh, git, npm, composer, curl)
        if (preg_match_all('/^\s*(?:\$|php\s+artisan|ssh\s|git\s|npm\s|composer\s|curl\s).{10,}$/mi', $conteudo, $m)) {
            $fatos = array_merge($fatos, $m[0]);
        }
        // Bullets densos (- /* ou número.) com 30+ chars de texto não-trivial
        if (preg_match_all('/^[\s]*[-*•]\s+(.{30,}?)$/m', $conteudo, $m)) {
            foreach ($m[1] as $bullet) {
                if (! preg_match('/^[\s\-=]+$/', $bullet)) $fatos[] = trim($bullet);
            }
        }

        // Dedup, normaliza
        $fatos = array_map(fn($f) => trim(preg_replace('/\s+/', ' ', $f)), $fatos);
        return array_values(array_unique(array_filter($fatos)));
    }

    /** @return string[] */
    private function arquivosCanonicos(): array
    {
        $arquivos = [];
        foreach (['memory/decisions', 'memory/sessions', 'memory/requisitos'] as $dir) {
            $base = base_path($dir);
            if (is_dir($base)) {
                $iter = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base));
                foreach ($iter as $f) {
                    if ($f->isFile() && in_array($f->getExtension(), ['md'], true)) {
                        $arquivos[] = $f->getPathname();
                    }
                }
            }
        }
        foreach (['CLAUDE.md', 'INFRA.md', 'CURRENT.md', 'memory/08-handoff.md'] as $f) {
            if (file_exists(base_path($f))) $arquivos[] = base_path($f);
        }
        return $arquivos;
    }

    /** @return string[] arquivos onde o fato aparece */
    private function buscarCobertura(string $fato, array $arquivos): array
    {
        // Extrai termos significativos (SHA, palavras 5+ chars)
        $termos = [];
        if (preg_match_all('/\b[0-9a-f]{7,12}\b/', $fato, $m)) $termos = array_merge($termos, $m[0]);
        if (preg_match_all('/[A-Za-záéíóúçãõ_]{6,}/u', $fato, $m)) {
            foreach ($m[0] as $t) {
                if (! in_array(mb_strtolower($t), ['quando', 'depois', 'antes', 'sempre', 'porque'], true)) {
                    $termos[] = $t;
                }
            }
        }
        $termos = array_slice(array_unique($termos), 0, 5);
        if (empty($termos)) return [];

        $hits = [];
        foreach ($arquivos as $path) {
            $c = file_get_contents($path);
            $matched = 0;
            foreach ($termos as $t) {
                if (stripos($c, $t) !== false) $matched++;
            }
            // Precisa de 2+ termos pra contar como cobertura
            if ($matched >= 2) {
                $hits[] = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $path);
            }
        }
        return $hits;
    }

    private function proximoNumeroAdr(): string
    {
        $arquivos = glob(base_path('memory/decisions/*.md')) ?: [];
        $max = 0;
        foreach ($arquivos as $f) {
            if (preg_match('/(\d{4})-/', basename($f), $m)) {
                $max = max($max, (int) $m[1]);
            }
        }
        return sprintf('%04d', $max + 1);
    }

    private function truncar(string $s, int $n): string
    {
        return mb_strlen($s) > $n ? mb_substr($s, 0, $n - 1) . '…' : $s;
    }
}
