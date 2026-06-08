<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Purga nonces antigos da tabela `webhook_nonces` (>24h).
 *
 * **US-WA-082** — Replay window é 5min, mas mantemos histórico 24h por
 * margem segurança contra time skew + audit forense. Após 24h é seguro
 * deletar (qualquer replay desse nonce já seria rejeitado pelo
 * `REPLAY_WINDOW_SECONDS=300` no middleware).
 *
 * Schedule: hourly (Kernel.php). Idempotente — DELETE com WHERE.
 *
 * @see Modules/Whatsapp/Http/Middleware/VerifyBaileysWebhookHmac.php
 */
class CleanupWebhookNoncesCommand extends Command
{
    protected $signature = 'whatsapp:cleanup-webhook-nonces
                            {--max-age=24 : Max age em horas pra manter nonce}';

    protected $description = 'Purga nonces >24h da tabela webhook_nonces (replay protection cleanup).';

    public function handle(): int
    {
        $maxAgeHours = (int) $this->option('max-age');
        $cutoff = now()->subHours($maxAgeHours);

        $deleted = DB::table('webhook_nonces')
            ->where('created_at', '<', $cutoff)
            ->delete();

        Log::info('[whatsapp.cleanup-webhook-nonces]', [
            'deleted' => $deleted,
            'max_age_hours' => $maxAgeHours,
            'cutoff' => $cutoff->toIso8601String(),
        ]);

        $this->info("✓ Deleted {$deleted} nonces older than {$maxAgeHours}h");

        return self::SUCCESS;
    }
}
