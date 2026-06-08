<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Detecta drift entre source local (`Modules/Whatsapp/daemon-node/src/`) e
 * daemon Baileys rodando em prod CT 100.
 *
 * **Por que existe (incident 2026-05-13):** descobrimos que source no CT 100
 * `/srv/build/whatsapp-baileys-daemon/src/` estava ~15 commits atrás do main
 * E ninguém sabia. Quando tentei rebuild manual, falhou compilation pq main
 * tem dependências de PRs anteriores que CT 100 ainda não recebeu. Drift
 * silencioso = bug oculto.
 *
 * **Como funciona:**
 *
 * 1. Calcula SHA hash do source local `Modules/Whatsapp/daemon-node/src/`
 *    via `git rev-parse HEAD:Modules/Whatsapp/daemon-node/src`
 * 2. Query GET /health no daemon prod — payload retorna `daemon_source_sha`
 *    (campo NOVO que o daemon expõe a partir do build :v823+)
 * 3. Compara — se diff, alerta + log estruturado
 *
 * **Quando rodar:**
 * - Cron semanal (segundas 09:00 BRT — antes do horário comercial)
 * - Manual `php artisan whatsapp:daemon-source-drift-check`
 *
 * **Fallback:** se daemon não expõe `daemon_source_sha` (versão velha que
 * não foi rebuilt depois deste PR), comando devolve warning suave e exit 0
 * — não trava cron.
 *
 * @see memory/requisitos/Whatsapp/runbooks/daemon-ct100-rebuild.md
 * @see Modules/Whatsapp/daemon-node/src/http/routes/health.ts
 */
class DaemonSourceDriftCheckCommand extends Command
{
    protected $signature = 'whatsapp:daemon-source-drift-check
                            {--fail-on-drift : Exit 1 se houver drift (CI usage)}';

    protected $description = 'Detecta drift entre source local Modules/Whatsapp/daemon-node/ e daemon CT 100 prod.';

    public function handle(): int
    {
        // 1. Source SHA local
        $localSha = trim((string) shell_exec(
            'git -C ' . escapeshellarg(base_path()) . ' rev-parse HEAD:Modules/Whatsapp/daemon-node/src 2>/dev/null'
        ));

        if ($localSha === '' || strlen($localSha) < 40) {
            $this->warn('⚠ Não foi possível resolver SHA local (git rev-parse falhou). Pulando check.');
            return self::SUCCESS;
        }

        // 2. SHA do daemon prod via /health
        $daemonUrl = (string) config('whatsapp.baileys.daemon_url', '');
        $apiKey = (string) config('whatsapp.baileys.api_key', '');

        if ($daemonUrl === '' || $apiKey === '') {
            $this->error('WHATSAPP_BAILEYS_DAEMON_URL/_API_KEY ausente.');
            return self::FAILURE;
        }

        try {
            $response = Http::withToken($apiKey)
                ->withoutVerifying() // FIXME(US-WA-058) — cert LE pendente
                ->timeout(10)
                ->get("{$daemonUrl}/health");
        } catch (\Throwable $e) {
            $this->error("Daemon offline: {$e->getMessage()}");
            return self::FAILURE;
        }

        if (! $response->successful()) {
            $this->error("Daemon /health HTTP {$response->status()}");
            return self::FAILURE;
        }

        $remoteSha = (string) ($response->json('daemon_source_sha') ?? '');

        if ($remoteSha === '') {
            $this->warn('⚠ Daemon não expõe `daemon_source_sha` no /health — versão pré-PR safeguards.');
            $this->line("Local SHA: {$localSha}");
            $this->line('Reload daemon após próximo deploy pra ativar drift detection.');
            return self::SUCCESS;
        }

        $inSync = $localSha === $remoteSha;
        $this->table(
            ['Origem', 'SHA source'],
            [
                ['Local main HEAD', $localSha],
                ['Daemon CT 100 prod', $remoteSha],
            ]
        );

        if ($inSync) {
            $this->info('✅ Daemon CT 100 está EM SYNC com main local.');
            return self::SUCCESS;
        }

        $this->error('❌ DRIFT detectado — daemon CT 100 está desatualizado vs main.');
        $this->line('Próxima ação: seguir [runbook daemon-ct100-rebuild.md](memory/requisitos/Whatsapp/runbooks/daemon-ct100-rebuild.md)');

        Log::warning('[whatsapp.daemon-source-drift]', [
            'local_sha' => $localSha,
            'remote_sha' => $remoteSha,
            'message' => 'Daemon CT 100 desatualizado vs main — rebuild necessário',
        ]);

        return $this->option('fail-on-drift') ? self::FAILURE : self::SUCCESS;
    }
}
