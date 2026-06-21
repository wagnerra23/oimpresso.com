<?php

declare(strict_types=1);

namespace Modules\Jana\Console\Commands;

use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * ADR 0133 — System health audit canônico.
 *
 * 5 checks rodam 1×/dia (06:15 BRT, 15min após jana:health-check pra evitar disputa DB).
 * Princípio 2 Constituição v2 (tiered cost): SQL + filesystem only, ZERO LLM call.
 * Princípio 4 (loop fechado por métrica): output integra brief diário.
 *
 * Checks (5 dimensões do audit paralelo 2026-05-10 que viraram US):
 *   1. observability_pipeline       — Langfuse health 200 + LANGFUSE_HOST env setado
 *   2. eval_ci_gate                  — .github/workflows/jana-ragas-gate.yml exists (US-COPI-105 done)
 *   3. adr_stale_count               — ADRs canon citando tech abandonada (Vizra/old-Reverb/CURRENT.md/TASKS.md)
 *   4. cost_dashboard_aggregation    — mcp_usage_diaria populada nas últimas 24h
 *   5. test_coverage_gate            — modules-pest.yml ou ci.yml com pcov+coverage-clover
 *
 * Output: tabela stdout + log estruturado.
 * Exit code: 0 se tudo OK, 1 se qualquer check failed.
 *
 * Uso:
 *   php artisan jana:system-audit
 *   php artisan jana:system-audit --json (machine-readable; consumido por tool MCP)
 *   php artisan jana:system-audit --notify (loga ALERT em log se falhou)
 */
class SystemAuditCommand extends Command
{
    protected $signature = 'jana:system-audit
                            {--json : Output JSON em vez de tabela}
                            {--notify : Loga ALERT no channel single se algo falhou}';

    protected $description = 'System health audit Constituição v2 — 5 checks SQL+FS (ADR 0133)';

    /**
     * Constantes — thresholds + paths checkados. Mudar aqui, não no método.
     */
    protected const ADR_STALE_THRESHOLD = 5;

    protected const COST_AGG_MIN_ROWS_24H = 1;

    public function handle(): int
    {
        $checks = [
            $this->checkObservabilityPipeline(),
            $this->checkEvalCiGate(),
            $this->checkAdrStaleCount(),
            $this->checkCostDashboardAggregation(),
            $this->checkTestCoverageGate(),
        ];

        $allOk = self::allChecksOk($checks);

        if ($this->option('json')) {
            $this->line(json_encode([
                'ok' => $allOk,
                'checked_at' => now()->toIso8601String(),
                'checks' => $checks,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->renderTable($checks, $allOk);
        }

        Log::channel('single')->info('jana:system-audit', [
            'ok' => $allOk,
            'checks' => collect($checks)->mapWithKeys(fn ($c) => [
                $c['name'] => [
                    'ok' => $c['ok'],
                    'value' => $c['value'],
                    'threshold' => $c['threshold'] ?? null,
                ],
            ])->toArray(),
        ]);

        if ($this->option('notify') && ! $allOk) {
            $failed = collect($checks)->where('ok', false)->pluck('name')->implode(', ');
            Log::channel('single')->error("jana:system-audit ALERT — falhou: {$failed}");
        }

        return $allOk ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Veredito do gate: OK só se TODOS os checks têm ok=true (system-audit não tem
     * advisory — todos os 5 são duros). Extraído pra ser testável SEM DB (bite-test
     * SentinelBiteTest) — prova que o exit code RESPONDE ao estado, não é constante.
     * Auditoria de sentinelas 2026-06-20 (o comando tinha ZERO testes).
     */
    public static function allChecksOk(array $checks): bool
    {
        return collect($checks)->every(fn ($c) => (bool) ($c['ok'] ?? false));
    }

    /**
     * Check 1 — Observability pipeline (US-INFRA-016 PR-1 deve estar ativo).
     * OK = LANGFUSE_HOST setado + endpoint público responde 200 em <5s.
     */
    protected function checkObservabilityPipeline(): array
    {
        $host = (string) env('LANGFUSE_HOST', '');

        if ($host === '') {
            return [
                'name' => 'observability_pipeline',
                'ok' => false,
                'value' => 'LANGFUSE_HOST não setado no .env',
                'threshold' => 'LANGFUSE_HOST presente + health 200',
                'remediation' => 'Wire .env Hostinger com keys do Langfuse (US-INFRA-016 PR-1)',
            ];
        }

        // Retry 3× com backoff exponencial pra evitar false-alarm em blip de rede.
        // Princípio 8 ADR 0094 (confiabilidade com fallback): check só vira FAIL após
        // 3 tentativas falharem — não 1 timeout transitório.
        $client = new Client(['timeout' => 5.0]);
        $endpoint = rtrim($host, '/') . '/api/public/health';
        $lastError = null;

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                $response = $client->get($endpoint);
                $status = $response->getStatusCode();

                if ($status === 200) {
                    return [
                        'name' => 'observability_pipeline',
                        'ok' => true,
                        'value' => "HTTP 200 de {$host}" . ($attempt > 1 ? " (try {$attempt}/3)" : ''),
                        'threshold' => 'HTTP 200 em ≤3 tentativas',
                    ];
                }

                $lastError = "HTTP {$status}";
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
            }

            if ($attempt < 3) {
                usleep(500_000 * $attempt); // 0.5s, 1s
            }
        }

        return [
            'name' => 'observability_pipeline',
            'ok' => false,
            'value' => "FAIL após 3 tentativas: {$lastError}",
            'threshold' => 'HTTP 200 em ≤3 tentativas',
            'remediation' => 'Verificar CT 100 Langfuse: tailscale ssh root@ct100-mcp \'cd /opt/langfuse/code/docker/langfuse && docker compose ps\'',
        ];
    }

    /**
     * Check 2 — Eval CI gate (US-COPI-105 done).
     * OK = .github/workflows/jana-ragas-gate.yml exists.
     * (ADR 0271 onda 2 item 8: apontava pra eval-recall-gate.yml inexistente
     *  → self-audit falso-verde. O gate real de eval é o jana-ragas-gate.yml.)
     */
    protected function checkEvalCiGate(): array
    {
        $path = base_path('.github/workflows/jana-ragas-gate.yml');
        $exists = File::exists($path);

        return [
            'name' => 'eval_ci_gate',
            'ok' => $exists,
            'value' => $exists ? 'workflow jana-ragas-gate.yml presente' : 'workflow ausente',
            'threshold' => '.github/workflows/jana-ragas-gate.yml exists',
            'remediation' => $exists ? null : 'Implementar US-COPI-105 — workflow CI gate Pest eval R@3≥0.70',
        ];
    }

    /**
     * Check 3 — ADR stale (US-COPI-106 ritual).
     * Conta ADRs com `lifecycle: canon` que mencionam tech abandonada
     * (Vizra, old-Reverb sem `superseded_by: 0058`, refs a CURRENT.md/TASKS.md).
     */
    protected function checkAdrStaleCount(): array
    {
        $dir = base_path('memory/decisions');

        if (! File::isDirectory($dir)) {
            return [
                'name' => 'adr_stale_count',
                'ok' => true,
                'value' => 'memory/decisions/ não existe',
                'threshold' => '≤ ' . self::ADR_STALE_THRESHOLD . ' candidatos',
            ];
        }

        // Refinado pós-audit adversarial: sub-agent paralelo detectou 10 candidatas
        // enquanto regex strict só detectou 1. Agora cobre 5 sinais de stale:
        //   1. Vizra (laravel/ai SDK substitui)
        //   2. CURRENT.md / TASKS.md (deprecados ADR 0070)
        //   3. Reverb sem superseded_by: 0058 (Centrifugo substitui)
        //   4. Hostinger como runtime daemon (ADR 0062 proíbe)
        //   5. Octane Hostinger (proibido ADR 0062)
        $patterns = [
            '/\bVizra\b/i',
            '/\bCURRENT\.md\b/',
            '/\bTASKS\.md\b/',
            '/\boctane.{0,30}hostinger\b/i',
        ];

        $candidates = collect(File::files($dir))
            ->filter(fn ($f) => str_ends_with($f->getFilename(), '.md'))
            ->filter(function ($f) use ($patterns) {
                $content = File::get($f->getRealPath());

                // Skip ADRs já marked deprecated/superseded/historical
                if (preg_match('/^\s*lifecycle:\s*(deprecated|superseded|historical)/mi', $content)) {
                    return false;
                }
                if (preg_match('/^\s*status:\s*(deprecated|superseded|rejected)/mi', $content)) {
                    return false;
                }

                // Não é canon nem accepted? skip
                if (! preg_match('/lifecycle:\s*canon|status:\s*accepted/i', $content)) {
                    return false;
                }

                foreach ($patterns as $p) {
                    if (preg_match($p, $content)) {
                        return true;
                    }
                }

                // Reverb mention sem superseded_by 0058 (Centrifugo)
                if (preg_match('/\bReverb\b/i', $content) && ! preg_match('/superseded_by:\s*\[?\s*0058/i', $content)) {
                    return true;
                }

                return false;
            })
            ->count();

        return [
            'name' => 'adr_stale_count',
            'ok' => $candidates <= self::ADR_STALE_THRESHOLD,
            'value' => "{$candidates} ADRs canon com refs a tech abandonada",
            'threshold' => '≤ ' . self::ADR_STALE_THRESHOLD,
            'remediation' => $candidates > self::ADR_STALE_THRESHOLD ? 'Executar US-COPI-106 — ADR GC ritual trimestral' : null,
        ];
    }

    /**
     * Check 4 — Cost dashboard aggregation (US futura).
     * OK = mcp_usage_diaria tem ≥1 row pras últimas 24h (cron mcp:agregacao-diaria rodando).
     */
    protected function checkCostDashboardAggregation(): array
    {
        if (! Schema::hasTable('mcp_usage_diaria')) {
            return [
                'name' => 'cost_dashboard_aggregation',
                'ok' => false,
                'value' => 'tabela mcp_usage_diaria não existe',
                'threshold' => '≥ ' . self::COST_AGG_MIN_ROWS_24H . ' rows nas últimas 24h',
                'remediation' => 'Aplicar migrations Jana mcp_usage_diaria + implementar comando mcp:agregacao-diaria',
            ];
        }

        $since = now()->subHours(24);
        $rows = DB::table('mcp_usage_diaria')
            ->where('dia', '>=', $since->toDateString())
            ->count();

        return [
            'name' => 'cost_dashboard_aggregation',
            'ok' => $rows >= self::COST_AGG_MIN_ROWS_24H,
            'value' => "{$rows} rows nas últimas 24h",
            'threshold' => '≥ ' . self::COST_AGG_MIN_ROWS_24H,
            'remediation' => $rows < self::COST_AGG_MIN_ROWS_24H ? 'Implementar comando artisan mcp:agregacao-diaria + schedule 23:55 (referenciado nas migrations mas não implementado)' : null,
        ];
    }

    /**
     * Check 5 — Test coverage gate (US futura).
     * OK = workflow CI tem pcov + coverage-clover output.
     */
    protected function checkTestCoverageGate(): array
    {
        $workflowsDir = base_path('.github/workflows');

        if (! File::isDirectory($workflowsDir)) {
            return [
                'name' => 'test_coverage_gate',
                'ok' => false,
                'value' => '.github/workflows/ não existe',
                'threshold' => 'pcov + coverage-clover em ci.yml ou modules-pest.yml',
            ];
        }

        $has = collect(File::files($workflowsDir))
            ->filter(fn ($f) => str_ends_with($f->getFilename(), '.yml'))
            ->contains(function ($f) {
                $content = File::get($f->getRealPath());
                return str_contains(strtolower($content), 'pcov') ||
                    str_contains(strtolower($content), 'coverage-clover');
            });

        return [
            'name' => 'test_coverage_gate',
            'ok' => $has,
            'value' => $has ? 'pcov ou coverage-clover encontrado em workflows' : 'sem coverage instrumentation',
            'threshold' => 'pcov OR coverage-clover',
            'remediation' => $has ? null : 'Implementar coverage gate — habilitar pcov em modules-pest.yml + publicar coverage.xml',
        ];
    }

    protected function renderTable(array $checks, bool $allOk): void
    {
        $rows = collect($checks)->map(fn ($c) => [
            $c['ok'] ? '✅' : '❌',
            $c['name'],
            $c['value'],
            $c['threshold'] ?? '—',
        ])->toArray();

        $this->table(['OK', 'Check', 'Valor atual', 'Threshold'], $rows);

        if (! $allOk) {
            $this->newLine();
            $this->warn('⚠️  Remediations:');
            foreach ($checks as $c) {
                if (! $c['ok'] && ! empty($c['remediation'])) {
                    $this->line("  • {$c['name']}: {$c['remediation']}");
                }
            }
        }

        $this->newLine();
        $this->line($allOk ? '<info>All 5 checks PASSED</info>' : "<error>{$this->failedCount($checks)}/5 checks FAILED</error>");
    }

    protected function failedCount(array $checks): int
    {
        return collect($checks)->where('ok', false)->count();
    }
}
