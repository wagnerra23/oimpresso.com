<?php

declare(strict_types=1);

namespace Modules\Brief\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * brief:health — Health check do módulo Brief (D9.c — Wave 17 Governance saturação 97%).
 *
 * Equivalente leve do jana:health-check; foca em sinais críticos da geração
 * do Daily Brief (camada L7, ADR 0091):
 *
 *   1. cache_table_present — mcp_brief_inputs_cache presente
 *   2. brief_table_present — mcp_briefs presente
 *   3. recent_valid_brief — último brief valid=1 < 24h (cron 6x/dia deveria gerar)
 *   4. failure_rate_24h — valid=0 / total_24h < 30% (alerta se >30%)
 *
 * Multi-tenant Tier 0 (ADR 0093): Brief é repo-wide (sem business_id — agrega
 * estado global do projeto pro time interno). Read-only, NUNCA INSERT/UPDATE/DELETE.
 *
 * Exit code:
 *   - Sem --alert: sempre 0 (info-only)
 *   - Com --alert: 2 se FAIL, 1 se WARN, 0 se OK
 *
 * Uso:
 *   php artisan brief:health
 *   php artisan brief:health --json
 *   php artisan brief:health --alert
 *
 * NOTA Tier 0: NUNCA use `--verbose` como opção custom — colide com Symfony default
 * (lição catalogada em handoff 2026-05-14-1834-whatsapp-purge-fix-verbose).
 *
 * @see memory/decisions/0091-daily-brief.md
 * @see memory/decisions/0155-module-grade-v3.md D9.c
 * @see Modules\Vestuario\Console\Commands\VestuarioHealthCommand (pattern referência)
 */
class BriefHealthCommand extends Command
{
    protected $signature = 'brief:health
        {--alert : Exit code 2 se FAIL, 1 se WARN (cron + monitoring)}
        {--json : Output JSON estruturado em vez de tabela}';

    protected $description = 'Health check do Daily Brief — 4 sinais (ADR 0155 D9.c, Wave 17).';

    public function handle(): int
    {
        $asJson = (bool) $this->option('json');
        $alert  = (bool) $this->option('alert');

        $checks = [
            $this->checkCacheTable(),
            $this->checkBriefTable(),
            $this->checkRecentValidBrief(),
            $this->checkFailureRate24h(),
        ];

        $summary = [
            'ok'    => collect($checks)->filter(fn ($c) => $c['status'] === 'OK')->count(),
            'warn'  => collect($checks)->filter(fn ($c) => $c['status'] === 'WARN')->count(),
            'fail'  => collect($checks)->filter(fn ($c) => $c['status'] === 'FAIL')->count(),
            'total' => count($checks),
        ];

        if ($asJson) {
            return $this->outputJson($checks, $summary, $alert);
        }

        return $this->outputTable($checks, $summary, $alert);
    }

    private function checkCacheTable(): array
    {
        if (! Schema::hasTable('mcp_brief_inputs_cache')) {
            return $this->makeCheck(
                'cache_table_present',
                'FAIL',
                0,
                '1',
                'Tabela mcp_brief_inputs_cache ausente',
                'Rode migrations canon: refresh_brief_inputs_cache() é dependência.'
            );
        }

        return $this->makeCheck(
            'cache_table_present',
            'OK',
            1,
            '1',
            'Tabela mcp_brief_inputs_cache presente',
            'Schema canônico ADR 0091 aplicado.'
        );
    }

    private function checkBriefTable(): array
    {
        if (! Schema::hasTable('mcp_briefs')) {
            return $this->makeCheck(
                'brief_table_present',
                'FAIL',
                0,
                '1',
                'Tabela mcp_briefs ausente',
                'Rode migration canon. Sem ela brief:generate falha silente.'
            );
        }

        return $this->makeCheck(
            'brief_table_present',
            'OK',
            1,
            '1',
            'Tabela mcp_briefs presente',
            'Schema canônico ADR 0091 aplicado.'
        );
    }

    /**
     * Check 3: último brief valid=1 deve ter <24h. Cron 6x/dia (7/11/14/17/20/23h BRT)
     * deveria gerar ≥5 briefs/dia em condições normais.
     */
    private function checkRecentValidBrief(): array
    {
        if (! Schema::hasTable('mcp_briefs')) {
            return $this->makeCheck('recent_valid_brief', 'WARN', null, '<24h', 'Tabela ausente', 'Rode migrate.');
        }

        $row = DB::selectOne(
            'SELECT MAX(generated_at) AS last_at, TIMESTAMPDIFF(MINUTE, MAX(generated_at), NOW()) AS age_min
             FROM mcp_briefs WHERE valid = 1'
        );

        if ($row === null || $row->last_at === null) {
            return $this->makeCheck(
                'recent_valid_brief',
                'FAIL',
                null,
                '<24h',
                'Nenhum brief valid=1 encontrado',
                'Rode `php artisan brief:generate --dry-run` pra debugar geração.'
            );
        }

        $ageMin = (int) $row->age_min;
        $threshold = 24 * 60;

        if ($ageMin > $threshold) {
            return $this->makeCheck(
                'recent_valid_brief',
                'FAIL',
                $ageMin,
                '<1440',
                "Último brief valid=1 há {$ageMin} min (>24h)",
                'Cron parou. Verifique schedule:list + storage/logs/laravel.log ALERT entries.'
            );
        }

        if ($ageMin > 60 * 8) {
            return $this->makeCheck(
                'recent_valid_brief',
                'WARN',
                $ageMin,
                '<480',
                "Último brief valid=1 há {$ageMin} min (>8h)",
                'Cron pode estar atrasado. Próxima rodada esperada a cada ~3-4h.'
            );
        }

        return $this->makeCheck(
            'recent_valid_brief',
            'OK',
            $ageMin,
            '<480',
            "Último brief valid=1 há {$ageMin} min",
            'Cron rodando dentro do esperado.'
        );
    }

    /**
     * Check 4: failure rate últimas 24h. valid=0 / total deve ser <30%.
     */
    private function checkFailureRate24h(): array
    {
        if (! Schema::hasTable('mcp_briefs')) {
            return $this->makeCheck('failure_rate_24h', 'WARN', null, '<30%', 'Tabela ausente', 'Rode migrate.');
        }

        $total = (int) DB::table('mcp_briefs')
            ->where('generated_at', '>=', now()->subDay())
            ->count();

        if ($total === 0) {
            return $this->makeCheck(
                'failure_rate_24h',
                'WARN',
                0,
                '>=1',
                'Nenhuma tentativa de brief nas últimas 24h',
                'Cron deveria rodar 6x/dia. Verifique schedule + worker.'
            );
        }

        $failed = (int) DB::table('mcp_briefs')
            ->where('generated_at', '>=', now()->subDay())
            ->where('valid', 0)
            ->count();

        $pct = (int) round(($failed / $total) * 100);

        if ($pct > 50) {
            return $this->makeCheck(
                'failure_rate_24h',
                'FAIL',
                $pct,
                '<30',
                "{$failed}/{$total} briefs falharam (={$pct}%)",
                'OpenAI down OU prompt regrediu OU PII leak detectado. Veja mcp_briefs.error_msg.'
            );
        }

        if ($pct > 30) {
            return $this->makeCheck(
                'failure_rate_24h',
                'WARN',
                $pct,
                '<30',
                "{$failed}/{$total} briefs falharam (={$pct}%)",
                'Investigue mcp_briefs.error_msg recentes.'
            );
        }

        return $this->makeCheck(
            'failure_rate_24h',
            'OK',
            $pct,
            '<30',
            "{$failed}/{$total} briefs falharam (={$pct}%)",
            'Failure rate dentro do esperado.'
        );
    }

    private function outputTable(array $checks, array $summary, bool $alert): int
    {
        $this->line('');
        $this->info('Brief Health Check — ' . now()->toDateTimeString());
        $this->newLine();

        $headers = ['Check', 'Status', 'Details', 'Recommendation'];
        $tableRows = collect($checks)->map(function (array $check) {
            $statusIcon = match ($check['status']) {
                'OK'   => 'OK',
                'WARN' => 'WARN',
                'FAIL' => 'FAIL',
                default => $check['status'],
            };

            return [
                $check['name'],
                $statusIcon,
                mb_strimwidth((string) $check['details'], 0, 80, '…'),
                mb_strimwidth((string) $check['recommendation'], 0, 80, '…'),
            ];
        })->toArray();

        $this->table($headers, $tableRows);
        $this->newLine();

        $summaryLine = sprintf(
            '%d OK, %d WARN, %d FAIL de %d checks',
            $summary['ok'],
            $summary['warn'],
            $summary['fail'],
            $summary['total']
        );

        if ($summary['fail'] > 0) {
            $this->error("  Resumo: {$summaryLine}");
        } elseif ($summary['warn'] > 0) {
            $this->warn("  Resumo: {$summaryLine}");
        } else {
            $this->info("  Resumo: {$summaryLine}");
        }

        $this->newLine();
        return $this->resolveExitCode($summary, $alert);
    }

    private function outputJson(array $checks, array $summary, bool $alert): int
    {
        $output = [
            'timestamp' => now()->toIso8601String(),
            'checks'    => collect($checks)->map(function (array $check) {
                return [
                    'name'           => $check['name'],
                    'status'         => $check['status'],
                    'value'          => $check['value'],
                    'threshold'      => $check['threshold'],
                    'details'        => $check['details'],
                    'recommendation' => $check['recommendation'],
                ];
            })->values()->toArray(),
            'summary' => $summary,
        ];

        $this->line(json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return $this->resolveExitCode($summary, $alert);
    }

    private function makeCheck(
        string $name,
        string $status,
        mixed $value,
        string $threshold,
        string $details,
        string $recommendation
    ): array {
        return compact('name', 'status', 'value', 'threshold', 'details', 'recommendation');
    }

    private function resolveExitCode(array $summary, bool $alert): int
    {
        if (! $alert) {
            return 0;
        }

        if ($summary['fail'] > 0) {
            return 2;
        }

        if ($summary['warn'] > 0) {
            return 1;
        }

        return 0;
    }
}
