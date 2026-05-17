<?php

declare(strict_types=1);

namespace Modules\ADS\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ads:health — Health check Modules/ADS (Wave 23 D9.c).
 *
 * ADS é o motor de decisões automáticas (Brain B autonomous) — health crítico:
 *  garantir que pipeline de decisões / planning / skills versionadas estão saudáveis.
 *
 * Sinais (alinhado ao padrão `spreadsheet:health`):
 *   1. ads_decisions_table_present
 *   2. ads_skills_table_present
 *   3. brainb_processed_24h        — decisões processadas Brain B nas últimas 24h
 *   4. decisions_pending_review    — pending humano há mais de 72h (FAIL governança)
 *   5. policy_blocked_ratio_24h    — % decisões bloqueadas por PolicyEngine (sinal calibração)
 *   6. skills_unpublished_overdue  — skills draft há mais de 14d (governance debt)
 *
 * Multi-tenant Tier 0 (ADR 0093): agregação cross-tenant superadmin. Read-only.
 * SEMPRE sem `--verbose` (Symfony reserved — `--detail` se precisar).
 *
 * @see memory/decisions/0155-module-grade-v3.md D9.c
 * @see Modules/Spreadsheet/Console/Commands/SpreadsheetHealthCommand.php (sibling)
 */
class AdsHealthCommand extends Command
{
    protected $signature = 'ads:health
        {--alert : Exit code 2 se FAIL, 1 se WARN}
        {--json : Output JSON estruturado}';

    protected $description = 'Health check ADS — 6 sinais Brain B autonomous (ADR 0155 D9.c, Wave 23).';

    public function handle(): int
    {
        $asJson = (bool) $this->option('json');
        $alert  = (bool) $this->option('alert');

        $checks = [
            $this->checkDecisionsTable(),
            $this->checkSkillsTable(),
            $this->checkBrainBProcessed24h(),
            $this->checkDecisionsPendingReview(),
            $this->checkPolicyBlockedRatio24h(),
            $this->checkSkillsUnpublishedOverdue(),
        ];

        $summary = [
            'ok'    => collect($checks)->filter(fn ($c) => $c['status'] === 'OK')->count(),
            'warn'  => collect($checks)->filter(fn ($c) => $c['status'] === 'WARN')->count(),
            'fail'  => collect($checks)->filter(fn ($c) => $c['status'] === 'FAIL')->count(),
            'total' => count($checks),
        ];

        if ($asJson) {
            $this->line(json_encode([
                'timestamp' => now()->toIso8601String(),
                'module'    => 'ADS',
                'checks'    => $checks,
                'summary'   => $summary,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return $this->resolveExitCode($summary, $alert);
        }

        $this->line('');
        $this->info('ADS Health Check (Brain B autonomous) — ' . now()->toDateTimeString());
        $this->newLine();

        $rows = collect($checks)->map(fn ($c) => [
            $c['name'],
            $c['status'],
            mb_strimwidth((string) $c['details'], 0, 80, '…'),
            mb_strimwidth((string) $c['recommendation'], 0, 80, '…'),
        ])->toArray();

        $this->table(['Check', 'Status', 'Details', 'Recommendation'], $rows);
        $this->newLine();

        $line = "{$summary['ok']} OK, {$summary['warn']} WARN, {$summary['fail']} FAIL de {$summary['total']} checks";
        $summary['fail'] > 0 ? $this->error("  Resumo: {$line}")
            : ($summary['warn'] > 0 ? $this->warn("  Resumo: {$line}") : $this->info("  Resumo: {$line}"));

        return $this->resolveExitCode($summary, $alert);
    }

    private function checkDecisionsTable(): array
    {
        // Tabela canônica do pipeline ADS (decisões emitidas / processadas)
        $candidates = ['ads_decisions', 'ads_decision_records'];
        foreach ($candidates as $t) {
            if (Schema::hasTable($t)) {
                return $this->mk('ads_decisions_table_present', 'OK', "Tabela {$t} presente", 'Schema canônico ADS aplicado.');
            }
        }
        return $this->mk('ads_decisions_table_present', 'FAIL', 'Nenhuma tabela ADS decisions encontrada', 'Rode migrate Modules/ADS.');
    }

    private function checkSkillsTable(): array
    {
        $candidates = ['ads_skills', 'ads_skill_versions'];
        foreach ($candidates as $t) {
            if (Schema::hasTable($t)) {
                return $this->mk('ads_skills_table_present', 'OK', "Tabela {$t} presente", 'Schema canônico ADS aplicado.');
            }
        }
        return $this->mk('ads_skills_table_present', 'WARN', 'Tabela ads_skills ausente', 'Skills versionadas indisponíveis.');
    }

    private function checkBrainBProcessed24h(): array
    {
        $table = $this->resolveDecisionsTable();
        if ($table === null) {
            return $this->mk('brainb_processed_24h', 'WARN', 'Tabela decisions ausente', 'Rode migrate.');
        }
        $count = (int) DB::table($table)
            ->where('created_at', '>=', now()->subDay())
            ->count();
        if ($count === 0) {
            return $this->mk('brainb_processed_24h', 'WARN', '0 decisões em 24h cross-tenant', 'Brain B pode estar ocioso — ok se sem volume.');
        }
        return $this->mk('brainb_processed_24h', 'OK', "{$count} decisões em 24h", 'Pipeline Brain B ativo.');
    }

    private function checkDecisionsPendingReview(): array
    {
        $table = $this->resolveDecisionsTable();
        if ($table === null || ! Schema::hasColumn($table, 'status')) {
            return $this->mk('decisions_pending_review', 'WARN', 'Schema parcial — sem coluna status', 'Rode migrate completo.');
        }
        $pending = (int) DB::table($table)
            ->where('status', 'pending_review')
            ->where('created_at', '<', now()->subHours(72))
            ->count();
        if ($pending > 0) {
            return $this->mk('decisions_pending_review', 'FAIL', "{$pending} decisões pending >72h", 'Acionar revisor humano OU ajustar PolicyEngine.');
        }
        return $this->mk('decisions_pending_review', 'OK', '0 decisões pending >72h', 'Backlog humano em dia.');
    }

    private function checkPolicyBlockedRatio24h(): array
    {
        $table = $this->resolveDecisionsTable();
        if ($table === null || ! Schema::hasColumn($table, 'status')) {
            return $this->mk('policy_blocked_ratio_24h', 'WARN', 'Schema parcial', 'Sem dados pra calcular ratio.');
        }
        $total = (int) DB::table($table)
            ->where('created_at', '>=', now()->subDay())
            ->count();
        if ($total === 0) {
            return $this->mk('policy_blocked_ratio_24h', 'OK', 'Sem volume 24h', 'Calibração indiferente.');
        }
        $blocked = (int) DB::table($table)
            ->where('created_at', '>=', now()->subDay())
            ->whereIn('status', ['blocked', 'rejected', 'policy_blocked'])
            ->count();
        $ratio = $total > 0 ? ($blocked / $total) : 0.0;
        if ($ratio > 0.5) {
            return $this->mk('policy_blocked_ratio_24h', 'WARN', sprintf('%.0f%% bloqueado (%d/%d)', $ratio * 100, $blocked, $total), 'PolicyEngine talvez muito restritiva — revisar thresholds.');
        }
        return $this->mk('policy_blocked_ratio_24h', 'OK', sprintf('%.0f%% bloqueado (%d/%d)', $ratio * 100, $blocked, $total), 'Calibração saudável (<50% blocked).');
    }

    private function checkSkillsUnpublishedOverdue(): array
    {
        if (! Schema::hasTable('ads_skill_versions')) {
            return $this->mk('skills_unpublished_overdue', 'OK', 'Sem skill_versions — sem débito', 'N/A.');
        }
        $cols = ['status', 'state'];
        $statusCol = null;
        foreach ($cols as $c) {
            if (Schema::hasColumn('ads_skill_versions', $c)) { $statusCol = $c; break; }
        }
        if ($statusCol === null) {
            return $this->mk('skills_unpublished_overdue', 'WARN', 'Schema sem coluna status', 'N/A.');
        }
        $overdue = (int) DB::table('ads_skill_versions')
            ->where($statusCol, 'draft')
            ->where('created_at', '<', now()->subDays(14))
            ->count();
        if ($overdue > 5) {
            return $this->mk('skills_unpublished_overdue', 'WARN', "{$overdue} skills draft >14d", 'Publicar ou descartar — débito governança.');
        }
        return $this->mk('skills_unpublished_overdue', 'OK', "{$overdue} skills draft >14d", 'Backlog skills saudável.');
    }

    private function resolveDecisionsTable(): ?string
    {
        foreach (['ads_decisions', 'ads_decision_records'] as $t) {
            if (Schema::hasTable($t)) return $t;
        }
        return null;
    }

    private function mk(string $name, string $status, string $details, string $recommendation): array
    {
        return compact('name', 'status', 'details', 'recommendation');
    }

    private function resolveExitCode(array $summary, bool $alert): int
    {
        if (! $alert) return 0;
        if ($summary['fail'] > 0) return 2;
        if ($summary['warn'] > 0) return 1;
        return 0;
    }
}
