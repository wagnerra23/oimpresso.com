<?php

namespace Modules\Copiloto\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Yaml\Yaml;

/**
 * MEM-KB-3 / F1 — Migra ADRs antigas pra schema canônico de frontmatter.
 *
 * Lê todas memory/decisions/*.md, infere os 8 campos obrigatórios a partir
 * do header existente (`**Status:**`, `**Data:**`, `**Decisores:**`, etc.),
 * e escreve frontmatter YAML no topo. Idempotente: ADR que já tem frontmatter
 * é pulada.
 *
 * Uso:
 *   php artisan mcp:adr:migrar-frontmatter --dry-run     # mostra preview
 *   php artisan mcp:adr:migrar-frontmatter               # grava
 *   php artisan mcp:adr:migrar-frontmatter --slug=0053-* # filtra
 *
 * Heurísticas (best-effort — Wagner revisa em PR batch):
 *   - status     : extrai de `**Status:**` → normaliza pra rascunho|proposto|aceito|deprecated|superseded
 *   - decided_at : extrai de `**Data:**` (várias formas) → ISO YYYY-MM-DD
 *   - decided_by : extrai de `**Decisores:**`/`**Decidido por:**` → iniciais TEAM
 *   - authority  : default `canonical` (todas ADRs aceitas histórico são canon)
 *   - lifecycle  : `substituido` se `**Supersede`/`Superseded` existir, senão `ativo`
 *   - module     : detectado pelo slug + path
 *   - quarter    : derivado de decided_at
 *   - title      : extrai de `# ADR NNNN — Título`
 *   - supersedes : parsea links `[ADR XXXX](XXXX-...)` em linhas Supersede
 */
class McpAdrMigrarFrontmatterCommand extends Command
{
    protected $signature = 'mcp:adr:migrar-frontmatter
                            {--dry-run        : Não grava — mostra preview do frontmatter inferido}
                            {--slug=          : Filtra por padrão glob (ex: "0053-*")}
                            {--force          : Sobrescreve frontmatter existente}';

    protected $description = 'Migra ADRs antigas pra schema canônico de frontmatter YAML (MEM-KB-3 / F1)';

    protected const STATUS_MAP = [
        'aceita'      => 'aceito',
        'aceito'      => 'aceito',
        'accepted'    => 'aceito',
        'aprovada'    => 'aceito',
        'aprovado'    => 'aceito',
        'proposed'    => 'proposto',
        'proposto'    => 'proposto',
        'proposta'    => 'proposto',
        'draft'       => 'rascunho',
        'rascunho'    => 'rascunho',
        'wip'         => 'rascunho',
        'em-análise'  => 'rascunho',
        'deprecated'  => 'deprecated',
        'depreciada'  => 'deprecated',
        'rejeitada'   => 'deprecated',
        'rejeitado'   => 'deprecated',
        'superseded'  => 'superseded',
        'substituída' => 'superseded',
        'substituida' => 'superseded',
    ];

    protected const TEAM_MAP = [
        'wagner'  => 'W', 'w'       => 'W', 'wr'      => 'W',
        'felipe'  => 'F', 'f'       => 'F',
        'maíra'   => 'M', 'maira'   => 'M', 'm'       => 'M',
        'luiz'    => 'L', 'l'       => 'L',
        'eliana'  => 'E', 'e'       => 'E',
    ];

    public function handle(): int
    {
        $dryRun  = (bool) $this->option('dry-run');
        $padrao  = (string) ($this->option('slug') ?? '*');
        $forcar  = (bool) $this->option('force');

        $base = base_path('memory/decisions');
        $arquivos = glob("$base/{$padrao}.md") ?: [];

        // Filtra README/_SCHEMA/_TEMPLATE/_INDEX
        $arquivos = array_filter($arquivos, function ($path) {
            $name = basename($path, '.md');
            return ! str_starts_with($name, '_') && $name !== 'README';
        });

        if (empty($arquivos)) {
            $this->error("Nenhuma ADR encontrada com padrão `{$padrao}`");
            return self::FAILURE;
        }

        $this->info(sprintf("Migrando %d ADR(s)%s", count($arquivos), $dryRun ? ' [DRY-RUN]' : ''));
        $this->newLine();

        $stats = ['migradas' => 0, 'puladas' => 0, 'erros' => 0];

        foreach ($arquivos as $path) {
            $slug = basename($path, '.md');
            $conteudo = file_get_contents($path);
            if ($conteudo === false) {
                $this->error("  ✗ {$slug}: erro lendo arquivo");
                $stats['erros']++;
                continue;
            }

            // Skip se já tem frontmatter (a menos que --force)
            $jaTemFm = preg_match('/^---\s*\n.*?\n---\s*\n/s', $conteudo);
            if ($jaTemFm && ! $forcar) {
                $this->line("  ⊘ {$slug}: já tem frontmatter (use --force pra sobrescrever)");
                $stats['puladas']++;
                continue;
            }

            $body = $jaTemFm
                ? preg_replace('/^---\s*\n.*?\n---\s*\n/s', '', $conteudo, 1)
                : $conteudo;

            $fm = $this->inferirFrontmatter($slug, $body);

            if ($dryRun) {
                $this->line("  ✓ {$slug}");
                $this->line($this->formatarFrontmatter($fm));
                $this->newLine();
                $stats['migradas']++;
                continue;
            }

            $novoConteudo = $this->formatarFrontmatter($fm) . "\n" . ltrim($body);
            if (file_put_contents($path, $novoConteudo) === false) {
                $this->error("  ✗ {$slug}: erro escrevendo");
                $stats['erros']++;
                continue;
            }
            $this->info("  ✓ {$slug}");
            $stats['migradas']++;
        }

        $this->newLine();
        $this->info(sprintf(
            "Resumo: %d migrada(s) · %d pulada(s) · %d erro(s)%s",
            $stats['migradas'], $stats['puladas'], $stats['erros'],
            $dryRun ? ' [DRY-RUN — nada foi gravado]' : ''
        ));

        return $stats['erros'] === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Infere os 8+ campos do frontmatter a partir do conteúdo da ADR.
     *
     * @return array<string, mixed>
     */
    protected function inferirFrontmatter(string $slug, string $body): array
    {
        $number = preg_match('/^(\d{4})-/', $slug, $m) ? (int) $m[1] : 0;
        $title  = $this->extrairTitulo($body, $slug);

        $statusBruto = $this->extrairLinha($body, ['Status', 'status']);
        $status      = $this->normalizarStatus($statusBruto) ?? 'aceito';

        $decidedAt = $this->normalizarData($this->extrairLinha($body, ['Data', 'Date', 'data']))
                  ?? $this->dataFallbackPorNumber($number);

        $decidedBy = $this->normalizarTeam(
            $this->extrairLinha($body, ['Decisores', 'Decidido por', 'Decisor', 'Autor', 'Owner', 'Owners'])
        );
        if (empty($decidedBy)) {
            $decidedBy = ['W']; // Wagner é decisor padrão histórico
        }

        $supersedes = $this->extrairSupersedes($body);
        $superseded = ! empty($supersedes) || $status === 'superseded';

        $supersededBy = []; // Não dá pra inferir esta direção do conteúdo da ADR antiga
                            // Wagner preenche manualmente em ADRs específicas

        $lifecycle = ($status === 'superseded' || $status === 'deprecated')
            ? 'substituido'
            : 'ativo';

        $module = $this->detectarModulo($slug, $body);

        $fm = [
            'slug'        => $slug,
            'number'      => $number,
            'title'       => $title,
            'type'        => 'adr',
            'status'      => $status,
            'authority'   => 'canonical',
            'lifecycle'   => $lifecycle,
            'decided_by'  => $decidedBy,
            'decided_at'  => $decidedAt,
        ];

        if ($module) {
            $fm['module'] = $module;
        }
        $fm['quarter'] = $this->derivarQuarter($decidedAt);
        $fm['tags'] = [];

        if (! empty($supersedes)) {
            $fm['supersedes'] = $supersedes;
        }
        if (! empty($supersededBy)) {
            $fm['superseded_by'] = $supersededBy;
        }

        $fm['related'] = $this->extrairRelated($body, $slug);
        $fm['pii']     = false;

        return $fm;
    }

    protected function extrairTitulo(string $body, string $slug): string
    {
        // Tenta `# ADR NNNN — Título` ou `# Título`
        if (preg_match('/^#\s+(?:ADR\s+\d{4}\s*[—–-]\s*)?(.+?)$/m', $body, $m)) {
            return trim($m[1]);
        }
        return $slug;
    }

    protected function extrairLinha(string $body, array $rotulos): ?string
    {
        foreach ($rotulos as $rotulo) {
            // Forma `**Rotulo:** valor` (Markdown bold)
            if (preg_match('/\*\*' . preg_quote($rotulo, '/') . '\s*:\s*\*\*\s*(.+?)$/mi', $body, $m)) {
                return trim($m[1]);
            }
            // Forma `Rotulo: valor` plain
            if (preg_match('/^' . preg_quote($rotulo, '/') . '\s*:\s*(.+?)$/mi', $body, $m)) {
                return trim($m[1]);
            }
        }
        return null;
    }

    protected function normalizarStatus(?string $bruto): ?string
    {
        if (! $bruto) return null;
        // Remove emojis comuns (✅ ❌ ⚠️ 🟢 etc) e markdown
        $limpo = trim(preg_replace('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}\*\(\)]/u', '', $bruto));
        $limpo = strtolower(trim($limpo));
        // Pega primeira palavra
        $primeira = preg_split('/\s+/', $limpo)[0] ?? '';

        return self::STATUS_MAP[$primeira] ?? null;
    }

    protected function normalizarData(?string $bruto): ?string
    {
        if (! $bruto) return null;
        // Tenta YYYY-MM-DD
        if (preg_match('/(\d{4})-(\d{2})-(\d{2})/', $bruto, $m)) {
            return "{$m[1]}-{$m[2]}-{$m[3]}";
        }
        // Tenta DD/MM/YYYY
        if (preg_match('/(\d{2})\/(\d{2})\/(\d{4})/', $bruto, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }
        // Tenta DD-MM-YYYY ou D de MMMM de YYYY (português)
        return null;
    }

    protected function dataFallbackPorNumber(int $number): string
    {
        // Fallback super grosseiro: ADRs 0001-0010 = abril 18, depois progride
        // Histórico real: 0001-~0021 entre 2026-04-18 e 2026-04-23
        // 0022-0040 entre 2026-04-24 e 2026-04-27
        // 0041-0061 entre 2026-04-28 e 2026-04-30
        if ($number === 0) return '2026-04-30';
        if ($number <= 21) return '2026-04-23';
        if ($number <= 40) return '2026-04-26';
        if ($number <= 61) return '2026-04-29';
        return '2026-04-30';
    }

    protected function normalizarTeam(?string $bruto): array
    {
        if (! $bruto) return [];
        $iniciais = [];
        // Encontra nomes ou iniciais separados por vírgula/espaço
        preg_match_all('/\b([A-ZÀ-Úa-zà-ú]+)\b/u', $bruto, $matches);
        foreach ($matches[1] as $token) {
            $tokenLower = strtolower($token);
            if (isset(self::TEAM_MAP[$tokenLower])) {
                $iniciais[self::TEAM_MAP[$tokenLower]] = true;
            }
        }
        return array_keys($iniciais);
    }

    /**
     * Procura linhas tipo "Supersede:" ou "Substitui:" e extrai slugs/numbers.
     *
     * @return array<string>
     */
    protected function extrairSupersedes(string $body): array
    {
        $slugs = [];
        $patterns = [
            '/(?:Supersede(?:s|d)?|Substitui|Substituída?)\s+(?:parcialmente\s+)?(?::|=)?\s*(.+?)$/mi',
        ];
        foreach ($patterns as $regex) {
            if (preg_match_all($regex, $body, $matches)) {
                foreach ($matches[1] as $linha) {
                    if (preg_match_all('/(\d{4})(?:-([a-z0-9-]+))?/', $linha, $refs)) {
                        for ($i = 0; $i < count($refs[0]); $i++) {
                            $num  = $refs[1][$i];
                            $rest = $refs[2][$i] ?? '';
                            $slugs[] = $rest ? "$num-$rest" : $num;
                        }
                    }
                }
            }
        }
        return array_values(array_unique($slugs));
    }

    /**
     * Extrai ADRs mencionadas no corpo (citações) — distintas das supersedes.
     *
     * @return array<string>
     */
    protected function extrairRelated(string $body, string $proprioSlug): array
    {
        $slugs = [];
        // Match ADR XXXX em prosa
        if (preg_match_all('/ADR\s+(\d{4})\b/', $body, $m)) {
            foreach ($m[1] as $num) {
                if ($num === substr($proprioSlug, 0, 4)) continue;
                $slugs[$num] = true;
            }
        }
        // Match links markdown `[..](NNNN-..)`
        if (preg_match_all('/\((\d{4}-[a-z0-9-]+)\.md\)/', $body, $m)) {
            foreach ($m[1] as $slug) {
                if (str_starts_with($slug, substr($proprioSlug, 0, 4))) continue;
                $slugs[$slug] = true;
            }
        }
        // Limita a 8 mais relevantes pra não inflar (Wagner refina depois)
        $resultado = array_keys($slugs);
        sort($resultado);
        return array_slice($resultado, 0, 8);
    }

    protected function detectarModulo(string $slug, string $body): ?string
    {
        $modulos = ['copiloto', 'financeiro', 'pontowr2', 'memcofre', 'cms', 'officeimpresso', 'connector', 'grow', 'infra'];
        $lower = strtolower($slug . ' ' . substr($body, 0, 500));
        foreach ($modulos as $m) {
            if (str_contains($lower, $m)) {
                return $m;
            }
        }
        return null;
    }

    protected function derivarQuarter(string $iso): string
    {
        $partes = explode('-', $iso);
        $ano = $partes[0] ?? '2026';
        $mes = (int) ($partes[1] ?? 1);
        $q = (int) ceil($mes / 3);
        return "{$ano}-Q{$q}";
    }

    protected function formatarFrontmatter(array $fm): string
    {
        // Symfony YAML inline=4 dá listas em block style + scalars curtos inline
        $yaml = Yaml::dump($fm, 4, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
        return "---\n" . rtrim($yaml) . "\n---";
    }
}
