<?php

declare(strict_types=1);

namespace Modules\ConsultaOs\Console\Commands;

use App\Util\OtelHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Modules\ConsultaOs\Contracts\ConsultaOsRepositoryInterface;
use Modules\ConsultaOs\Services\ConsultaOsService;

/**
 * consultaos:health — health-check do portal publico ConsultaOs (D9 observabilidade Wave 23).
 *
 * Verifica saúde dos componentes minimos do portal publico:
 *   - Repository bindado (ConsultaOsRepositoryInterface — RepairConsultaOsRepository, US-CONSULTA-001)
 *   - Service resolvel via container
 *   - Config retention.php declarado (D7 LGPD compliance)
 *   - Smoke probe: criterio invalido (tipo fora da lista) e recusado sem consultar
 *   - Smoke probe: buscar numero inexistente retorna not_found limpo
 *   (Sem probe de "numero conhecido": com dados reais nao ha OS fixa garantida.)
 *
 * Default: silencioso + log estruturado (cron). `--detail`: tabela humano.
 * NAO usa `--verbose` (Symfony reserved — .claude/rules/commands.md).
 *
 * Uso:
 *   php artisan consultaos:health
 *   php artisan consultaos:health --detail
 *
 * Multi-tenant Tier 0 (ADR 0093): portal publico NAO scopa por business_id
 * (cliente externo sem sessao) — ver PENDENTE [W] no RepairConsultaOsRepository.
 *
 * @see Modules\ConsultaOs\Services\ConsultaOsService
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
        // D9 observabilidade Wave 26 — span sem business_id (CLI command portal publico).
        return OtelHelper::span('consultaos.health', [
            'cli'    => true,
            'detail' => (bool) $this->option('detail'),
        ], function (): int {
            return $this->handleInterno();
        });
    }

    /**
     * Implementacao interna — wrap span OTel acima (D9 Wave 26 saturation).
     */
    private function handleInterno(): int
    {
        $report = [
            'repository_bound'   => false,
            'service_resolvable' => false,
            'retention_declared' => false,
            'smoke_invalid_ok'   => false,
            'smoke_unknown_ok'   => false,
        ];

        // 1) Repository bindado (RepairConsultaOsRepository — US-CONSULTA-001)
        try {
            $repo = app(ConsultaOsRepositoryInterface::class);
            $report['repository_bound'] = is_object($repo);
            $report['repository_class'] = $report['repository_bound'] ? get_class($repo) : null;
        } catch (\Throwable $e) {
            Log::warning('consultaos.health.repository_bind_failed', ['err' => $e->getMessage()]);
        }

        // 2) Service resolvel via container (DI cadeia completa)
        try {
            $service = app(ConsultaOsService::class);
            $report['service_resolvable'] = $service instanceof ConsultaOsService;
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

        // 4) Smoke probe — criterio invalido nao consulta e volta not_found (Tier 0)
        if ($report['service_resolvable']) {
            try {
                $res = $service->buscar('tipo_invalido', 'x');
                $report['smoke_invalid_ok'] = ($res['found'] ?? null) === false;
            } catch (\Throwable $e) {
                Log::warning('consultaos.health.smoke_invalid_failed', ['err' => $e->getMessage()]);
            }

            // 5) Smoke probe — numero inexistente retorna not_found limpo
            try {
                $res = $service->buscar('job_sheet_no', 'HEALTH-0000-INEXISTENTE');
                $report['smoke_unknown_ok'] = ($res['found'] ?? null) === false;
            } catch (\Throwable $e) {
                Log::warning('consultaos.health.smoke_unknown_failed', ['err' => $e->getMessage()]);
            }
        }

        $hasIssue = ! ($report['repository_bound']
            && $report['service_resolvable']
            && $report['retention_declared']
            && $report['smoke_invalid_ok']
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
