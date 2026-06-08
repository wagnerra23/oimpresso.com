<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\Channel;

/**
 * Reset 1-comando dum channel Baileys quando trava.
 *
 * **Por que existe (Wagner 2026-05-13):** Wagner não quer ter que chamar Claude
 * pra cada vez que canal não conecta. Este comando resolve sozinho:
 *
 *   php artisan whatsapp:channel-reset 5
 *
 * Faz:
 *   1. DELETE /instances/{id} no daemon CT 100 (idempotente — 404 = ok)
 *   2. Reseta channel.status = 'setup', channel_health = 'never_checked'
 *   3. Output: o que aconteceu + próxima ação ("escanear QR em /atendimento/canais")
 *
 * Com `--reconnect`: também dispara POST /instances/{id}/connect logo após.
 * Útil pra ciclo completo via CLI quando UI não tá disponível.
 *
 * **Uso:**
 *   php artisan whatsapp:channel-reset 5                  # purge + reset DB
 *   php artisan whatsapp:channel-reset 5 --reconnect      # + dispara connect novo
 *   php artisan whatsapp:channel-reset 5 --dry-run        # preview
 *
 * **Multi-tenant Tier 0 (ADR 0093):** CLI sem session — `withoutGlobalScopes`
 * justificado por comment. Caller (Wagner em CLI ou cron) é trusted.
 *
 * @see Modules/Whatsapp/Console/Commands/ChannelsReconcilerCommand.php (bulk auto)
 * @see Modules/Whatsapp/Jobs/DeleteBaileysInstanceJob.php (async via Observer)
 */
class ChannelResetCommand extends Command
{
    protected $signature = 'whatsapp:channel-reset
                            {channel_id : ID do Channel pra resetar}
                            {--reconnect : Após reset, dispara POST /connect (gera novo QR)}
                            {--dry-run : Preview sem mudanças}';

    protected $description = 'Reseta channel Baileys travado: purge daemon + reset status DB (+ reconnect opcional).';

    public function handle(): int
    {
        $channelId = (int) $this->argument('channel_id');
        $reconnect = (bool) $this->option('reconnect');
        $isDryRun = (bool) $this->option('dry-run');

        // SUPERADMIN: CLI sem session — withoutGlobalScope justificado
        $channel = Channel::query()
            ->withoutGlobalScope(ScopeByBusiness::class)
            ->find($channelId);

        if (! $channel) {
            $this->error("Channel #{$channelId} não existe.");
            return self::FAILURE;
        }

        if ($channel->type !== Channel::TYPE_WHATSAPP_BAILEYS) {
            $this->error("Channel #{$channelId} é type={$channel->type} — só Baileys suporta reset via daemon.");
            return self::FAILURE;
        }

        $this->info("Channel #{$channel->id} ({$channel->label}) — biz={$channel->business_id} — status atual: {$channel->status}");

        if ($isDryRun) {
            $this->warn('🔵 DRY-RUN');
        }

        $daemonUrl = (string) config('whatsapp.baileys.daemon_url', '');
        $apiKey = (string) config('whatsapp.baileys.api_key', '');

        if ($daemonUrl === '' || $apiKey === '') {
            $this->error('WHATSAPP_BAILEYS_DAEMON_URL/_API_KEY ausente no .env.');
            return self::FAILURE;
        }

        $instanceId = 'ch-' . str_replace('-', '', (string) $channel->channel_uuid);

        // PASSO 1: DELETE no daemon
        $this->line('1) DELETE no daemon (purga creds revogadas)...');
        if (! $isDryRun) {
            $purge = Http::withToken($apiKey)
                ->withoutVerifying()
                ->timeout(10)
                ->delete("{$daemonUrl}/instances/{$instanceId}");

            if ($purge->successful() || $purge->status() === 404) {
                $this->info("   ✓ daemon respondeu " . $purge->status() . ' (' . ($purge->status() === 404 ? 'não existia' : 'purgado') . ')');
            } else {
                $this->error("   ✗ daemon HTTP {$purge->status()}: " . $purge->body());
                return self::FAILURE;
            }
        }

        // PASSO 2: Reset status DB
        $this->line('2) Reset DB channel.status → setup...');
        if (! $isDryRun) {
            $channel->forceFill([
                'status' => 'setup',
                'channel_health' => 'never_checked',
                'channel_health_consecutive_failures' => 0,
                'last_health_check_at' => now(),
                'last_health_message' => 'reset via artisan whatsapp:channel-reset',
            ])->save();
            $this->info("   ✓ DB atualizado");
        }

        // PASSO 3 (opcional): Reconnect
        if ($reconnect) {
            $this->line('3) POST /connect no daemon (gera QR novo)...');
            if (! $isDryRun) {
                $connect = Http::withToken($apiKey)
                    ->withoutVerifying()
                    ->timeout(15)
                    ->post("{$daemonUrl}/instances/{$instanceId}/connect", [
                        'business_uuid' => $channel->channel_uuid,
                        'business_id' => $channel->business_id,
                    ]);

                if ($connect->successful()) {
                    $state = $connect->json('state');
                    $this->info("   ✓ daemon respondeu " . $connect->status() . " (state={$state})");
                } else {
                    $this->warn("   ⚠ daemon HTTP {$connect->status()}: " . substr($connect->body(), 0, 200));
                }
            }
        }

        $this->newLine();
        $this->info('✅ Reset concluído.');
        if ($reconnect) {
            $this->line("Próximo passo: abra https://oimpresso.com/atendimento/canais e clique Conectar no canal #{$channelId} pra ver o QR.");
        } else {
            $this->line("Próximo passo: rode com --reconnect OU clique Conectar no canal #{$channelId} em /atendimento/canais.");
        }

        return self::SUCCESS;
    }
}
