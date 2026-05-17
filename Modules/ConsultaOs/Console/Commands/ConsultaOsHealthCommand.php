<?php

declare(strict_types=1);

namespace Modules\ConsultaOs\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Modules\ConsultaOs\Contracts\ConsultaOsRepositoryInterface;
use Modules\ConsultaOs\Services\ConsultaOsMockService;

/**
 * consultaos:health — health-check do portal publico ConsultaOs (D9 observabilidade Wave 23).
 *
 * Verifica saúde dos componentes minimos do portal publico:
 *   - Repository bindado (ConsultaOsRepositoryInterface — Mock OU Repair-real US-CONSULTA-001)
 *   - Service resolvel via container
 *   - Config retention.php declarado (D7 LGPD compliance)
 *   - Smoke probe: buscar numero conhecido retorna found+payload
 *   - Smoke probe: buscar numero inexistente retorna not_found limpo
 *
 * Default: silencioso + log estruturado (cron). `--detail`: tabela humano.
 * NAO usa `--verbose` (Symfony reserved — .claude/rules/commands.md).
 *
 * Uso:
 *   php artisan consultaos:health
 *   php artisan consultaos:health --detail
 *
 * Multi-tenant Tier 0 (ADR 0093): portal publico NAO scopa por business_id
 * (cliente externo sem sessao). Quando US-CONSULTA-001 ativar query real,
 * Service resolve business_id via lookup do protocolo + rate-limit IP.
 *
 * @see Modules\ConsultaOs\Services\ConsultaOsMockService
 * @see Modules\ConsultaOs\Contracts\ConsultaOsRepositoryInterface
 * @see memory/decisions/0155-module-grade-v3-sub-dimensoes-gate-ci.md F6 health
 */
class ConsultaOsHealthCommand extends Command
{
    protected $signature = 'consultaos:health
                            {--detail : Tabela humano (default: log estruturado)}
                            {--notify : Loga ALERT em error channel se issue detectada}';

    protected $description = 'Health-check ConsultaOs portal publico (Repository bind + Service + retention + smoke probes).';

    public function handle(): int
    {
        $report = [
            'repository_bound'   => false,
            'service_resolvable' => false,
            'retention_declared' => false,
            'smoke_known_ok'     => false,
            'smoke_unknown_ok'   => false,
        ];

        // 1) Repository bindado (Mock OU Repair-real US-CONSULTA-001)
        try {
            $repo = app(ConsultaOsRepositoryInterface::class);
            $report['repository_bound'] = is_object($repo);
            $report['repository_class'] = $report['repository_bound'] ? get_class($repo) : null;
        } catch (\Throwable $e) {
            Log::warning('consultaos.health.repository_bind_failed', ['err' => $e->getMessage()]);
        }

        // 2) Service resolvel via container (DI cadeia completa)
        try {
            $service = app(ConsultaOsMockService::class);
            $report['service_resolvable'] = $service instanceof ConsultaOsMockService;
        } catch (\Throwable $e) {
            Log::warning('consultaos.health.service_resolve_failed', ['err' => $e->getMessage()]);
        }

        // 3) Config retention.php declarado (D7 LGPD)
        $retentionPath = __DIR__ . '/../../Config/retention.php';
        if (file_exists($retentionPath)) {
            $cfg = require $retentionPath;
            $report['retention_declared'] = isset($cfg['entities']['consulta_os_logs'])
                && isset($cfg['entities']['consulta_os_tokens'])
                && isset($cfg['strategy']);
        }

        // 4) Smoke probe — numero conhecido (4821 padrao Mock)
        if ($report['service_resolvable']) {
            try {
                $res = $service->buscar('4821');
                $report['smoke_known_ok'] = ($res['found'] ?? false) === true
                    && isset($res['os']['client']);
            } catch (\Throwable $e) {
                Log::warning('consultaos.health.smoke_known_failed', ['err' => $e->getMessage()]);
            }

            // 5) Smoke probe — numero inexistente retorna not_found limpo
            try {
                $res = $service->buscar('99999999');
                $report['smoke_unknown_ok'] = ($res['found'] ?? null) === false;
            } catch (\Throwable $e) {
                Log::warning('consultaos.health.smoke_unknown_failed', ['err' => $e->getMessage()]);
            }
        }

        $hasIssue = ! ($report['repository_bound']
            && $report['service_resolvable']
            && $report['retention_declared']
            && $report['smoke_known_ok']
            && $report['smoke_unknown_ok']);

        Log::info('consultaos.health', $report + ['ok' => ! $hasIssue]);

        if ($this->option('detail')) {
            $this->info('=== consultaos:health ===');
            foreach ($report as $k => $v) {
                $this->line(sprintf('  %-22s: %s', $k, is_bool($v) ? ($v ? 'OK' : 'FAIL') : ($v ?? 'null')));
            }
            $this->line('  status                : ' . ($hasIssue ? 'ISSUE' : 'OK'));
        }

        if ($this->option('notify') && $hasIssue) {
            Log::error('consultaos.health ALERT', $report);
        }

        return $hasIssue ? self::FAILURE : self::SUCCESS;
    }
}
