<?php

declare(strict_types=1);

namespace Modules\Connector\Console\Commands;

use App\Util\OtelHelper;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
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
 * Thread Connector/08 (CONN-O8): cada execução PUBLICA uma entrada em
 *   storage/app/connector/health-history.json (os 3 valores, `issues[]` e a taxa de
 *   desvio do DelphiSync em 24 h), podando o que passa de 14 dias. A aba Saúde do
 *   painel (`/connector/client?aba=saude`) lê esse arquivo — não executa o comando.
 *   Os números são de TODOS os negócios: só superadmin vê o painel.
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

    /** Janela do histórico publicado (dias). */
    public const DIAS = 14;

    /** Caminho do histórico; `connector.health_history_path` sobrescreve (testes). */
    public static function caminhoHistorico(): string
    {
        return (string) (config('connector.health_history_path') ?: storage_path('app/connector/health-history.json'));
    }

    /** @return list<array<string, mixed>> entradas dos últimos DIAS, da mais antiga à mais recente; arquivo ausente/ilegível = [] */
    public static function historico(): array
    {
        $p = self::caminhoHistorico();
        $dados = is_file($p) ? json_decode((string) file_get_contents($p), true) : null;
        if (! is_array($dados)) {
            return [];
        }
        $corte = now()->subDays(self::DIAS);

        return array_values(array_filter($dados, function ($e) use ($corte) {
            try {
                return is_array($e) && isset($e['executado_em']) && Carbon::parse($e['executado_em'])->gte($corte);
            } catch (\Throwable) {
                return false;
            }
        }));
    }

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
            'delphi_desvio_24h' => $this->checkDelphiDesvio24h(),
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

        $this->publicar([
            'executado_em' => now()->toIso8601String(),
            'ok' => $allOk,
            // check pulado (tabela ausente) vira null: não medido não é zero
            'tokens_active_24h' => $report['tokens_active_24h']['skipped'] ? null : $report['tokens_active_24h']['count'],
            'licencas_recent_24h' => $report['licencas_recent_24h']['skipped'] ? null : $report['licencas_recent_24h']['count'],
            'rotas_registradas' => $report['rotas_registradas']['count'],
            'issues' => $issues,
            'delphi' => $report['delphi_desvio_24h'],
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

    /** Acrescenta e poda. Falha de escrita aparece na saída e no log; o exit code segue o dos limiares. */
    private function publicar(array $entrada): void
    {
        $p = self::caminhoHistorico();
        try {
            File::ensureDirectoryExists(dirname($p));
            $lista = [...self::historico(), $entrada];
            File::put($p, json_encode($lista, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), true);
            $this->line("connector:health histórico publicado em {$p} (".count($lista).' entrada(s))');
        } catch (\Throwable $e) {
            Log::channel('stack')->warning('connector:health histórico NÃO publicado: '.$e->getMessage());
            $this->line('connector:health histórico NÃO publicado: '.$e->getMessage());
        }
    }

    /**
     * Desvio do DelphiSync em 24 h, medido no registro do middleware `log.delphi`
     * (`licenca_log`, source delphi_middleware): chamadas com corpo e, delas, as que
     * chegaram em formato fora dos três conhecidos (`body_format = unknown`).
     * Taxa null sem chamada com corpo (sem denominador não há taxa).
     * @return array{chamadas_24h: int|null, desvios_24h: int|null, taxa_desvio: float|null}
     */
    private function checkDelphiDesvio24h(): array
    {
        if (! Schema::hasTable('licenca_log')) {
            return ['chamadas_24h' => null, 'desvios_24h' => null, 'taxa_desvio' => null];
        }

        $base = DB::table('licenca_log')
            ->where('source', 'delphi_middleware')
            ->where('event', 'api_call')
            ->where('created_at', '>=', now()->subHours(24))
            ->where('metadata->body_format', '!=', 'empty');
        $chamadas = (clone $base)->count();
        $desvios = (clone $base)->where('metadata->body_format', 'unknown')->count();

        return [
            'chamadas_24h' => $chamadas,
            'desvios_24h' => $desvios,
            'taxa_desvio' => $chamadas > 0 ? round($desvios / $chamadas, 4) : null,
        ];
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
