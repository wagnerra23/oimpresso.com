<?php

declare(strict_types=1);

namespace Modules\Crm\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * crm:health — Wave 23 D8 governance v3.
 *
 * 6 sinais críticos:
 *   1. contacts_table         — `contacts` (UltimatePOS core) presente
 *   2. leaduser_table         — `leaduser` pivot presente
 *   3. proposals_table        — `crm_proposals` presente
 *   4. leads_per_business     — leads ativos por business
 *   5. orphan_leaduser        — atribuições com user inexistente
 *   6. proposal_stale_alarme  — propostas `sent` > 30d sem update
 *
 * Convenção `--detail` (NUNCA --verbose: Symfony reserved — `.claude/rules/commands.md`).
 *
 * Multi-tenant Tier 0 (ADR 0093): admin command read-only.
 *
 * @see Modules/Financeiro/Console/Commands/FinanceiroHealthCommand.php (pattern irmão)
 * @see Modules/RecurringBilling/Console/Commands/RecurringHealthCommand.php
 */
class CrmHealthCommand extends Command
{
    protected $signature = 'crm:health
        {--business= : Filtra por business_id}
        {--alert : Exit 2 FAIL, 1 WARN (cron)}
        {--json : Output JSON}
        {--detail : Log detalhado (NUNCA --verbose: Symfony reserved)}';

    protected $description = 'Health check Modules/Crm — 6 sinais (Wave 23 D8).';

    private const PROPOSAL_STALE_DAYS = 30;

    public function handle(): int
    {
        $businessId = $this->option('business') !== null
            ? (int) $this->option('business')
            : null;

        $asJson = (bool) $this->option('json');
        $alert  = (bool) $this->option('alert');
        $detail = (bool) $this->option('detail');

        $checks = [
            $this->checkContactsTable(),
            $this->checkLeaduserTable(),
            $this->checkProposalsTable(),
            $this->checkLeadsPerBusiness($businessId),
            $this->checkOrphanLeaduser(),
            $this->checkProposalStaleAlarme($businessId),
        ];

        if ($detail && ! $asJson) {
            foreach ($checks as $c) {
                $this->line("  [{$c['status']}] {$c['name']}: {$c['details']}");
            }
        }

        $summary = [
            'ok'    => collect($checks)->filter(fn ($c) => $c['status'] === 'OK')->count(),
            'warn'  => collect($checks)->filter(fn ($c) => $c['status'] === 'WARN')->count(),
            'fail'  => collect($checks)->filter(fn ($c) => $c['status'] === 'FAIL')->count(),
            'total' => count($checks),
        ];

        if ($asJson) {
            $this->line(json_encode([
                'timestamp'       => now()->toIso8601String(),
                'business_filter' => $businessId,
                'checks'          => $checks,
                'summary'         => $summary,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return $this->resolveExitCode($summary, $alert);
        }

        $this->info('CRM Health Check — ' . now()->toDateTimeString());
        $bizLabel = $businessId !== null ? "business_id={$businessId}" : 'todos businesses';
        $this->line("   Filtro: {$bizLabel}");
        $this->newLine();

        $this->table(['Check', 'Status', 'Details'], collect($checks)->map(fn ($c) => [
            $c['name'],
            $c['status'],
            mb_strimwidth((string) $c['details'], 0, 80, '…'),
        ])->toArray());

        $line = sprintf('%d OK, %d WARN, %d FAIL de %d', $summary['ok'], $summary['warn'], $summary['fail'], $summary['total']);
        $summary['fail'] > 0 ? $this->error("  Resumo: $line") : ($summary['warn'] > 0 ? $this->warn("  Resumo: $line") : $this->info("  Resumo: $line"));

        return $this->resolveExitCode($summary, $alert);
    }

    private function checkContactsTable(): array
    {
        return Schema::hasTable('contacts')
            ? $this->makeCheck('contacts_table', 'OK', 'contacts presente')
            : $this->makeCheck('contacts_table', 'FAIL', 'contacts ausente — UltimatePOS core');
    }

    private function checkLeaduserTable(): array
    {
        return Schema::hasTable('leaduser')
            ? $this->makeCheck('leaduser_table', 'OK', 'leaduser pivot presente')
            : $this->makeCheck('leaduser_table', 'FAIL', 'leaduser ausente');
    }

    private function checkProposalsTable(): array
    {
        return Schema::hasTable('crm_proposals')
            ? $this->makeCheck('proposals_table', 'OK', 'crm_proposals presente')
            : $this->makeCheck('proposals_table', 'FAIL', 'crm_proposals ausente — rode migrate Crm');
    }

    private function checkLeadsPerBusiness(?int $businessId): array
    {
        if (! Schema::hasTable('contacts')) {
            return $this->makeCheck('leads_per_business', 'WARN', 'contacts ausente');
        }

        $q = DB::table('contacts')->where('type', 'lead')->whereNull('deleted_at');
        if ($businessId !== null) {
            $q->where('business_id', $businessId);
        }

        $count = (clone $q)->count();
        $distinctos = (clone $q)->distinct('business_id')->count('business_id');

        if ($count === 0) {
            return $this->makeCheck('leads_per_business', 'WARN', 'Nenhum lead no sistema (pré-uso)');
        }

        return $this->makeCheck('leads_per_business', 'OK', "{$count} lead(s) em {$distinctos} business(es)");
    }

    private function checkOrphanLeaduser(): array
    {
        if (! Schema::hasTable('leaduser') || ! Schema::hasTable('users')) {
            return $this->makeCheck('orphan_leaduser', 'WARN', 'leaduser ou users ausentes');
        }

        $orphans = DB::table('leaduser')
            ->leftJoin('users', 'leaduser.user_id', '=', 'users.id')
            ->whereNull('users.id')
            ->count();

        if ($orphans > 0) {
            return $this->makeCheck('orphan_leaduser', 'WARN', "{$orphans} atribuição(ões) órfã(s)");
        }

        return $this->makeCheck('orphan_leaduser', 'OK', 'Sem atribuições órfãs');
    }

    private function checkProposalStaleAlarme(?int $businessId): array
    {
        if (! Schema::hasTable('crm_proposals')) {
            return $this->makeCheck('proposal_stale_alarme', 'WARN', 'crm_proposals ausente');
        }

        $cutoff = now()->subDays(self::PROPOSAL_STALE_DAYS);

        $q = DB::table('crm_proposals')
            ->where('status', 'sent')
            ->where('updated_at', '<', $cutoff)
            ->whereNull('deleted_at');

        if ($businessId !== null) {
            $q->where('business_id', $businessId);
        }

        $count = $q->count();

        if ($count > 0) {
            return $this->makeCheck(
                'proposal_stale_alarme',
                'WARN',
                "{$count} proposta(s) status=sent > " . self::PROPOSAL_STALE_DAYS . 'd sem update — follow-up sugerido'
            );
        }

        return $this->makeCheck('proposal_stale_alarme', 'OK', 'Carteira de propostas ativa');
    }

    /**
     * @return array{name: string, status: string, details: string}
     */
    private function makeCheck(string $name, string $status, string $details): array
    {
        return compact('name', 'status', 'details');
    }

    private function resolveExitCode(array $summary, bool $alert): int
    {
        if (! $alert) {
            return 0;
        }

        return $summary['fail'] > 0 ? 2 : ($summary['warn'] > 0 ? 1 : 0);
    }
}
