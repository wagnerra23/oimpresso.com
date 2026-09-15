<?php

declare(strict_types=1);

namespace Modules\Governance\Console\Commands;

use App\Util\OtelHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * D9 Observabilidade — Health check operacional do módulo Governance (Wave 18).
 *
 * Roda 06:35 BRT (após charter:health 06:30 BRT). Verifica 3 sinais essenciais:
 *
 *   1. mcp_governance_rules tem ao menos 1 row enabled (sem policies = sem enforcement)
 *   2. mcp_audit_log recebeu entries últimas 24h (audit log silencioso = trigger broken?)
 *   3. config('governance.actiongate_mode') NOT 'off' em prod (sem gate = sem proteção)
 *
 * O 4º sinal era o frescor de mcp_module_grades_history; saiu com a rubrica
 * module-grade (ADR 0399 Onda 4) — sem o cron que a alimentava, ele passaria a
 * acusar "zero snapshots" para sempre.
 *
 * Diferença vs charter:health:
 *   - charter:health audita PAGE CHARTERS (Modules/<X>/resources/js/Pages/*.charter.md)
 *   - governance:health audita CORE GOVERNANCE INFRA (audit log + policies + ActionGate)
 *
 * NOTA: NÃO usa `--verbose` (Symfony reserved — vide .claude/rules/commands.md).
 *
 * Uso:
 *   php artisan governance:health
 *   php artisan governance:health --notify (ALERT em log se algo falhou)
 *   php artisan governance:health --detail (output linha-a-linha por check)
 *
 * Exit codes:
 *   0 — todos checks ok
 *   1 — pelo menos 1 check falhou (alimenta alert downstream)
 *
 * @see memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md
 * @see Modules/Governance/Console/Commands/CharterHealthCommand.php (pattern análogo)
 */
class GovernanceHealthCommand extends Command
{
    protected $signature = 'governance:health
                            {--notify : Loga ALERT no governance channel se algo falhou}
                            {--detail : Mostra linha por check (humano-friendly)}';

    protected $description = 'Health-check diário Governance core infra (policies + audit + ActionGate)';

    public function handle(): int
    {
        // D9.a OTel: wrap health check completo (rastreabilidade cron BRT 06:35).
        return OtelHelper::span('governance.health.run', [
            'module' => 'Governance',
            'mode'   => config('governance.actiongate_mode', 'warn'),
        ], fn () => $this->runChecks());
    }

    private function runChecks(): int
    {
        $detail = (bool) $this->option('detail');
        $notify = (bool) $this->option('notify');

        $checks = [
            'policies_enabled'        => $this->checkPoliciesEnabled(),
            'audit_log_alive_24h'     => $this->checkAuditLogAlive24h(),
            'actiongate_mode_active'  => $this->checkActionGateModeActive(),
        ];

        $allOk = ! in_array(false, array_column($checks, 'ok'), true);

        Log::channel('single')->info('governance:health', [
            'ok'     => $allOk,
            'checks' => $checks,
            'mode'   => config('governance.actiongate_mode', 'warn'),
        ]);

        if ($detail) {
            foreach ($checks as $name => $check) {
                $status = $check['ok'] ? '<fg=green>OK</>' : '<fg=red>FAIL</>';
                $this->line("  [{$status}] {$name}: {$check['msg']}");
            }
            $this->newLine();
            $this->line($allOk ? '<fg=green>All checks OK.</>' : '<fg=red>One or more checks failed.</>');
        }

        if ($notify && ! $allOk) {
            $failed = collect($checks)
                ->filter(fn ($c) => ! $c['ok'])
                ->map(fn ($c, $name) => "{$name}: {$c['msg']}")
                ->implode(' · ');
            Log::channel('single')->error("governance:health ALERT — {$failed}");
        }

        return $allOk ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return array{ok: bool, msg: string}
     */
    private function checkPoliciesEnabled(): array
    {
        if (! Schema::hasTable('mcp_governance_rules')) {
            return ['ok' => false, 'msg' => 'tabela mcp_governance_rules ausente'];
        }

        $count = (int) DB::table('mcp_governance_rules')->where('enabled', 1)->count();

        return $count > 0
            ? ['ok' => true,  'msg' => "{$count} policies ativas"]
            : ['ok' => false, 'msg' => 'ZERO policies enabled (sem enforcement)'];
    }

    /**
     * @return array{ok: bool, msg: string}
     */
    private function checkAuditLogAlive24h(): array
    {
        if (! Schema::hasTable('mcp_audit_log')) {
            return ['ok' => false, 'msg' => 'tabela mcp_audit_log ausente'];
        }

        $count = (int) DB::table('mcp_audit_log')
            ->where('ts', '>', now()->subHours(24))
            ->count();

        // Silêncio = suspeito (trigger MySQL append-only quebrado?). Mas zero é OK em dev.
        if (app()->environment('production') && $count === 0) {
            return ['ok' => false, 'msg' => 'audit log silencioso 24h (trigger broken?)'];
        }

        return ['ok' => true, 'msg' => "{$count} entries 24h"];
    }

    /**
     * @return array{ok: bool, msg: string}
     */
    private function checkActionGateModeActive(): array
    {
        $mode = (string) config('governance.actiongate_mode', 'warn');

        if (app()->environment('production') && $mode === 'off') {
            return ['ok' => false, 'msg' => "ActionGate mode='off' em prod (sem proteção)"];
        }

        return ['ok' => true, 'msg' => "ActionGate mode={$mode}"];
    }
}
