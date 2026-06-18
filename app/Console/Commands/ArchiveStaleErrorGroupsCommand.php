<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Errors\ErrorGrouper;
use Illuminate\Console\Command;

/**
 * errors:archive-stale-groups — janela de decaimento dos grupos de erro (Fase 2 · E-2).
 *
 * Arquiva grupos abertos sem ocorrência há N dias (default config
 * `errors.group_decay_days`). Plataforma/governança — NÃO é business-scoped
 * (error_groups é repo-wide; o dedup_key já carrega o business).
 *
 * Agendado daily 04:00 (app/Console/Kernel.php).
 *
 * @see prototipo-ui/handoffs/erros-dedup.md
 */
class ArchiveStaleErrorGroupsCommand extends Command
{
    protected $signature = 'errors:archive-stale-groups
        {--days= : Dias sem ocorrência pra arquivar (default config errors.group_decay_days)}
        {--detail : Log detalhado}';

    protected $description = 'Arquiva grupos de erro abertos sem ocorrência há N dias (decaimento E-2).';

    public function handle(ErrorGrouper $grouper): int
    {
        $days = (int) ($this->option('days') ?: config('errors.group_decay_days', 14));

        $archived = $grouper->archiveStale($days);

        $this->info("error_groups: {$archived} grupo(s) arquivado(s) (sem ocorrência há ≥{$days}d).");

        if ($this->option('detail')) {
            $this->line("Janela de decaimento: {$days} dias (config errors.group_decay_days).");
        }

        return self::SUCCESS;
    }
}
