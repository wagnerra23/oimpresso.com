<?php

declare(strict_types=1);

namespace Modules\Governance\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Governance\Services\ModuleGradeService;

/**
 * Module Grades dashboard — rubrica oficial module-grade-v3 (ADR 0155).
 *
 * Rotas:
 *   GET /governance/module-grades         → Index (tabela 34 módulos ranqueada, 9 dims)
 *   GET /governance/module-grades/{name}  → Show (drill-down 9 dimensões + gaps + Evoluir)
 *
 * Cache 5min em gradeAllModules() — Service faz I/O filesystem 1-2s × 34 módulos.
 *
 * @see memory/decisions/0155-module-grade-v3-sub-dimensoes-gate-ci.md
 * @see memory/requisitos/Governance/RUNBOOK-module-grades.md
 */
class ModuleGradeController extends Controller
{
    private const CACHE_TTL_SECONDS = 300;
    private const CACHE_KEY_ALL = 'governance.module_grades.all';

    public function __construct(private readonly ModuleGradeService $service)
    {
        $this->middleware('auth');
    }

    public function index(Request $request): Response
    {
        // Inertia::defer pra coleta filesystem (1-2s × 34 módulos)
        $grades = Inertia::defer(fn () => $this->buildAllGradesPayload());

        $kpis = Inertia::defer(fn () => $this->buildKpisPayload());

        // Aba "Catálogo & Sinais-vivos" (grade Catálogo/IDP 2026-07-21): agrega os sinais
        // JÁ derivados por serviço (service-scorecard.json) + as relações do grafo
        // (catalog.json). Só lê JSON de governança commitado — sem business_id/dado de
        // negócio (Tier 0 não morde). Deferido (RUNBOOK-inertia-defer: só carrega quando
        // a aba é pedida por partial reload).
        $catalog = Inertia::defer(fn () => $this->buildCatalogSignalsPayload());

        return Inertia::render('governance/ModuleGrades/Index', [
            'grades'  => $grades,
            'kpis'    => $kpis,
            'catalog' => $catalog,
        ]);
    }

    public function show(Request $request, string $name): Response
    {
        try {
            $grade = Cache::remember(
                "governance.module_grades.{$name}",
                self::CACHE_TTL_SECONDS,
                fn () => $this->service->gradeModule($name),
            );
        } catch (\InvalidArgumentException $e) {
            abort(404, $e->getMessage());
        }

        // ADR 0155 v3 + RUNBOOK-inertia-defer-pattern.md — history é query SQL
        // (até 7 rows × 34 módulos) — defer pra não bloquear render inicial.
        $history = Inertia::defer(fn () => $this->buildHistoryPayload($name));

        // Charter Goal 9 (2026-05-17) — dossier markdown do módulo lido de
        // memory/requisitos/<name>/ via filesystem. I/O lento (até 6 arquivos
        // grandes em SRS/TeamMcp) — Inertia::defer pula closure quando partial
        // reload não pede. Não-bloqueante pra render inicial da rubrica.
        $dossier = Inertia::defer(fn () => $this->buildDossierPayload($name));

        return Inertia::render('governance/ModuleGrades/Show', [
            'grade'   => $grade,
            'history' => $history,
            'dossier' => $dossier,
        ]);
    }

    /**
     * Dossier canônico do módulo — lê docs markdown de memory/requisitos/<name>/.
     *
     * Lê em ordem de prioridade canônica:
     *  1. BRIEFING.md (estado consolidado 1 pág executiva)
     *  2. CAPTERRA-*.md (qualquer variação — narrativa qualitativa vs mercado)
     *  3. GOVERNANCE-MATURITY-FICHA.md (nota cross-benchmark Backstage etc)
     *  4. DEPRECATION-PLAN.md (se existir — sinal de zumbi state)
     *  5. SPEC.md (US-XXX-NNN catalogadas)
     *  6. CHANGELOG.md (Waves recentes)
     *
     * NÃO lê: RUNBOOK-*.md (poluiria lista; usuário pode abrir via git/IDE),
     * UI-CATALOG.md (auxiliar técnico), arquivos sem extensão .md.
     *
     * Robusto a módulos sem nenhuma ficha (retorna []), case-insensitive em
     * nome do diretório (Modules/SRS vs memory/requisitos/SRS), normaliza
     * line endings CRLF→LF pra ReactMarkdown render limpo.
     *
     * @return array<int, array{slug: string, label: string, content_md: string, size_chars: int, modified_at: string|null}>
     */
    private function buildDossierPayload(string $name): array
    {
        $baseDir = base_path("memory/requisitos/{$name}");
        if (! is_dir($baseDir)) {
            return [];
        }

        // Mapeamento slug → label humano + glob pattern (ordem de prioridade)
        $catalog = [
            'briefing'           => ['BRIEFING.md',              'Briefing — estado consolidado'],
            'capterra'           => ['CAPTERRA*.md',             'Capterra — narrativa vs mercado'],
            'governance-maturity' => ['GOVERNANCE-MATURITY-FICHA.md', 'Maturity Ficha — Backstage/LeanIX bench'],
            'deprecation-plan'   => ['DEPRECATION-PLAN.md',      'Deprecation Plan — zumbi roadmap'],
            'spec'               => ['SPEC.md',                  'SPEC — US catalogadas'],
            'changelog'          => ['CHANGELOG.md',             'Changelog — Waves'],
        ];

        $docs = [];
        foreach ($catalog as $slug => [$pattern, $label]) {
            $matches = glob("{$baseDir}/{$pattern}") ?: [];
            foreach ($matches as $file) {
                $raw = @file_get_contents($file);
                if ($raw === false || $raw === '') {
                    continue;
                }

                // Normaliza line endings CRLF→LF (ReactMarkdown remarkGfm respeita LF nativo)
                $content = str_replace("\r\n", "\n", $raw);

                // Sufixo "-variant" pra CAPTERRA múltiplos (ex CAPTERRA-FICHA + CAPTERRA-MCP-TEAM-FICHA)
                $filename = basename($file);
                $finalSlug = count($matches) > 1
                    ? $slug . '-' . strtolower(pathinfo($filename, PATHINFO_FILENAME))
                    : $slug;

                $docs[] = [
                    'slug'        => $finalSlug,
                    'label'       => count($matches) > 1 ? "{$label} ({$filename})" : $label,
                    'filename'    => $filename,
                    'content_md'  => $content,
                    'size_chars'  => mb_strlen($content),
                    'modified_at' => @date('Y-m-d H:i:s', filemtime($file)) ?: null,
                ];
            }
        }

        return $docs;
    }

    /**
     * Últimos 7 snapshots da nota do módulo (sparkline 7d).
     * Alimentado por `php artisan module:grade-snapshot` (cron daily 06:05 BRT).
     *
     * @return array<int, array{score: int, bucket: string, snapshot_at: string}>
     */
    private function buildHistoryPayload(string $name): array
    {
        // Tolerante a ambientes sem a migration aplicada ainda (CI / dev fresh)
        if (! \Illuminate\Support\Facades\Schema::hasTable('mcp_module_grades_history')) {
            return [];
        }

        return DB::table('mcp_module_grades_history')
            ->select('score', 'bucket', 'snapshot_at')
            ->where('module', $name)
            ->orderByDesc('snapshot_at')
            ->limit(7)
            ->get()
            ->reverse() // ordem cronológica pra sparkline ler da esquerda pra direita
            ->values()
            ->map(fn ($row) => [
                'score'       => (int) $row->score,
                'bucket'      => (string) $row->bucket,
                'snapshot_at' => (string) $row->snapshot_at,
            ])
            ->all();
    }

    /**
     * Payload da listagem Index (34 módulos × campos compactos).
     *
     * ADR 0155 v3 — repassa 9 dimensões + `score_v3_raw`/`score_v3_normalized`/`weights_v3_total`
     * pra Index.tsx renderizar colunas D1-D9 (antes só D1-D5 — D6-D9 ficavam `—` permanente).
     *
     * Cada dimensão vem como string `"score/max"` consistente com Show.tsx + back-compat v1.
     *
     * @return array<int, array>
     */
    private function buildAllGradesPayload(): array
    {
        return Cache::remember(
            self::CACHE_KEY_ALL,
            self::CACHE_TTL_SECONDS,
            fn () => $this->service->gradeAllModules()
                ->map(fn ($g) => [
                    'module'              => $g['module'],
                    // `score` sinônimo de score_v3_normalized — back-compat v1/v2
                    'score'               => $g['score'],
                    // ADR 0155 v3 — chaves explícitas (marca audit + permite UI v3 destacar)
                    'score_v3_normalized' => $g['score_v3_normalized'] ?? $g['score'],
                    'score_v3_raw'        => $g['score_v3_raw'] ?? null,
                    'weights_v3_total'    => $g['weights_v3_total'] ?? null,
                    'bucket'              => $g['bucket'],
                    'color'               => $g['color'],
                    'dimensions'          => [
                        // D1-D5 (v1/v2 — preservadas)
                        'multi_tenant'  => "{$g['dimensions']['multi_tenant']['score']}/{$g['dimensions']['multi_tenant']['max']}",
                        'pest_coverage' => "{$g['dimensions']['pest_coverage']['score']}/{$g['dimensions']['pest_coverage']['max']}",
                        'documentation' => "{$g['dimensions']['documentation']['score']}/{$g['dimensions']['documentation']['max']}",
                        'architecture'  => "{$g['dimensions']['architecture']['score']}/{$g['dimensions']['architecture']['max']}",
                        'client_real'   => "{$g['dimensions']['client_real']['score']}/{$g['dimensions']['client_real']['max']}",
                        // D6-D9 (ADR 0155 v3 — antes ausentes na payload Index, Index.tsx renderizava '—')
                        'performance'   => "{$g['dimensions']['performance']['score']}/{$g['dimensions']['performance']['max']}",
                        'lgpd'          => "{$g['dimensions']['lgpd']['score']}/{$g['dimensions']['lgpd']['max']}",
                        'security'      => "{$g['dimensions']['security']['score']}/{$g['dimensions']['security']['max']}",
                        'observability' => "{$g['dimensions']['observability']['score']}/{$g['dimensions']['observability']['max']}",
                    ],
                ])
                ->all()
        );
    }

    /**
     * @return array{average: float, total: int, by_bucket: array<string, int>}
     */
    private function buildKpisPayload(): array
    {
        $all = Cache::get(self::CACHE_KEY_ALL) ?? [];
        if (empty($all)) {
            $all = $this->buildAllGradesPayload();
        }

        $scores = array_column($all, 'score');
        $byBucket = [];
        foreach ($all as $g) {
            $byBucket[$g['bucket']] = ($byBucket[$g['bucket']] ?? 0) + 1;
        }

        return [
            'average'   => count($scores) > 0 ? round(array_sum($scores) / count($scores), 1) : 0,
            'total'     => count($all),
            'by_bucket' => $byBucket,
        ];
    }

    /**
     * Payload da aba "Catálogo & Sinais-vivos" — AGREGADOR advisory (não recalcula nada).
     *
     * Lê 2 artefatos DERIVADOS e commitados (ADR 0256):
     *   - memory/governance/service-scorecard.json → sinais por serviço (grade+telas+grafo+briefing+maturidade),
     *     gerado por scripts/governance/service-scorecard.mjs (nightly mv-metabolismo).
     *   - memory/governance/catalog.json → relações do grafo (delegatesTo) pra listar nomes de
     *     depends_on/dependents (o scorecard só traz as CONTAGENS; aqui damos os NOMES navegáveis).
     *
     * SEM business_id / dado de negócio — é meta de governança, Tier 0 não se aplica ao dado.
     * Degrada gracioso (available:false) se algum artefato faltar (checkout fresh / prod sem deploy).
     *
     * @return array{available: bool, generated_from?: mixed, stats?: mixed, services: array<int, array>}
     */
    private function buildCatalogSignalsPayload(): array
    {
        $scPath = base_path('memory/governance/service-scorecard.json');
        $catPath = base_path('memory/governance/catalog.json');
        if (! is_file($scPath) || ! is_file($catPath)) {
            return ['available' => false, 'services' => []];
        }

        $sc = json_decode((string) @file_get_contents($scPath), true);
        $cat = json_decode((string) @file_get_contents($catPath), true);
        if (! is_array($sc) || ! is_array($cat)) {
            return ['available' => false, 'services' => []];
        }

        // Adjacência por NOME das arestas delegatesTo do catálogo — quem depende de quem.
        $dependsOn = [];
        $dependents = [];
        foreach (($cat['edges'] ?? []) as $e) {
            if (($e['type'] ?? '') !== 'delegatesTo') {
                continue;
            }
            $from = str_replace('module:', '', (string) ($e['from'] ?? ''));
            $to = str_replace('module:', '', (string) ($e['to'] ?? ''));
            if ($from === '' || $to === '') {
                continue;
            }
            $dependsOn[$from][] = $to;
            $dependents[$to][] = $from;
        }

        $services = array_map(static function ($s) use ($dependsOn, $dependents) {
            $id = (string) ($s['id'] ?? '');

            return [
                'id'         => $id,
                'grade'      => $s['signals']['grade']['value'] ?? null,
                'trust'      => $s['trust'] ?? null,
                'owner'      => $s['owner'] ?? null,
                'scope'      => $s['scope'] ?? null,
                'purpose'    => $s['purpose'] ?? null,
                'screens'    => $s['signals']['screens'] ?? null,
                'graph'      => $s['signals']['graph'] ?? null,
                'briefing'   => $s['signals']['briefing'] ?? null,
                'maturity'   => $s['maturity'] ?? null,
                'depends_on' => array_values(array_unique($dependsOn[$id] ?? [])),
                'dependents' => array_values(array_unique($dependents[$id] ?? [])),
            ];
        }, $sc['services'] ?? []);

        return [
            'available'      => true,
            'generated_from' => $sc['generated_from'] ?? null,
            'stats'          => $sc['stats'] ?? null,
            'services'       => $services,
        ];
    }
}
