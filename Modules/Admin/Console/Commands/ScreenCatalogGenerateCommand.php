<?php

declare(strict_types=1);

namespace Modules\Admin\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Yaml\Yaml;

/**
 * admin:ui-catalog-generate — Gera memory/requisitos/<Modulo>/UI-CATALOG.md
 *
 * Varre resources/js/Pages/<Modulo>/**\/*.tsx + lê irmãos .charter.md + .review.md
 * e produz tabela canônica de telas com status review + UX targets met + pendências.
 *
 * Schedule: daily 09:30 BRT (depois cron smoke 09:00) em app/Console/Kernel.php.
 *
 * Multi-tenant Tier 0 (ADR 0093): governance é repo-wide (não scoped business).
 * Catálogo UI é estrutural — varre filesystem, não DB.
 *
 * NOTA Tier 0: `--detail` (NÃO --verbose — Symfony reserved, ver .claude/rules/commands.md).
 *
 * @see memory/requisitos/_DesignSystem/CHARTER-TEMPLATE.md "Screen Review PDCA"
 * @see memory/decisions/0164-screen-review-pdca.md (W30-A)
 * @see memory/decisions/0101-sistema-charter-capterra-governanca-escopo.md
 */
class ScreenCatalogGenerateCommand extends Command
{
    protected $signature = 'admin:ui-catalog-generate
        {modulo? : Nome do módulo (ex: Admin, Financeiro). Omitir + --all pra todos.}
        {--all : Varre todos módulos com Pages Inertia}
        {--detail : Log detalhado por tela escaneada}
        {--dry-run : Imprime catálogo sem escrever arquivo}';

    protected $description = 'Gera memory/requisitos/<Modulo>/UI-CATALOG.md com índice telas + status review (ADR 0164 W30).';

    private const PAGES_BASE = 'resources/js/Pages';
    private const CATALOG_TARGET_BASE = 'memory/requisitos';

    public function handle(): int
    {
        $modulo = $this->argument('modulo');
        $all = (bool) $this->option('all');
        $detail = (bool) $this->option('detail');
        $dryRun = (bool) $this->option('dry-run');

        if (! $all && ! $modulo) {
            $this->error('Informe {modulo} OU use --all. Ex: php artisan admin:ui-catalog-generate Admin');
            return self::FAILURE;
        }

        $modules = $all ? $this->discoverAllModules() : [$modulo];

        if ($modules === []) {
            $this->warn('Nenhum módulo encontrado com Pages Inertia.');
            return self::SUCCESS;
        }

        $this->info('admin:ui-catalog-generate — ' . now()->toDateTimeString());
        $this->line('Módulos alvo: ' . implode(', ', $modules));
        $this->newLine();

        $totalTelas = 0;
        $totalCatalogos = 0;

        foreach ($modules as $mod) {
            $screens = $this->scanScreens($mod);
            $count = count($screens);
            $totalTelas += $count;

            if ($detail) {
                $this->line("  [{$mod}] {$count} telas escaneadas");
                foreach ($screens as $screen) {
                    $this->line("    - {$screen['relative_path']} (status: {$screen['review_status']}, round: {$screen['current_round']})");
                }
            }

            if ($count === 0) {
                $this->warn("  [{$mod}] sem telas — pulando catálogo.");
                continue;
            }

            $catalog = $this->buildCatalogMarkdown($mod, $screens);
            $target = base_path(self::CATALOG_TARGET_BASE . "/{$mod}/UI-CATALOG.md");

            if ($dryRun) {
                $this->line("--- DRY RUN ({$target}) ---");
                $this->line($catalog);
                $this->line('--- END DRY RUN ---');
            } else {
                $dir = dirname($target);
                if (! is_dir($dir)) {
                    @mkdir($dir, 0755, true);
                }
                file_put_contents($target, $catalog);
                $this->info("  [OK] UI-CATALOG.md gerado: memory/requisitos/{$mod}/UI-CATALOG.md ({$count} telas)");
                $totalCatalogos++;
            }
        }

        $this->newLine();
        $this->info("Resumo: {$totalTelas} telas escaneadas, {$totalCatalogos} catálogos gerados em " . count($modules) . ' módulos.');

        return self::SUCCESS;
    }

    /**
     * Descobre módulos com Pages Inertia (resources/js/Pages/<Mod>/).
     *
     * @return array<string>
     */
    private function discoverAllModules(): array
    {
        $base = base_path(self::PAGES_BASE);
        if (! is_dir($base)) {
            return [];
        }

        $modules = [];
        foreach (scandir($base) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $full = $base . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($full)) {
                $modules[] = $entry;
            }
        }
        sort($modules);
        return $modules;
    }

    /**
     * Varre resources/js/Pages/<Modulo>/ recursivamente por *.tsx
     * e correlaciona com irmãos .charter.md + .review.md.
     *
     * @return array<int, array{
     *   relative_path: string,
     *   tela_id: string,
     *   has_charter: bool,
     *   charter_status: string,
     *   review_status: string,
     *   current_round: int|string,
     *   last_smoke: string,
     *   ux_targets_met: string
     * }>
     */
    private function scanScreens(string $modulo): array
    {
        $base = base_path(self::PAGES_BASE . "/{$modulo}");
        if (! is_dir($base)) {
            return [];
        }

        $screens = [];
        $iter = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS));

        foreach ($iter as $file) {
            /** @var \SplFileInfo $file */
            if (! $file->isFile() || $file->getExtension() !== 'tsx') {
                continue;
            }
            // pular sub-components (_components/) e libs (_lib/)
            $relPath = str_replace('\\', '/', $file->getPathname());
            if (str_contains($relPath, '/_components/') || str_contains($relPath, '/_lib/')) {
                continue;
            }
            // pular arquivos test
            $name = $file->getFilename();
            if (str_ends_with($name, '.test.tsx') || str_ends_with($name, '.spec.tsx')) {
                continue;
            }

            $telaId = $modulo . '/' . str_replace('.tsx', '', substr($relPath, strpos($relPath, "/{$modulo}/") + strlen("/{$modulo}/")));
            $charterPath = str_replace('.tsx', '.charter.md', $file->getPathname());
            $reviewPath = str_replace('.tsx', '.review.md', $file->getPathname());

            $hasCharter = is_file($charterPath);
            $charterStatus = $hasCharter ? $this->parseStatus($charterPath) : 'no-charter';
            $reviewMeta = is_file($reviewPath) ? $this->parseReviewMeta($reviewPath) : null;

            $screens[] = [
                'relative_path' => str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getPathname()),
                'tela_id' => $telaId,
                'has_charter' => $hasCharter,
                'charter_status' => $charterStatus,
                'review_status' => $reviewMeta['status'] ?? 'no-review',
                'current_round' => $reviewMeta['current_round'] ?? '-',
                'last_smoke' => $reviewMeta['approved_at'] ?? ($reviewMeta['created_at'] ?? '-'),
                'ux_targets_met' => $reviewMeta['status'] === 'approved' ? '✓' : ($reviewMeta['status'] === 'rejected' ? '✗' : '⏸'),
            ];
        }

        usort($screens, fn ($a, $b) => strcmp($a['tela_id'], $b['tela_id']));
        return $screens;
    }

    /**
     * Lê frontmatter YAML do .charter.md e retorna campo `status` (draft|live|deprecated).
     */
    private function parseStatus(string $charterPath): string
    {
        $content = @file_get_contents($charterPath) ?: '';
        if (! preg_match('/^---\s*\n(.*?)\n---/s', $content, $m)) {
            return 'unknown';
        }
        try {
            $parsed = Yaml::parse($m[1]);
            return (string) ($parsed['status'] ?? 'unknown');
        } catch (\Throwable $e) {
            return 'parse-error';
        }
    }

    /**
     * Lê frontmatter YAML do .review.md.
     *
     * @return array<string, mixed>|null
     */
    private function parseReviewMeta(string $reviewPath): ?array
    {
        $content = @file_get_contents($reviewPath) ?: '';
        if (! preg_match('/^---\s*\n(.*?)\n---/s', $content, $m)) {
            return null;
        }
        try {
            $parsed = Yaml::parse($m[1]);
            return is_array($parsed) ? $parsed : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Renderiza UI-CATALOG.md canônico (tabela + pendências + cross-ref).
     */
    private function buildCatalogMarkdown(string $modulo, array $screens): string
    {
        $total = count($screens);
        $pendingWagner = collect($screens)->filter(fn ($s) => $s['review_status'] === 'pending-wagner')->count();
        $approved = collect($screens)->filter(fn ($s) => $s['review_status'] === 'approved')->count();
        $rejected = collect($screens)->filter(fn ($s) => $s['review_status'] === 'rejected')->count();
        $noCharter = collect($screens)->filter(fn ($s) => ! $s['has_charter'])->count();
        $noReview = collect($screens)->filter(fn ($s) => $s['review_status'] === 'no-review')->count();

        $now = now()->toDateString();

        $md = "# {$modulo} — UI Catalog\n\n";
        $md .= "> Gerado por `php artisan admin:ui-catalog-generate {$modulo}` — daily 09:30 BRT.\n";
        $md .= "> Última geração: {$now}\n\n";
        $md .= "## Telas ({$total})\n\n";
        $md .= "| Tela | Charter | Status review | Round | Última smoke | UX targets |\n";
        $md .= "|---|---|---|---:|---|:---:|\n";

        foreach ($screens as $s) {
            $charterCell = $s['has_charter'] ? $s['charter_status'] : '**sem charter**';
            $statusCell = $s['review_status'];
            $smokeCell = $s['last_smoke'] === '-' ? '(pendente)' : $s['last_smoke'];
            $md .= "| {$s['tela_id']} | {$charterCell} | {$statusCell} | {$s['current_round']} | {$smokeCell} | {$s['ux_targets_met']} |\n";
        }

        $md .= "\n## Pendências\n\n";
        if ($pendingWagner > 0) {
            $md .= "- **{$pendingWagner} telas pending-wagner** — aguardando Wagner aprovar smoke ou desvios\n";
        }
        if ($rejected > 0) {
            $md .= "- **{$rejected} telas rejected** — precisam iteração antes próximo merge\n";
        }
        if ($noCharter > 0) {
            $md .= "- **{$noCharter} telas SEM charter** — rodar `/charter-write resources/js/Pages/{$modulo}/<Tela>.tsx`\n";
        }
        if ($noReview > 0) {
            $md .= "- **{$noReview} telas SEM review.md** — skill `tela-smoke-pos-merge` (Tier B) cria no próximo merge\n";
        }
        if ($pendingWagner === 0 && $rejected === 0 && $noCharter === 0 && $noReview === 0) {
            $md .= "- (sem pendências — todas telas aprovadas)\n";
        }

        $md .= "\n## Cross-ref\n\n";
        $md .= "- [CHARTER-TEMPLATE.md](../_DesignSystem/CHARTER-TEMPLATE.md) — template canônico\n";
        $md .= "- [RUNBOOK-charters-s4-ativacao.md](../_DesignSystem/RUNBOOK-charters-s4-ativacao.md) — workflow draft→live\n";
        $md .= "- ADR 0164 — Screen Review PDCA (W30)\n";
        $md .= "- ADR 0101 — Sistema Charter-Capterra\n";
        $md .= "- Skill `charter-first` (Tier A) · `tela-smoke-pos-merge` (Tier B — W30)\n";

        $md .= "\n## Estatísticas\n\n";
        $md .= "- Total telas: {$total}\n";
        $md .= "- Approved: {$approved}\n";
        $md .= "- Pending Wagner: {$pendingWagner}\n";
        $md .= "- Rejected: {$rejected}\n";
        $md .= "- Sem charter: {$noCharter}\n";
        $md .= "- Sem review: {$noReview}\n";

        return $md;
    }
}
