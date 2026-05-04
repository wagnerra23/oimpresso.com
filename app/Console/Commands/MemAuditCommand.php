<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Camada 3.5 — auxiliar de governança (Opção C / promoção auto-mem→ADR).
 *
 * Audita auto-mem (~/.claude/projects/D--oimpresso-com/memory/*.md) versus ADRs
 * canônicas em memory/decisions/. Identifica candidatos a promoção:
 *   ✅ coberto por ADR
 *   ⚠️ sem cobertura clara (revisar)
 *   ❌ sem ADR + conteúdo denso (forte candidato)
 *
 * Heurística de match:
 *   - Levenshtein normalizado de títulos > 0.7 OU
 *   - 3+ palavras-chave (>=4 chars, não-stopword) compartilhadas
 *
 * Ver: ADR 0061 (zero auto-mem privada), ADR 0066 (exemplo de promoção).
 */
class MemAuditCommand extends Command
{
    protected $signature = 'mem:audit
                            {--candidates-only : Mostra só ⚠️ e ❌}
                            {--automem-dir= : Override do diretório auto-mem}';

    protected $description = 'Audita auto-mem versus ADRs canônicas (cobertura)';

    private const STOPWORDS = [
        'para', 'pelo', 'pela', 'esse', 'essa', 'isso', 'isto', 'aqui', 'mais', 'menos',
        'como', 'quando', 'onde', 'quem', 'tudo', 'nada', 'sobre', 'depois', 'antes',
        'que', 'com', 'sem', 'não', 'sim', 'foi', 'são', 'tem', 'ter', 'tem',
        'the', 'and', 'for', 'with', 'from', 'this', 'that', 'have', 'has', 'are',
        'was', 'were', 'will', 'would', 'should', 'could', 'about',
    ];

    public function handle(): int
    {
        $automemDir = $this->option('automem-dir')
            ?: ($_SERVER['USERPROFILE'] ?? $_SERVER['HOME'] ?? '') . '/.claude/projects/D--oimpresso-com/memory';
        $automemDir = str_replace('\\', '/', $automemDir);

        if (! is_dir($automemDir)) {
            $this->warn("Diretório auto-mem não encontrado: $automemDir");
            $this->line('Use --automem-dir=PATH se ele estiver em outro local.');
            return 1;
        }

        $automems = glob("$automemDir/*.md") ?: [];
        $adrs = $this->indexAdrs();

        if (empty($automems)) {
            $this->warn("Nenhum auto-mem em $automemDir");
            return 0;
        }

        $rows = [];
        $candidatesOnly = (bool) $this->option('candidates-only');

        foreach ($automems as $autoPath) {
            $name = basename($autoPath, '.md');
            $title = $this->extrairTitulo(file_get_contents($autoPath)) ?: $name;
            $linhas = substr_count(file_get_contents($autoPath), "\n");

            $match = $this->melhorMatch($title, file_get_contents($autoPath), $adrs);

            $emoji = match (true) {
                $match && $match['cobertura'] >= 0.7 => '✅',
                $match && $match['cobertura'] >= 0.3 => '⚠️',
                default                              => '❌',
            };

            if ($candidatesOnly && $emoji === '✅') continue;

            $rows[] = [
                $emoji,
                $this->truncar($name, 45),
                $match ? "ADR " . substr($match['slug'], 0, 4) : '—',
                $match ? round($match['cobertura'] * 100) . '%' : '0%',
                $linhas . ' linhas',
            ];
        }

        if (empty($rows)) {
            $this->info('Sem candidatos pendentes ✅');
            return 0;
        }

        $this->table(['', 'auto-mem', 'ADR equivalente', 'cobertura', 'tamanho'], $rows);
        return 0;
    }

    /** @return array<int, array{path:string, slug:string, title:string, content:string, keywords:array}> */
    private function indexAdrs(): array
    {
        $base = base_path('memory/decisions');
        $arquivos = glob("$base/*.md") ?: [];
        $idx = [];
        foreach ($arquivos as $path) {
            $name = basename($path, '.md');
            if (str_starts_with($name, '_') || $name === 'README') continue;
            $content = file_get_contents($path);
            $idx[] = [
                'path'     => $path,
                'slug'     => $name,
                'title'    => $this->extrairTitulo($content) ?: $name,
                'content'  => $content,
                'keywords' => $this->keywords($content),
            ];
        }
        return $idx;
    }

    private function extrairTitulo(string $conteudo): ?string
    {
        // YAML title:
        if (preg_match('/^title:\s*"?([^"\n]+)"?/m', $conteudo, $m)) return trim($m[1]);
        // # H1
        if (preg_match('/^#\s+(.+)$/m', $conteudo, $m)) return trim($m[1]);
        return null;
    }

    /** @return string[] */
    private function keywords(string $texto): array
    {
        $low = mb_strtolower(strip_tags($texto));
        // Remove markdown blocks
        $low = preg_replace('/```.*?```/s', '', $low) ?? $low;
        preg_match_all('/[a-záéíóúãõâêôç]{4,}/u', $low, $m);
        $palavras = array_diff($m[0] ?? [], self::STOPWORDS);
        return array_values(array_unique($palavras));
    }

    /** @return ?array{slug:string, cobertura:float} */
    private function melhorMatch(string $title, string $content, array $adrs): ?array
    {
        $autoKeywords = $this->keywords($title . ' ' . $content);
        $titleLow = mb_strtolower($title);

        $melhor = null;
        $melhorScore = 0.0;

        foreach ($adrs as $adr) {
            // Levenshtein title
            $adrTitleLow = mb_strtolower($adr['title']);
            $maxLen = max(mb_strlen($titleLow), mb_strlen($adrTitleLow), 1);
            $levSim = 1 - (levenshtein(
                mb_substr($titleLow, 0, 250),
                mb_substr($adrTitleLow, 0, 250)
            ) / $maxLen);
            $levSim = max(0, $levSim);

            // Substring
            $subSim = 0.0;
            if (mb_strlen($titleLow) > 8 && (str_contains($adrTitleLow, $titleLow) || str_contains($titleLow, $adrTitleLow))) {
                $subSim = 0.8;
            }

            // Keyword overlap
            $shared = array_intersect($autoKeywords, $adr['keywords']);
            $kwScore = count($autoKeywords) > 0 ? min(1.0, count($shared) / 8) : 0.0;

            $score = max($levSim, $subSim) * 0.5 + $kwScore * 0.5;
            if ($score > $melhorScore) {
                $melhorScore = $score;
                $melhor = ['slug' => $adr['slug'], 'cobertura' => $score];
            }
        }

        return $melhor && $melhorScore > 0.15 ? $melhor : null;
    }

    private function truncar(string $s, int $n): string
    {
        return mb_strlen($s) > $n ? mb_substr($s, 0, $n - 1) . '…' : $s;
    }
}
