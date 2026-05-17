<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Admin\Http\Requests\UpdateReviewStatusRequest;
use Modules\Admin\Services\AdminAuditLogger;
use Modules\Governance\Services\InitiativeService;
use Symfony\Component\Yaml\Yaml;

/**
 * W30 Agent B (2026-05-17) — Screen Review tri-pane PDCA Wagner-only.
 *
 * Lista TODAS telas do projeto (`resources/js/Pages/**\/*.tsx`) com status PDCA:
 *  - pending-wagner (default — sem `<Tela>.review.md`)
 *  - approved (último round status=approved)
 *  - rejected (último round status=rejected — pode disparar Initiative)
 *  - iterate (último round status=iterate — em loop F1.5)
 *
 * Charter ao lado (`<Tela>.charter.md`) é fonte canônica de UX targets +
 * Mission. Reviews ficam em `<Tela>.review.md` append-only (NUNCA edita
 * round anterior — preserva histórico Wagner-Claude loop).
 *
 * Tier 0:
 *  - IsWagner middleware (governance repo-wide intencional)
 *  - Multi-tenant cross-tenant: SUPERADMIN governance repo-wide
 *  - Append-only `<Tela>.review.md` (cada updateStatus = novo round bloco YAML)
 *  - Inertia::defer em props caras (modules + screens — glob 200+ arquivos)
 *
 * @see resources/js/Pages/Admin/ScreenReview.charter.md
 * @see Modules/Governance/Services/InitiativeService::createFromScorecardBreach
 * @see Modules/Admin/Services/AdminAuditLogger
 */
class ScreenReviewController extends Controller
{
    /** Statuses válidos PDCA. */
    public const STATUS_PENDING = 'pending-wagner';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_ITERATE = 'iterate';

    /** Base path scan — relative to base_path() */
    private const PAGES_ROOT = 'resources/js/Pages';

    public function __construct(
        protected AdminAuditLogger $audit,
        protected ?InitiativeService $initiatives = null,
    ) {
        $this->initiatives ??= app(InitiativeService::class);
    }

    /**
     * GET /admin/screen-review — render tri-pane.
     */
    public function index(): Response
    {
        $counts = $this->countAllStatuses();

        return Inertia::render('Admin/ScreenReview', [
            'meta' => [
                'generated_at' => now()->toIso8601String(),
                'total_telas' => $counts['total'],
                'pending_count' => $counts[self::STATUS_PENDING],
                'approved_count' => $counts[self::STATUS_APPROVED],
                'rejected_count' => $counts[self::STATUS_REJECTED],
                'iterate_count' => $counts[self::STATUS_ITERATE],
                'pending_over_7d' => $counts['pending_over_7d'],
            ],
            'modules' => Inertia::defer(fn () => $this->buildModulesPayload()),
            'screens' => Inertia::defer(fn () => $this->buildScreensPayload()),
        ]);
    }

    /**
     * POST /admin/screen-review/{screenPath}/status — append round.
     *
     * @param string $screenPath URL-encoded path tipo "Admin%2FGovernanceV4" (sem .tsx)
     */
    public function updateStatus(UpdateReviewStatusRequest $request, string $screenPath): RedirectResponse
    {
        $decoded = urldecode($screenPath);
        $tsxRelative = self::PAGES_ROOT.'/'.$decoded.'.tsx';
        $tsxFull = base_path($tsxRelative);

        if (! File::exists($tsxFull)) {
            return back()->withErrors(['screenPath' => "Tela inexistente: {$decoded}"]);
        }

        $reviewPath = $this->reviewPathFor($tsxFull);
        $round = $this->nextRoundNumber($reviewPath);

        $status = $request->string('status')->toString();
        $notes = $request->string('notes')->toString();
        $desvios = $request->input('desvios', []);

        // Append-only: monta bloco YAML novo + concatena (NUNCA reescreve anteriores)
        $block = $this->renderRoundBlock(
            round: $round,
            status: $status,
            user: optional($request->user())->name ?? 'Wagner',
            notes: $notes,
            desvios: $desvios,
        );

        if (File::exists($reviewPath)) {
            $existing = File::get($reviewPath);
            File::put($reviewPath, $existing."\n\n".$block);
        } else {
            $header = "# Reviews — {$decoded}\n\n".
                      "> Append-only PDCA Wagner-Claude loop. Cada round = bloco YAML novo.\n".
                      "> Charter canônico ao lado em `{$decoded}.charter.md`.\n";
            File::put($reviewPath, $header."\n".$block);
        }

        // Initiative governance se rejected (opt-in)
        $initiativeCreated = false;
        if ($status === self::STATUS_REJECTED && $request->boolean('create_initiative', true)) {
            try {
                $moduleName = $this->extractModuleName($decoded);
                $this->initiatives->createFromScorecardBreach(
                    module: $moduleName,
                    bucket: 'cross_cutting_infra', // screen-review é infra UX cross-cutting
                    ruleId: 'SCREEN-REVIEW.'.str_replace(['/', '\\'], '.', $decoded),
                    scoreBefore: 0,
                    scoreTarget: 100,
                    deadlineDays: 14,
                    metadata: [
                        'source' => 'admin.screen-review',
                        'screen_path' => $decoded,
                        'round' => $round,
                        'desvios_count' => count($desvios),
                    ],
                );
                $initiativeCreated = true;
            } catch (\Throwable $e) {
                Log::warning('admin.screen-review.initiative_failed', [
                    'screen' => $decoded,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->audit->log('screen_review.update_status', [
            'screen_path' => $decoded,
            'status' => $status,
            'round' => $round,
            'desvios_count' => count($desvios),
            'initiative_created' => $initiativeCreated,
        ], $request);

        return back()->with('success', "Round {$round} salvo · status={$status}".
            ($initiativeCreated ? ' · Initiative aberta' : ''));
    }

    // ─────────────────────────────────────────────────────────────────────
    // Build payloads — defer-friendly (custosos mas <100ms cada)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Lista módulos (top-level Pages/*) com contagem por status.
     *
     * @return array<int, array{name:string, total:int, pending:int, approved:int, rejected:int, iterate:int}>
     */
    protected function buildModulesPayload(): array
    {
        $screens = $this->buildScreensPayload();
        $byModule = [];

        foreach ($screens as $s) {
            $mod = $s['module'];
            $byModule[$mod] ??= [
                'name' => $mod,
                'total' => 0,
                'pending' => 0,
                'approved' => 0,
                'rejected' => 0,
                'iterate' => 0,
            ];
            $byModule[$mod]['total']++;
            $key = $this->statusKeyShort($s['status']);
            $byModule[$mod][$key]++;
        }

        $list = array_values($byModule);
        usort($list, fn ($a, $b) => $b['pending'] <=> $a['pending'] ?: strcmp($a['name'], $b['name']));

        return $list;
    }

    /**
     * Lista todas telas .tsx (exceto charter/review/_components/_lib) + status.
     *
     * @return array<int, array{module:string, path:string, name:string, status:string, current_round:int, last_review_at:?string, screenshot_url:?string, charter_path:?string, ux_targets:array, desvios_count:int}>
     */
    protected function buildScreensPayload(): array
    {
        $root = base_path(self::PAGES_ROOT);
        if (! is_dir($root)) {
            return [];
        }

        $screens = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }
            $relPath = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));

            // Filtros: só .tsx, sem _components/_lib, sem .charter/.review/.test
            if (! str_ends_with($relPath, '.tsx')) {
                continue;
            }
            if (preg_match('#(^|/)_(components|lib)/#', $relPath)) {
                continue;
            }
            if (str_contains($relPath, '.charter.') || str_contains($relPath, '.review.') || str_contains($relPath, '.test.')) {
                continue;
            }

            $pathNoExt = substr($relPath, 0, -4); // strip .tsx
            $parts = explode('/', $pathNoExt);
            $module = $parts[0] ?? 'Root';
            $name = end($parts);

            $charterPath = $root.'/'.$pathNoExt.'.charter.md';
            $reviewPath = $root.'/'.$pathNoExt.'.review.md';

            $status = self::STATUS_PENDING;
            $currentRound = 0;
            $lastReviewAt = null;
            $desviosCount = 0;
            $uxTargets = [];

            if (File::exists($reviewPath)) {
                $parsed = $this->parseReviewFile($reviewPath);
                $status = $parsed['status'];
                $currentRound = $parsed['round'];
                $lastReviewAt = $parsed['last_at'];
                $desviosCount = $parsed['desvios_count'];
            }

            if (File::exists($charterPath)) {
                $uxTargets = $this->extractUxTargetsFromCharter($charterPath);
            }

            // Screenshot convention: prototipo-ui/<module-kebab>/<screen-kebab>/screenshot-1440.png
            $screenshotRel = sprintf(
                'prototipo-ui/%s/%s/screenshot-1440.png',
                $this->kebab($module),
                $this->kebab($name),
            );
            $screenshotUrl = File::exists(base_path($screenshotRel)) ? '/'.$screenshotRel : null;

            $screens[] = [
                'module' => $module,
                'path' => $pathNoExt,
                'name' => $name,
                'status' => $status,
                'current_round' => $currentRound,
                'last_review_at' => $lastReviewAt,
                'screenshot_url' => $screenshotUrl,
                'charter_path' => File::exists($charterPath) ? $pathNoExt.'.charter.md' : null,
                'ux_targets' => $uxTargets,
                'desvios_count' => $desviosCount,
            ];
        }

        usort($screens, fn ($a, $b) => strcmp($a['module'], $b['module']) ?: strcmp($a['name'], $b['name']));

        return $screens;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────

    /**
     * @return array{total:int, pending-wagner:int, approved:int, rejected:int, iterate:int, pending_over_7d:int}
     */
    protected function countAllStatuses(): array
    {
        $screens = $this->buildScreensPayload();
        $counts = [
            'total' => count($screens),
            self::STATUS_PENDING => 0,
            self::STATUS_APPROVED => 0,
            self::STATUS_REJECTED => 0,
            self::STATUS_ITERATE => 0,
            'pending_over_7d' => 0,
        ];

        $sevenDaysAgo = now()->subDays(7);
        foreach ($screens as $s) {
            $counts[$s['status']]++;
            if ($s['status'] === self::STATUS_PENDING && $s['last_review_at']) {
                $lastReview = \Carbon\Carbon::parse($s['last_review_at']);
                if ($lastReview->lt($sevenDaysAgo)) {
                    $counts['pending_over_7d']++;
                }
            }
        }

        return $counts;
    }

    protected function countByStatus(string $status): int
    {
        return $this->countAllStatuses()[$status] ?? 0;
    }

    private function reviewPathFor(string $tsxFull): string
    {
        return preg_replace('/\.tsx$/', '.review.md', $tsxFull);
    }

    private function nextRoundNumber(string $reviewPath): int
    {
        if (! File::exists($reviewPath)) {
            return 1;
        }
        $content = File::get($reviewPath);
        preg_match_all('/^round:\s*(\d+)$/m', $content, $matches);
        $max = 0;
        foreach ($matches[1] ?? [] as $n) {
            $max = max($max, (int) $n);
        }

        return $max + 1;
    }

    private function renderRoundBlock(int $round, string $status, string $user, string $notes, array $desvios): string
    {
        $now = now()->toIso8601String();
        $yaml = "```yaml\n";
        $yaml .= "round: {$round}\n";
        $yaml .= "status: {$status}\n";
        $yaml .= "user: {$user}\n";
        $yaml .= "at: {$now}\n";
        if (! empty($desvios)) {
            $yaml .= "desvios:\n";
            foreach ($desvios as $d) {
                $yaml .= '  - '.$this->escapeYamlScalar((string) $d)."\n";
            }
        }
        if ($notes !== '') {
            $yaml .= 'notes: '.$this->escapeYamlScalar($notes)."\n";
        }
        $yaml .= "```";

        return "## Round {$round} — {$status} ({$now})\n\n".$yaml;
    }

    private function escapeYamlScalar(string $s): string
    {
        // Sempre quota — evita problemas com :, #, -, multiline
        return '"'.str_replace(['\\', '"', "\n"], ['\\\\', '\\"', '\\n'], $s).'"';
    }

    /**
     * Parse `<Tela>.review.md` — extrai status atual (último round) + count.
     *
     * @return array{status:string, round:int, last_at:?string, desvios_count:int}
     */
    private function parseReviewFile(string $path): array
    {
        $content = File::get($path);
        // Pega TODOS blocos yaml e fica com último (maior round)
        preg_match_all('/```yaml\s*(.*?)```/s', $content, $matches);
        $blocks = $matches[1] ?? [];

        $bestRound = 0;
        $bestStatus = self::STATUS_PENDING;
        $bestAt = null;
        $bestDesvios = 0;

        foreach ($blocks as $raw) {
            try {
                $parsed = Yaml::parse($raw);
                if (! is_array($parsed) || ! isset($parsed['round'])) {
                    continue;
                }
                $r = (int) $parsed['round'];
                if ($r > $bestRound) {
                    $bestRound = $r;
                    $bestStatus = (string) ($parsed['status'] ?? self::STATUS_PENDING);
                    $bestAt = isset($parsed['at']) ? (string) $parsed['at'] : null;
                    $bestDesvios = is_array($parsed['desvios'] ?? null) ? count($parsed['desvios']) : 0;
                }
            } catch (\Throwable $e) {
                Log::debug('screen-review.parse_block_failed', ['error' => $e->getMessage()]);
            }
        }

        return [
            'status' => $bestStatus,
            'round' => $bestRound,
            'last_at' => $bestAt,
            'desvios_count' => $bestDesvios,
        ];
    }

    /**
     * Extrai bloco "## UX Targets" do charter — lista bullets simples.
     *
     * @return array<int, string>
     */
    private function extractUxTargetsFromCharter(string $charterPath): array
    {
        $content = File::get($charterPath);
        if (! preg_match('/##\s+UX Targets(.*?)(?=\n##\s|\z)/s', $content, $m)) {
            return [];
        }
        $section = $m[1];
        preg_match_all('/^-\s+(.+)$/m', $section, $bullets);

        return array_slice($bullets[1] ?? [], 0, 12);
    }

    private function statusKeyShort(string $status): string
    {
        return match ($status) {
            self::STATUS_APPROVED => 'approved',
            self::STATUS_REJECTED => 'rejected',
            self::STATUS_ITERATE => 'iterate',
            default => 'pending',
        };
    }

    private function extractModuleName(string $path): string
    {
        $parts = explode('/', $path);

        return $parts[0] ?? 'Unknown';
    }

    private function kebab(string $s): string
    {
        $s = preg_replace('/([a-z])([A-Z])/', '$1-$2', $s);

        return strtolower($s ?? '');
    }
}
