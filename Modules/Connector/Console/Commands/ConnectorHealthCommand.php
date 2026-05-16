<?php

declare(strict_types=1);

namespace Modules\Connector\Console\Commands;

use App\Util\OtelHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Health-check diário do módulo Connector — Wave 16 governance v3 D9.c.
 *
 * Verificações:
 *   1. Passport oauth_access_tokens — quantos válidos (não revogados/expirados) em 24h
 *   2. Licenca_Computador — último acesso recente cross-business (drift detection)
 *   3. Rotas Connector registradas (sanity check ≥20 rotas /connector/api/*)
 *
 * Roda 06:15 BRT (após jana:health-check 06:00). Loga estruturado pra dashboard
 * /copiloto/admin/qualidade. Exit 0 OK, 1 alerta.
 *
 * Uso:
 *   php artisan connector:health
 *   php artisan connector:health --detail   (tabela detalhada por biz)
 *   php artisan connector:health --notify   (ALERT em log se algo falhou)
 *
 * @see ADR 0155 module-grade-v3 D9
 * @see Modules\Governance\Console\Commands\CharterHealthCommand (pattern reference)
 */
class ConnectorHealthCommand extends Command
{
    protected $signature = 'connector:health
                            {--detail : Saída detalhada por business (cuidado em prod)}
                            {--notify : Loga ALERT em log channel se algo falhou}';

    protected $description = 'Health-check diário Connector API (tokens Passport + licenças Delphi + rotas)';

    public function handle(): int
    {
        return OtelHelper::spanBiz('connector.health.run', function () {
            return $this->doHandle();
        }, ['connector.command' => 'connector:health']);
    }

    private function doHandle(): int
    {
        $report = [
            'tokens_active_24h' => $this->checkActiveTokens24h(),
            'licencas_recent_24h' => $this->checkLicencasRecent24h(),
            'rotas_registradas' => $this->checkRoutesRegistered(),
        ];

        $issues = [];

        // Threshold tokens: ≥1 token ativo em 24h é o mínimo aceitável (clientes externos).
        if ($report['tokens_active_24h']['count'] === 0 && $report['tokens_active_24h']['skipped'] === false) {
            $issues[] = 'tokens_active_24h=0 (clientes externos não autenticaram)';
        }

        // Threshold licenças: ≥1 acesso Delphi em 24h.
        if ($report['licencas_recent_24h']['count'] === 0 && $report['licencas_recent_24h']['skipped'] === false) {
            $issues[] = 'licencas_recent_24h=0 (nenhum WR Comercial acessou)';
        }

        // Threshold rotas: ≥20 (smoke test).
        if ($report['rotas_registradas']['count'] < 20) {
            $issues[] = "rotas_registradas={$report['rotas_registradas']['count']} (esperado ≥20)";
        }

        $allOk = $issues === [];

        Log::channel('stack')->info('connector:health', [
            'ok' => $allOk,
            'tokens_active_24h' => $report['tokens_active_24h']['count'],
            'licencas_recent_24h' => $report['licencas_recent_24h']['count'],
            'rotas_registradas' => $report['rotas_registradas']['count'],
            'issues' => $issues,
        ]);

        if ($this->option('detail')) {
            $this->table(['Check', 'Status', 'Valor'], [
                ['tokens_active_24h', $report['tokens_active_24h']['skipped'] ? 'SKIP' : 'OK', $report['tokens_active_24h']['count']],
                ['licencas_recent_24h', $report['licencas_recent_24h']['skipped'] ? 'SKIP' : 'OK', $report['licencas_recent_24h']['count']],
                ['rotas_registradas', $report['rotas_registradas']['count'] >= 20 ? 'OK' : 'FAIL', $report['rotas_registradas']['count']],
            ]);
        }

        if ($this->option('notify') && ! $allOk) {
            $detail = implode(', ', $issues);
            Log::channel('stack')->error("connector:health ALERT — issues: {$detail}");
        }

        $this->line($allOk ? 'connector:health OK' : 'connector:health FAIL — ' . implode(', ', $issues));

        return $allOk ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Conta tokens Passport ativos (não revogados, não expirados) usados nas últimas 24h.
     *
     * @return array{count: int, skipped: bool}
     */
    private function checkActiveTokens24h(): array
    {
        if (! Schema::hasTable('oauth_access_tokens')) {
            return ['count' => 0, 'skipped' => true];
        }

        $count = DB::table('oauth_access_tokens')
            ->where('revoked', false)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->where('updated_at', '>=', now()->subHours(24))
            ->count();

        return ['count' => $count, 'skipped' => false];
    }

    /**
     * Conta licenças Delphi com acesso nas últimas 24h.
     *
     * @return array{count: int, skipped: bool}
     */
    private function checkLicencasRecent24h(): array
    {
        if (! Schema::hasTable('licenca_computador')) {
            return ['count' => 0, 'skipped' => true];
        }

        // dt_ultimo_acesso é atualizada em LicencaComputadorController + OImpressoRegistroController.
        $count = DB::table('licenca_computador')
            ->where('dt_ultimo_acesso', '>=', now()->subHours(24))
            ->count();

        return ['count' => $count, 'skipped' => false];
    }

    /**
     * Conta rotas registradas com prefix /connector/api/.
     *
     * @return array{count: int}
     */
    private function checkRoutesRegistered(): array
    {
        $count = collect(app('router')->getRoutes())->filter(function ($r) {
            return str_starts_with($r->uri(), 'connector/api');
        })->count();

        return ['count' => $count];
    }
}
