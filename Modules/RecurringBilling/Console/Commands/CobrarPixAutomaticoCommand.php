<?php

declare(strict_types=1);

namespace Modules\RecurringBilling\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\RecurringBilling\Services\CobradorPixAutomatico;
use Modules\RecurringBilling\Services\InvoiceGeneratorService;

/**
 * `rb:cobrar-pix-automatico` — cobra pelo Pix Automático do BCB as faturas das assinaturas com
 * recorrência aprovada, na janela de 10 a 2 dias antes do vencimento (US-RB-021 · decisão [W]
 * 2026-10-26).
 *
 * `--dry-run` lista, por business, o que seria cobrado (fatura, vencimento, valor) sem gravar nem
 * chamar o banco — é a tabela antes→depois da regra mestre de valor. Sem `--dry-run`, só roda com
 * `RB_COBRAR_PIX_AUTOMATICO=true` (default desligado).
 */
class CobrarPixAutomaticoCommand extends Command
{
    protected $signature = 'rb:cobrar-pix-automatico
        {--business= : só este business}
        {--data= : "hoje" da rodada (Y-m-d), para conferir uma data}
        {--dry-run : lista o que seria cobrado, sem gravar nem chamar o banco}';

    protected $description = 'Cobra pelo Pix Automático (BCB) as faturas de assinatura com recorrência aprovada, de 10 a 2 dias antes do vencimento.';

    public function handle(CobradorPixAutomatico $cobrador, InvoiceGeneratorService $gerador): int
    {
        $dryRun = (bool) $this->option('dry-run');
        if (! $dryRun && ! config('recurringbilling.cobrar_pix_automatico', false)) {
            $this->info('Cobrança automática por Pix Automático desligada (RB_COBRAR_PIX_AUTOMATICO=false) — nada cobrado. Use --dry-run para ver o que seria cobrado.');

            return self::SUCCESS;
        }

        $data = $this->option('data') ?: null;
        $businesses = DB::table('rb_subscriptions')
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->where('metadata->pix_automatico->status', 'APROVADA')
            ->when($this->option('business'), fn ($q, $b) => $q->where('business_id', (int) $b))
            ->distinct()
            ->orderBy('business_id')
            ->pluck('business_id')
            ->map('intval');

        if ($businesses->isEmpty()) {
            $this->info('Nenhuma assinatura com recorrência Pix Automático aprovada.');

            return self::SUCCESS;
        }

        $falhas = 0;
        foreach ($businesses as $biz) {
            $linhas = $cobrador->cobrar($biz, $data, $dryRun);
            if ($dryRun) {
                $aGerar = $gerador->run($biz, $data, true, CobradorPixAutomatico::JANELA_MAX_DIAS,
                    DB::table('rb_subscriptions')->where('business_id', $biz)->where('status', 'active')
                        ->where('metadata->pix_automatico->status', 'APROVADA')->pluck('id')->map('intval')->all());
                $this->line("business {$biz}: {$aGerar['generated']} fatura(s) ainda a gerar antes de cobrar");
            }
            if ($linhas !== []) {
                $this->table(['fatura', 'documento', 'vencimento', 'valor (centavos)', 'ação'],
                    array_map(fn ($l) => [$l['fatura_id'], $l['numero_documento'], $l['vencimento'], $l['valor_centavos'], $l['acao']], $linhas));
            }
            $falhas += count(array_filter($linhas, fn ($l) => in_array($l['acao'], ['erro', 'sem_credencial'], true)));
        }

        return $falhas > 0 ? self::FAILURE : self::SUCCESS;
    }
}
