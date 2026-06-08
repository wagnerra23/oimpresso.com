<?php

declare(strict_types=1);

namespace Modules\NFSe\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * nfse:health — Wave 23 D6 governance v3.
 *
 * 6 sinais críticos NFSe:
 *   1. emissoes_table         — nfse_emissoes presente
 *   2. provider_config_table  — nfse_provider_configs presente
 *   3. certificado_table      — nfse_certificados presente
 *   4. providers_ativos       — businesses com NFSe configurada
 *   5. cert_vencimento_alarme — certificados vencendo nos próximos 30 dias
 *   6. rejeitadas_recentes    — NFSe rejeitadas nas últimas 24h
 *
 * Convenção `--detail` (NUNCA --verbose — Symfony reserved).
 *
 * Multi-tenant Tier 0 (ADR 0093): admin command read-only.
 *
 * @see Modules/NfeBrasil/Console/Commands/NfeHealthCommand.php (pattern irmão)
 * @see Modules/Financeiro/Console/Commands/FinanceiroHealthCommand.php
 */
class NfseHealthCommand extends Command
{
    protected $signature = 'nfse:health
        {--business= : Filtra por business_id}
        {--alert : Exit 2 FAIL, 1 WARN (cron)}
        {--json : Output JSON}
        {--detail : Log detalhado (NUNCA --verbose: Symfony reserved)}';

    protected $description = 'Health check Modules/NFSe — 6 sinais (Wave 23 D6).';

    private const CERT_WARN_DIAS = 30;

    private const REJEICAO_LOOKBACK_HORAS = 24;

    public function handle(): int
    {
        $businessId = $this->option('business') !== null
            ? (int) $this->option('business')
            : null;

        $asJson = (bool) $this->option('json');
        $alert  = (bool) $this->option('alert');
        $detail = (bool) $this->option('detail');

        $checks = [
            $this->checkEmissoesTable(),
            $this->checkProviderConfigTable(),
            $this->checkCertificadoTable(),
            $this->checkProvidersAtivos($businessId),
            $this->checkCertVencimento($businessId),
            $this->checkRejeitadasRecentes($businessId),
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

        $this->info('NFSe Health Check — ' . now()->toDateTimeString());
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

    private function checkEmissoesTable(): array
    {
        return Schema::hasTable('nfse_emissoes')
            ? $this->makeCheck('emissoes_table', 'OK', 'nfse_emissoes presente')
            : $this->makeCheck('emissoes_table', 'FAIL', 'nfse_emissoes ausente — rode module:migrate NFSe');
    }

    private function checkProviderConfigTable(): array
    {
        return Schema::hasTable('nfse_provider_configs')
            ? $this->makeCheck('provider_config_table', 'OK', 'nfse_provider_configs presente')
            : $this->makeCheck('provider_config_table', 'FAIL', 'nfse_provider_configs ausente');
    }

    private function checkCertificadoTable(): array
    {
        return Schema::hasTable('nfse_certificados')
            ? $this->makeCheck('certificado_table', 'OK', 'nfse_certificados presente')
            : $this->makeCheck('certificado_table', 'WARN', 'nfse_certificados ausente — cert global usado?');
    }

    private function checkProvidersAtivos(?int $businessId): array
    {
        if (! Schema::hasTable('nfse_provider_configs')) {
            return $this->makeCheck('providers_ativos', 'WARN', 'Tabela ausente');
        }

        $q = DB::table('nfse_provider_configs');
        if ($businessId !== null) {
            $q->where('business_id', $businessId);
        }

        $count = (clone $q)->count();
        $distintos = (clone $q)->distinct('business_id')->count('business_id');

        if ($count === 0) {
            return $this->makeCheck('providers_ativos', 'WARN', 'Nenhum provider configurado');
        }

        return $this->makeCheck('providers_ativos', 'OK', "{$count} config(s) em {$distintos} business(es)");
    }

    private function checkCertVencimento(?int $businessId): array
    {
        if (! Schema::hasTable('nfse_certificados')) {
            return $this->makeCheck('cert_vencimento_alarme', 'WARN', 'Tabela ausente');
        }

        if (! Schema::hasColumn('nfse_certificados', 'valido_ate')) {
            return $this->makeCheck('cert_vencimento_alarme', 'WARN', 'coluna valido_ate ausente');
        }

        $cutoff = now()->addDays(self::CERT_WARN_DIAS);

        $q = DB::table('nfse_certificados')
            ->where('valido_ate', '<', $cutoff)
            ->whereNull('deleted_at');

        if ($businessId !== null) {
            $q->where('business_id', $businessId);
        }

        $vencendo = $q->count();

        if ($vencendo > 0) {
            return $this->makeCheck(
                'cert_vencimento_alarme',
                'WARN',
                "{$vencendo} certificado(s) vencendo em < " . self::CERT_WARN_DIAS . 'd — rotacione'
            );
        }

        return $this->makeCheck('cert_vencimento_alarme', 'OK', 'Certificados vigentes');
    }

    private function checkRejeitadasRecentes(?int $businessId): array
    {
        if (! Schema::hasTable('nfse_emissoes')) {
            return $this->makeCheck('rejeitadas_recentes', 'WARN', 'Tabela ausente');
        }

        $cutoff = now()->subHours(self::REJEICAO_LOOKBACK_HORAS);

        $q = DB::table('nfse_emissoes')
            ->where('status', 'rejeitada')
            ->where('updated_at', '>=', $cutoff);

        if ($businessId !== null) {
            $q->where('business_id', $businessId);
        }

        $rej = $q->count();

        if ($rej >= 5) {
            return $this->makeCheck(
                'rejeitadas_recentes',
                'WARN',
                "{$rej} rejeição(ões) nas últimas " . self::REJEICAO_LOOKBACK_HORAS . 'h — investigue motivos SEFAZ municipal'
            );
        }

        return $this->makeCheck('rejeitadas_recentes', 'OK', "{$rej} rejeição(ões) em {$cutoff->diffForHumans()}");
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
