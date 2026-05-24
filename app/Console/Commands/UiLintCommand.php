<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Finder\Finder;

/**
 * `ui:lint` — Onda 1.2 do AUTOMATION-ROADMAP (Constituição UI v2).
 *
 * Aplica regras de lint UI sobre `resources/js/` (Pages + Components) para
 * detectar anti-padrões catalogados em PRE-MERGE-UI.md camada 4:
 *
 *   R1 — Cor hardcoded (`#hex` literal ou `bg-COR-NNN` Tailwind literal em
 *        arquivo Page) · use tokens semânticos / vars CSS (AP1 PRE-MERGE-UI)
 *   R2 — FontAwesome em Page/Component shared · use lucide-react (ADR UI-0003)
 *   R3 — Emoji em UI de produto · use lucide icon (R-DS PRE-MERGE-UI AP6)
 *
 * Exit codes:
 *   0  zero violações (CI/local OK)
 *   1  >=1 violação detectada (apenas com --strict; sem --strict é warning)
 *
 * Uso típico:
 *   php artisan ui:lint                          # summary, exit 0
 *   php artisan ui:lint --detail                 # mostra path:linha:match
 *   php artisan ui:lint --strict                 # exit 1 se hits > 0
 *   php artisan ui:lint --path=resources/js/Pages/Cliente
 *   php artisan ui:lint --rule=R1                # apenas regra R1
 *
 * Refs:
 *   - ADR UI-0013 (Constituição UI v2 · hierarquia 4 camadas)
 *   - PRE-MERGE-UI.md (anti-padrões AP1-AP8)
 *   - AUTOMATION-ROADMAP.md Onda 1 Item 1.2
 */
class UiLintCommand extends Command
{
    protected $signature = 'ui:lint
                            {--path=resources/js : Path a escanear (relativo a base_path)}
                            {--strict : Exit 1 se houver violação (CI mode)}
                            {--detail : Lista cada violação com path:linha:match}
                            {--rule= : Apenas regra(s) específica(s) (ex: R1,R2)}
                            {--baseline= : Arquivo JSON baseline (ratchet) · falha só se hits aumentarem vs baseline}
                            {--write-baseline= : Grava baseline JSON com estado atual (use depois de aceitar baseline existente)}
                            {--changed-only : Apenas arquivos modificados vs origin/main (uso em pre-commit hook)}';

    protected $description = 'Lint UI · detecta anti-padrões UI (cor crua, FontAwesome, emoji) — Constituição UI v2';

    /**
     * Catálogo das regras ativas. Onda 1.2 entrega R1, R2, R3.
     * Ondas futuras adicionam R4 (PT-01 slots) · R5 (origens ≠5) · R6 (LGPD copy) · etc.
     */
    private const RULES = [
        'R1' => 'cor crua (hex literal ou bg-COR-NNN em Page)',
        'R2' => 'FontAwesome (lucide-only · ADR UI-0003)',
        'R3' => 'emoji em UI de produto (use lucide icon)',
        'R4' => 'PT-01 Lista · Index.tsx sem PageHeader OU sem DataTable shared',
        'R5' => 'origens canon · CSS define apenas 5 origins (OS/CRM/FIN/PNT/MFG)',
    ];

    /**
     * Cores Tailwind padrão (paleta). Detectamos `bg-COR-NNN` / `text-COR-NNN` /
     * `border-COR-NNN` literais em arquivos Page — devem usar tokens semânticos
     * (`bg-accent`, `text-foreground`, etc).
     */
    private const TAILWIND_COLORS = [
        'slate', 'gray', 'zinc', 'neutral', 'stone', 'red', 'orange', 'amber',
        'yellow', 'lime', 'green', 'emerald', 'teal', 'cyan', 'sky', 'blue',
        'indigo', 'violet', 'purple', 'fuchsia', 'pink', 'rose',
    ];

    /**
     * Hex literais permitidos (branco/preto puros + algumas exceções comuns).
     */
    private const HEX_ALLOWED = ['#ffffff', '#000000', '#fff', '#000'];

    /**
     * @return int Exit code (0 ok · 1 falha)
     */
    public function handle(): int
    {
        $path = (string) $this->option('path');
        $strict = (bool) $this->option('strict');
        $detail = (bool) $this->option('detail');
        $ruleFilter = $this->parseRuleFilter((string) ($this->option('rule') ?? ''));
        $baselineFile = (string) ($this->option('baseline') ?? '');
        $writeBaseline = (string) ($this->option('write-baseline') ?? '');
        $changedOnly = (bool) $this->option('changed-only');

        $base = base_path($path);
        if (! is_dir($base)) {
            $this->error("Path não encontrado: {$path}");

            return self::FAILURE;
        }

        $this->line("<info>ui:lint</info> · scan em <comment>{$path}</comment>");
        if ($ruleFilter !== []) {
            $this->line('  regras filtradas: '.implode(', ', $ruleFilter));
        }
        if ($changedOnly) {
            $this->line('  modo --changed-only · apenas arquivos vs origin/main');
        }
        if ($baselineFile !== '') {
            $this->line("  baseline ratchet: <comment>{$baselineFile}</comment>");
        }
        $this->newLine();

        // Lista de arquivos a escanear (--changed-only filtra)
        $changedFiles = $changedOnly ? $this->getChangedFiles() : null;
        if ($changedOnly && $changedFiles !== null && $changedFiles === []) {
            $this->info('✓ Nenhum arquivo UI modificado vs origin/main');

            return self::SUCCESS;
        }

        $violations = [];
        $filesScanned = 0;

        $finder = new Finder;
        $finder->files()
            ->in($base)
            ->name(['*.tsx', '*.jsx'])
            ->notPath('node_modules')
            ->notPath('vendor')
            ->notPath('tests');

        foreach ($finder as $file) {
            $relPath = $this->relativePath($file->getRealPath());

            // Filtro --changed-only
            if ($changedFiles !== null && ! in_array($this->normalizePath($relPath), $changedFiles, true)) {
                continue;
            }

            $filesScanned++;
            $content = file_get_contents($file->getRealPath());
            $lines = explode("\n", $content);

            if ($this->ruleEnabled('R1', $ruleFilter)) {
                $violations = [...$violations, ...$this->checkR1($relPath, $lines)];
            }
            if ($this->ruleEnabled('R2', $ruleFilter)) {
                $violations = [...$violations, ...$this->checkR2($relPath, $lines)];
            }
            if ($this->ruleEnabled('R3', $ruleFilter)) {
                $violations = [...$violations, ...$this->checkR3($relPath, $content)];
            }
            if ($this->ruleEnabled('R4', $ruleFilter)) {
                $violations = [...$violations, ...$this->checkR4($relPath, $content)];
            }
        }

        // R5 — origens canon CSS (escaneia 1 vez, não por arquivo .tsx)
        if ($this->ruleEnabled('R5', $ruleFilter)) {
            $violations = [...$violations, ...$this->checkR5()];
        }

        // --write-baseline: grava estado atual como baseline aceito
        if ($writeBaseline !== '') {
            return $this->writeBaselineFile($writeBaseline, $violations, $filesScanned);
        }

        // --baseline: compara com baseline aceito (ratchet)
        $baselineData = null;
        if ($baselineFile !== '') {
            $baselineData = $this->loadBaseline($baselineFile);
            if ($baselineData === null) {
                $this->warn("Baseline {$baselineFile} não encontrado · usando modo strict (falha em qualquer hit)");
            }
        }

        return $this->reportAndExit($violations, $filesScanned, $strict, $detail, $baselineData);
    }

    /**
     * Lista arquivos UI modificados vs origin/main via `git diff --name-only`.
     * Usado pelo modo --changed-only (pre-commit hook).
     *
     * @return array<int, string>|null Null se git indisponível (fallback full scan)
     */
    private function getChangedFiles(): ?array
    {
        $cmd = 'git diff --name-only origin/main HEAD 2>&1';
        $output = [];
        $exitCode = 0;
        exec($cmd, $output, $exitCode);
        if ($exitCode !== 0) {
            $this->warn('git diff falhou · não consegui detectar arquivos modificados');

            return null;
        }

        // Inclui também arquivos staged (uncommitted) pra cobrir pre-commit
        $stagedOutput = [];
        exec('git diff --name-only --staged 2>&1', $stagedOutput);
        exec('git diff --name-only 2>&1', $unstagedOutput);

        $all = array_unique([...$output, ...$stagedOutput, ...$unstagedOutput]);

        // Filtrar só arquivos .tsx/.jsx do path scan
        return array_values(array_filter(
            array_map(fn (string $p): string => $this->normalizePath($p), $all),
            fn (string $p): bool => preg_match('/\.(tsx|jsx)$/', $p) === 1
        ));
    }

    /**
     * Grava baseline JSON com violations agrupadas por arquivo+regra.
     *
     * @param  array<int, array{rule:string, file:string, line:int, match:string, detail:string}>  $violations
     */
    private function writeBaselineFile(string $filename, array $violations, int $filesScanned): int
    {
        $abs = base_path($filename);

        // Group: file → rule → count
        // PATH NORMALIZATION: gravar paths sempre com forward-slash pra cross-platform
        // (Windows gera `\`, Linux/CI gera `/` · sem normalização baseline não bate em CI).
        $grouped = [];
        foreach ($violations as $v) {
            $key = $this->normalizePath($v['file']);
            $grouped[$key][$v['rule']] = ($grouped[$key][$v['rule']] ?? 0) + 1;
        }

        $payload = [
            '_meta' => [
                'generated_at' => date('c'),
                'tool' => 'ui:lint',
                'files_scanned' => $filesScanned,
                'total_violations' => count($violations),
                'rules' => self::RULES,
            ],
            'files' => $grouped,
        ];

        $dir = dirname($abs);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($abs, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->info("✓ Baseline gravado em {$filename}");
        $this->line(sprintf('  %d violações em %d arquivos baseline', count($violations), count($grouped)));
        $this->line('  Próximos ui:lint --baseline='.basename($filename).' só falham se hits AUMENTAREM');

        return self::SUCCESS;
    }

    /**
     * Carrega baseline JSON. Retorna null se inexistente/inválido.
     *
     * @return array<string, array<string, int>>|null
     */
    private function loadBaseline(string $filename): ?array
    {
        $abs = base_path($filename);
        if (! is_file($abs)) {
            return null;
        }

        $raw = file_get_contents($abs);
        $data = json_decode($raw, true);
        if (! is_array($data) || ! isset($data['files'])) {
            $this->warn("Baseline {$filename} mal-formado");

            return null;
        }

        return $data['files'];
    }

    private function normalizePath(string $path): string
    {
        return str_replace('\\', '/', $path);
    }

    /**
     * R1 · cor crua — hex literal (exceto branco/preto puros) e `bg-COR-NNN`
     * Tailwind literal em arquivos Page.
     *
     * @return array<int, array{rule:string, file:string, line:int, match:string, detail:string}>
     */
    private function checkR1(string $relPath, array $lines): array
    {
        $out = [];
        $tailwindRegex = '/\b(bg|text|border|ring|fill|stroke|from|to|via|placeholder|caret|accent|decoration|shadow|outline|divide)-('
            .implode('|', self::TAILWIND_COLORS).')-(\d{2,3})\b/';
        $hexRegex = '/#[0-9a-fA-F]{3,8}\b/';

        foreach ($lines as $i => $line) {
            // Skip block comments / line comments
            if (preg_match('/^\s*(\/\/|\*|\/\*)/', $line)) {
                continue;
            }

            if (preg_match_all($tailwindRegex, $line, $m)) {
                foreach ($m[0] as $match) {
                    $out[] = [
                        'rule' => 'R1',
                        'file' => $relPath,
                        'line' => $i + 1,
                        'match' => $match,
                        'detail' => 'Tailwind color literal · use token semântico (bg-accent, text-foreground, etc)',
                    ];
                }
            }

            if (preg_match_all($hexRegex, $line, $m)) {
                foreach ($m[0] as $match) {
                    if (in_array(strtolower($match), self::HEX_ALLOWED, true)) {
                        continue;
                    }
                    $out[] = [
                        'rule' => 'R1',
                        'file' => $relPath,
                        'line' => $i + 1,
                        'match' => $match,
                        'detail' => 'Hex literal · use oklch() token ou CSS var',
                    ];
                }
            }
        }

        return $out;
    }

    /**
     * R2 · FontAwesome · não permitido (lucide-only por UI-0003).
     *
     * @return array<int, array{rule:string, file:string, line:int, match:string, detail:string}>
     */
    private function checkR2(string $relPath, array $lines): array
    {
        $out = [];
        $regex = '/(@fortawesome|FontAwesomeIcon\b|font-awesome\b)/i';

        foreach ($lines as $i => $line) {
            if (preg_match($regex, $line, $m)) {
                $out[] = [
                    'rule' => 'R2',
                    'file' => $relPath,
                    'line' => $i + 1,
                    'match' => $m[0],
                    'detail' => 'FontAwesome detectado · use lucide-react (ADR UI-0003)',
                ];
            }
        }

        return $out;
    }

    /**
     * R3 · emoji em UI de produto · use lucide icon.
     * Detecta emojis pictográficos. EXCLUI text-style symbols (✓ ✗ ⚠ → ←
     * ★ ☆ ♥ etc) que são caracteres texto, não emojis decorativos.
     * Range incluído:
     *   - \x{1F300}-\x{1F9FF}  Misc symbols + pictographs (🌀-🛀, animais, food)
     *   - \x{1F600}-\x{1F64F}  Emoticons (😀-🙏)
     *   - \x{1F1E6}-\x{1F1FF}  Regional flags (🇧🇷)
     *   - \x{1FA70}-\x{1FAFF}  Symbols/pictographs extended-A
     * Range excluído (são texto, não emoji):
     *   - \x{2600}-\x{26FF}    Miscellaneous symbols (✓ ✗ ⚠ ☀ ☎ ★ — caracteres texto)
     *   - \x{2700}-\x{27BF}    Dingbats (✂ ✈ ✓ ✗ — caracteres texto)
     * Exclui emojis em comments (linhas começando com `//`, `*`, `/*`).
     *
     * @return array<int, array{rule:string, file:string, line:int, match:string, detail:string}>
     */
    private function checkR3(string $relPath, string $content): array
    {
        $out = [];
        $regex = '/(?:[\x{1F300}-\x{1F9FF}]|[\x{1F600}-\x{1F64F}]|[\x{1F1E6}-\x{1F1FF}]|[\x{1FA70}-\x{1FAFF}])/u';

        if (! preg_match_all($regex, $content, $matches, PREG_OFFSET_CAPTURE)) {
            return $out;
        }

        $lines = explode("\n", $content);

        foreach ($matches[0] as $match) {
            [$char, $offset] = $match;
            $lineNum = substr_count(substr($content, 0, (int) $offset), "\n") + 1;
            $lineText = $lines[$lineNum - 1] ?? '';

            // Skip se está em linha de comment
            if (preg_match('/^\s*(\/\/|\*|\/\*)/', $lineText)) {
                continue;
            }

            $out[] = [
                'rule' => 'R3',
                'file' => $relPath,
                'line' => $lineNum,
                'match' => $char,
                'detail' => 'Emoji em UI · use lucide icon (Constituição UI v2 · sem emoji em produto)',
            ];
        }

        return $out;
    }

    /**
     * R4 · PT-01 Lista · arquivos `Pages/<X>/Index.tsx` devem importar PageHeader
     * (Slot 1) e DataTable shared (Slot 5). Componentes shared ficam em
     * `@/Components/shared/` — import path tem que aparecer no source.
     *
     * Detecta apenas Index.tsx (não Show/Edit/Create/etc — esses são outros PTs).
     * Não roda se arquivo não está em Pages/ direto (ex: nested _drawer, _form).
     *
     * @return array<int, array{rule:string, file:string, line:int, match:string, detail:string}>
     */
    private function checkR4(string $relPath, string $content): array
    {
        $out = [];
        $normalized = $this->normalizePath($relPath);

        // Apenas Pages/<X>/Index.tsx (não nested _drawer/_form/_show)
        if (! preg_match('#^resources/js/Pages/[^/]+/Index\.tsx$#', $normalized)) {
            return $out;
        }

        // Permite alguns Index.tsx sem PT-01 (módulos que não são lista):
        // - Pages/Home/Index.tsx (dashboard, PT-04 futuro)
        // - Pages/Jana/Index.tsx (chat, custom)
        $skipPaths = [
            'resources/js/Pages/Home/Index.tsx',
            'resources/js/Pages/Jana/Index.tsx',
            'resources/js/Pages/Settings/Index.tsx',
            'resources/js/Pages/Modules/Index.tsx',
        ];
        if (in_array($normalized, $skipPaths, true)) {
            return $out;
        }

        $hasPageHeader = $this->hasSharedImport($content, 'PageHeader')
            || str_contains($content, '<PageHeader');
        $hasDataTable = $this->hasSharedImport($content, 'DataTable')
            || str_contains($content, '<DataTable');

        if (! $hasPageHeader) {
            $out[] = [
                'rule' => 'R4',
                'file' => $relPath,
                'line' => 1,
                'match' => 'no <PageHeader>',
                'detail' => 'PT-01 Slot 1 ausente · Index.tsx tem que importar PageHeader (Components/shared)',
            ];
        }
        if (! $hasDataTable) {
            $out[] = [
                'rule' => 'R4',
                'file' => $relPath,
                'line' => 1,
                'match' => 'no <DataTable>',
                'detail' => 'PT-01 Slot 5 ausente · Index.tsx tem que importar DataTable (Components/shared) OU justificar via .charter.md',
            ];
        }

        return $out;
    }

    /**
     * Helper · verifica se arquivo importa componente do shared.
     * Aceita variantes:
     *   - `import { PageHeader } from '@/Components/shared/...'`
     *   - `import { PageHeader } from "@/Components/shared/PageHeader"`
     *   - `import PageHeader from '...'`
     */
    private function hasSharedImport(string $content, string $component): bool
    {
        $patterns = [
            '/import\s+\{[^}]*\b'.preg_quote($component, '/').'\b[^}]*\}\s+from\s+[\'"]@\/Components\/shared/',
            '/import\s+'.preg_quote($component, '/').'\s+from\s+[\'"]@\/Components\/shared/',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }

        return false;
    }

    /**
     * R5 · CSS canon (`resources/css/cockpit.css` + `inertia.css`) define
     * apenas 5 origins canônicas: OS, CRM, FIN, PNT, MFG. Qualquer `--origin-<X>-`
     * com X diferente disso é violação.
     *
     * @return array<int, array{rule:string, file:string, line:int, match:string, detail:string}>
     */
    private function checkR5(): array
    {
        $out = [];
        $expected = ['OS', 'CRM', 'FIN', 'PNT', 'MFG'];
        $cssFiles = [
            'resources/css/cockpit.css',
            'resources/css/inertia.css',
        ];

        foreach ($cssFiles as $cssPath) {
            $abs = base_path($cssPath);
            if (! is_file($abs)) {
                continue;
            }

            $content = file_get_contents($abs);
            $lines = explode("\n", $content);

            foreach ($lines as $i => $line) {
                // Procura --origin-<X>-bg ou --origin-<X>-fg ou --origin-<X>-border
                if (preg_match_all('/--origin-([A-Z0-9_]+)-(bg|fg|border|soft)\b/', $line, $matches)) {
                    foreach ($matches[1] as $idx => $origin) {
                        if (! in_array($origin, $expected, true)) {
                            $out[] = [
                                'rule' => 'R5',
                                'file' => $cssPath,
                                'line' => $i + 1,
                                'match' => '--origin-'.$origin.'-'.$matches[2][$idx],
                                'detail' => sprintf(
                                    'Origin "%s" fora do canon · permitidos: %s',
                                    $origin,
                                    implode(', ', $expected)
                                ),
                            ];
                        }
                    }
                }
            }
        }

        return $out;
    }

    /**
     * @param  array<int, array{rule:string, file:string, line:int, match:string, detail:string}>  $violations
     * @param  array<string, array<string, int>>|null  $baseline
     */
    private function reportAndExit(array $violations, int $filesScanned, bool $strict, bool $detail, ?array $baseline = null): int
    {
        if ($violations === []) {
            $this->info("Ok · ui:lint passou · 0 violacoes em {$filesScanned} arquivos");

            return self::SUCCESS;
        }

        // Modo baseline ratchet: comparar hits atuais vs baseline · só falha em REGRESSÃO
        if ($baseline !== null) {
            return $this->reportRatchet($violations, $filesScanned, $strict, $detail, $baseline);
        }

        $uniqueFiles = count(array_unique(array_column($violations, 'file')));
        $this->warn(sprintf(
            '%d violação(ões) em %d arquivo(s) · scan total: %d arquivos',
            count($violations),
            $uniqueFiles,
            $filesScanned
        ));
        $this->newLine();

        $byRule = [];
        foreach ($violations as $v) {
            $byRule[$v['rule']][] = $v;
        }
        ksort($byRule);

        foreach ($byRule as $rule => $items) {
            $count = count($items);
            $desc = self::RULES[$rule] ?? '?';
            $this->line("<fg=red>{$rule}</fg=red> · {$desc} · <comment>{$count} hits</comment>");

            if ($detail) {
                foreach ($items as $v) {
                    $this->line(sprintf('  %s:%d → %s', $v['file'], $v['line'], $v['match']));
                    $this->line(sprintf('    <fg=gray>%s</fg=gray>', $v['detail']));
                }
            } else {
                $byFile = [];
                foreach ($items as $v) {
                    $byFile[$v['file']] = ($byFile[$v['file']] ?? 0) + 1;
                }
                arsort($byFile);
                $top = array_slice($byFile, 0, 5, true);
                foreach ($top as $file => $n) {
                    $this->line("  {$file} ({$n} hits)");
                }
                $remaining = count($byFile) - count($top);
                if ($remaining > 0) {
                    $this->line(sprintf('  ... + %d arquivo(s) (use --detail)', $remaining));
                }
            }
            $this->newLine();
        }

        $this->line('<comment>Detalhe completo:</comment> php artisan ui:lint --detail');
        $this->line('<comment>Refs:</comment> Constituição UI v2 · ADR UI-0013 · PRE-MERGE-UI.md');

        return $strict ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Ratchet · compara hits atuais com baseline. Falha apenas em regressão
     * (hits aumentaram OU arquivo novo violou regra que estava limpa).
     *
     * @param  array<int, array{rule:string, file:string, line:int, match:string, detail:string}>  $violations
     * @param  array<string, array<string, int>>  $baseline
     */
    private function reportRatchet(array $violations, int $filesScanned, bool $strict, bool $detail, array $baseline): int
    {
        // Current: file → rule → count
        // PATH NORMALIZATION: força forward-slash pra match com baseline gravado
        // cross-platform (Windows local vs Linux CI).
        $current = [];
        foreach ($violations as $v) {
            $file = $this->normalizePath($v['file']);
            $current[$file][$v['rule']] = ($current[$file][$v['rule']] ?? 0) + 1;
        }

        $regressions = [];
        $improvements = [];

        foreach ($current as $file => $rules) {
            foreach ($rules as $rule => $count) {
                $baselineCount = $baseline[$file][$rule] ?? 0;
                if ($count > $baselineCount) {
                    $regressions[] = [
                        'file' => $file,
                        'rule' => $rule,
                        'baseline' => $baselineCount,
                        'current' => $count,
                        'delta' => $count - $baselineCount,
                    ];
                }
            }
        }

        // Improvements (arquivos que ficaram mais limpos)
        foreach ($baseline as $file => $rules) {
            foreach ($rules as $rule => $baselineCount) {
                $currentCount = $current[$file][$rule] ?? 0;
                if ($currentCount < $baselineCount) {
                    $improvements[] = [
                        'file' => $file,
                        'rule' => $rule,
                        'baseline' => $baselineCount,
                        'current' => $currentCount,
                        'delta' => $currentCount - $baselineCount,
                    ];
                }
            }
        }

        $totalCurrent = count($violations);
        $totalBaseline = array_sum(array_map(fn ($r): int => array_sum($r), $baseline));

        $this->newLine();
        $this->line(sprintf(
            'Baseline: <comment>%d</comment> violações · Atual: <comment>%d</comment> · Delta: <comment>%+d</comment>',
            $totalBaseline,
            $totalCurrent,
            $totalCurrent - $totalBaseline
        ));

        if ($improvements !== []) {
            $this->newLine();
            $this->info('Improvements (reduções vs baseline):');
            foreach (array_slice($improvements, 0, 10) as $imp) {
                $this->line(sprintf('  %s · %s · %d → %d (Δ%+d)', $imp['file'], $imp['rule'], $imp['baseline'], $imp['current'], $imp['delta']));
            }
            if (count($improvements) > 10) {
                $this->line(sprintf('  ... + %d arquivo(s) melhoraram', count($improvements) - 10));
            }
        }

        if ($regressions === []) {
            $this->newLine();
            $this->info('Ok · sem regressões vs baseline');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->error(sprintf('REGRESSÃO · %d arquivo(s) com hits AUMENTADOS:', count($regressions)));
        foreach ($regressions as $reg) {
            $this->line(sprintf(
                '  <fg=red>%s</fg=red> · %s · %d → %d (Δ%+d)',
                $reg['file'],
                $reg['rule'],
                $reg['baseline'],
                $reg['current'],
                $reg['delta']
            ));
        }

        $this->newLine();
        $this->line('Pra ver detalhes: <comment>php artisan ui:lint --detail</comment>');
        $this->line('Pra atualizar baseline (se regressão é intencional): <comment>php artisan ui:lint --write-baseline=storage/ui-lint-baseline.json</comment>');

        return $strict ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Parse `--rule=R1,R2` em array.
     *
     * @return array<int, string>
     */
    private function parseRuleFilter(string $raw): array
    {
        if ($raw === '') {
            return [];
        }

        return array_filter(
            array_map('trim', explode(',', strtoupper($raw))),
            fn (string $r): bool => array_key_exists($r, self::RULES)
        );
    }

    /**
     * @param  array<int, string>  $filter
     */
    private function ruleEnabled(string $rule, array $filter): bool
    {
        return $filter === [] || in_array($rule, $filter, true);
    }

    private function relativePath(string $abs): string
    {
        $base = base_path().DIRECTORY_SEPARATOR;

        return str_replace($base, '', $abs);
    }
}
