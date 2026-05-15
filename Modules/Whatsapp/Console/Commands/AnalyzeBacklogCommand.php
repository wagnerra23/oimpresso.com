<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Console\Commands;

use Illuminate\Console\Command;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\Message;
use Modules\Whatsapp\Jobs\AnalisarMensagemInboundJob;

/**
 * US-WA-095 — Despacha análise IA pro backlog de mensagens inbound não analisadas.
 *
 * Uso típico (Wagner pareou canal Suporte e tem 21k msgs históricas):
 *
 *   php artisan whatsapp:analyze-backlog --business=1 --channel=10 --since=24h --limit=500 --dry-run
 *   php artisan whatsapp:analyze-backlog --business=1 --channel=10 --since=24h
 *
 * Filtros:
 *   --business=N    (obrigatório) — preserva Tier 0 multi-tenant
 *   --channel=N     opcional — limita a 1 canal específico
 *   --since=24h|7d  janela temporal (default: tudo histórico)
 *   --limit=N       limita batch (default: 1000)
 *   --dry-run       só conta + lista 10 amostra, NÃO dispatcha jobs
 *
 * Tier 0: business_id é obrigatório — sem ele, comando recusa.
 *
 * Job idempotente: re-rodar comando não duplica análise (Service respeita
 * `analise_at`). Mas re-dispatcha jobs pra msgs já analisadas — desperdício
 * de fila. Default filtra `analise_at IS NULL` pra evitar.
 *
 * @see Modules/Whatsapp/Jobs/AnalisarMensagemInboundJob.php
 */
class AnalyzeBacklogCommand extends Command
{
    protected $signature = 'whatsapp:analyze-backlog
        {--business= : business_id obrigatório (Tier 0)}
        {--channel= : limita a 1 channel_id (opcional)}
        {--since= : janela temporal (ex: 24h, 7d, 30d) — default tudo}
        {--limit=1000 : máximo de msgs por batch (default 1000)}
        {--dry-run : só conta + lista 10 amostra, NÃO dispatcha jobs}
        {--include-analyzed : reanalisar msgs já com analise_at (default: pula)}';

    protected $description = 'Despacha análise IA Jana pro backlog de mensagens inbound não analisadas (US-WA-095).';

    public function handle(): int
    {
        $businessId = (int) $this->option('business');
        if ($businessId <= 0) {
            $this->error('--business=N obrigatório (Tier 0 multi-tenant).');
            return Command::INVALID;
        }

        $channelId = $this->option('channel');
        $channelId = $channelId !== null ? (int) $channelId : null;

        $since = $this->option('since');
        $limit = max(1, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');
        $includeAnalyzed = (bool) $this->option('include-analyzed');

        if (! (bool) config('whatsapp.analise.enabled', false) && ! $dryRun) {
            $this->warn('whatsapp.analise.enabled=false — defina .env WHATSAPP_ANALISE_ENABLED=true antes de rodar.');
            $this->warn('Continue com --dry-run pra ver volume sem custo.');
            return Command::FAILURE;
        }

        // SUPERADMIN: comando artisan sem session HTTP — withoutGlobalScope
        // + filtro defensivo where('business_id') preserva Tier 0.
        $query = Message::query()
            ->withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $businessId)
            ->where('direction', Message::DIRECTION_INBOUND)
            ->where('is_internal_note', false)
            ->whereIn('type', ['text', 'interactive', 'template'])
            ->whereNotNull('body')
            ->where('body', '!=', '');

        if ($channelId !== null) {
            $query->whereExists(function ($sub) use ($channelId, $businessId) {
                $sub->select(\DB::raw(1))
                    ->from('conversations')
                    ->whereColumn('conversations.id', 'messages.conversation_id')
                    ->where('conversations.channel_id', $channelId)
                    ->where('conversations.business_id', $businessId);
            });
        }

        if (! $includeAnalyzed) {
            $query->whereNull('analise_at');
        }

        if (is_string($since) && $since !== '') {
            $cutoff = $this->parseSince($since);
            if ($cutoff !== null) {
                $query->where('created_at', '>=', $cutoff);
            } else {
                $this->warn("--since={$since} formato inválido (use ex: 24h, 7d, 30d). Ignorando filtro.");
            }
        }

        $total = (clone $query)->count();
        $this->info("Mensagens elegíveis biz={$businessId}" .
            ($channelId ? " channel={$channelId}" : '') .
            ': ' . number_format($total));

        if ($total === 0) {
            $this->info('Nada a fazer. Backlog vazio.');
            return Command::SUCCESS;
        }

        $batch = $query->orderBy('id')->limit($limit)->get(['id', 'business_id', 'body', 'created_at']);

        if ($dryRun) {
            $this->info("[DRY-RUN] Mostrando até 10 amostras (batch real seria {$batch->count()} msgs):");
            $this->table(
                ['id', 'biz', 'created_at', 'body (prev)'],
                $batch->take(10)->map(fn ($m) => [
                    $m->id,
                    $m->business_id,
                    (string) $m->created_at,
                    mb_substr((string) $m->body, 0, 60),
                ])->all()
            );
            $custoEstimado = $this->estimateCostBrl($batch->count());
            $this->info(sprintf('Custo estimado batch %d msgs: ~R$ %.2f (gpt-4o-mini)', $batch->count(), $custoEstimado));
            return Command::SUCCESS;
        }

        $bar = $this->output->createProgressBar($batch->count());
        $bar->start();
        $dispatched = 0;

        foreach ($batch as $msg) {
            AnalisarMensagemInboundJob::dispatch((int) $msg->business_id, (int) $msg->id);
            $dispatched++;
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();
        $this->info("Dispatched {$dispatched} jobs pra queue 'jana-analise'.");
        $this->info('Rode `php artisan queue:work database --queue=jana-analise --stop-when-empty` pra processar.');

        if ($total > $batch->count()) {
            $restante = $total - $batch->count();
            $this->warn("Ainda restam {$restante} msgs no backlog. Rode novamente pra processar próximo batch.");
        }

        return Command::SUCCESS;
    }

    /**
     * Parse "24h"|"7d"|"30d"|"6m" → Carbon timestamp passado.
     */
    protected function parseSince(string $since): ?\Carbon\Carbon
    {
        if (! preg_match('/^(\d+)\s*([hdmw])$/i', trim($since), $m)) {
            return null;
        }
        $n = (int) $m[1];
        $unit = strtolower($m[2]);

        return match ($unit) {
            'h' => now()->subHours($n),
            'd' => now()->subDays($n),
            'w' => now()->subWeeks($n),
            'm' => now()->subMonths($n),
            default => null,
        };
    }

    protected function estimateCostBrl(int $msgs): float
    {
        // Proxy ~150 tokens input + 50 output / msg, gpt-4o-mini pricing
        $inUsd = ($msgs * 150 / 1000) * 0.00015;
        $outUsd = ($msgs * 50 / 1000) * 0.0006;
        $cambio = (float) config('copiloto.ai.cambio_brl_usd', 5.50);
        return round(($inUsd + $outUsd) * $cambio, 4);
    }
}
