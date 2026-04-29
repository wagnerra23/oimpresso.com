<?php

namespace Modules\Copiloto\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Copiloto\Entities\Conversa;
use Modules\Copiloto\Jobs\ExtrairFatosDaConversaJob;

/**
 * MEM-EVAL-2 — Backfill de fatos: re-roda ExtrairFatosDaConversaJob em
 * conversas históricas pra popular `copiloto_memoria_facts` agora que o
 * threshold foi relaxado (5 → 3).
 *
 * Uso:
 *   php artisan copiloto:backfill-fatos --business=4
 *   php artisan copiloto:backfill-fatos --business=4 --janela=20  # mais msgs por chamada
 *   php artisan copiloto:backfill-fatos --business=all --sync     # sync (não dispatcha pra queue)
 *
 * Roda 1 vez por conversa. Idempotência via dedup do MeilisearchDriver::lembrar.
 */
class BackfillFatosCommand extends Command
{
    protected $signature = 'copiloto:backfill-fatos
                            {--business=all : ID do business ou "all"}
                            {--janela=20 : Mensagens por chamada (default 20, max conversa toda)}
                            {--sync : Roda síncrono em vez de queue}';

    protected $description = 'Re-roda ExtrairFatosDaConversaJob nas conversas existentes pra popular memoria_facts';

    public function handle(): int
    {
        $bizOpt = (string) $this->option('business');
        $janela = (int) $this->option('janela');
        $sync = (bool) $this->option('sync');

        $query = Conversa::query();
        if (ctype_digit($bizOpt)) {
            $query->where('business_id', (int) $bizOpt);
        }

        $conversas = $query->orderBy('id')->get();

        $this->info(sprintf(
            'Backfill: %d conversa(s), janela=%d msgs, modo=%s',
            $conversas->count(),
            $janela,
            $sync ? 'sync' : 'queue',
        ));

        $factsAntes = (int) DB::table('copiloto_memoria_facts')->count();

        $disparados = 0;
        foreach ($conversas as $conv) {
            $job = new ExtrairFatosDaConversaJob(
                conversaId: $conv->id,
                businessId: (int) $conv->business_id,
                userId: (int) $conv->user_id,
                janelaMensagens: $janela,
            );

            if ($sync) {
                try {
                    $job->handle(app(\Modules\Copiloto\Contracts\MemoriaContrato::class));
                    $this->line("  ✓ conversa #{$conv->id} (biz={$conv->business_id}) processada");
                } catch (\Throwable $e) {
                    $this->error("  ✗ conversa #{$conv->id}: " . $e->getMessage());
                }
            } else {
                dispatch($job);
                $this->line("  ↻ conversa #{$conv->id} dispatchada");
            }
            $disparados++;
        }

        $this->info("Backfill: {$disparados} jobs " . ($sync ? 'executados' : 'dispatchados'));

        if ($sync) {
            $factsDepois = (int) DB::table('copiloto_memoria_facts')->count();
            $this->info("Facts: {$factsAntes} → {$factsDepois} (+{$this->getDiff($factsAntes, $factsDepois)})");
        }

        return self::SUCCESS;
    }

    protected function getDiff(int $a, int $b): string
    {
        $diff = $b - $a;
        return $diff >= 0 ? (string) $diff : (string) $diff;
    }
}
