<?php

declare(strict_types=1);

namespace Modules\RecurringBilling\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\RecurringBilling\Services\CobradorCartaoRecorrente;

/**
 * `rb:cobrar-cartao` — cobra no cartão salvo as faturas vencidas das assinaturas e tenta de novo na
 * recusa leve, nos intervalos de `RB_CARTAO_RETRY_DIAS` (US-RB-013 · decisão [W] 2026-10-27).
 *
 * `--dry-run` lista, por business, o que seria cobrado (fatura, tentativa, valor) sem gravar nem
 * chamar o gateway — é a tabela antes→depois da regra mestre de valor. Sem `--dry-run`, só roda com
 * `RB_COBRAR_CARTAO=true` (default desligado).
 */
class CobrarCartaoCommand extends Command
{
    protected $signature = 'rb:cobrar-cartao
        {--business= : só este business}
        {--data= : "hoje" da rodada (Y-m-d), para conferir uma data}
        {--dry-run : lista o que seria cobrado, sem gravar nem chamar o gateway}';

    protected $description = 'Cobra no cartão salvo as faturas vencidas das assinaturas e tenta de novo na recusa leve.';

    public function handle(CobradorCartaoRecorrente $cobrador): int
    {
        $dryRun = (bool) $this->option('dry-run');
        if (! $dryRun && ! config('recurringbilling.cobrar_cartao', false)) {
            $this->info('Cobrança no cartão desligada (RB_COBRAR_CARTAO=false) — nada cobrado. Use --dry-run para ver o que seria cobrado.');

            return self::SUCCESS;
        }

        $businesses = DB::table('rb_subscriptions')
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->whereNotNull('cartao_salvo_id')
            ->when($this->option('business'), fn ($q, $b) => $q->where('business_id', (int) $b))
            ->distinct()->orderBy('business_id')->pluck('business_id')->map('intval');

        if ($businesses->isEmpty()) {
            $this->info('Nenhuma assinatura ativa com cartão salvo.');

            return self::SUCCESS;
        }

        $falhas = 0;
        foreach ($businesses as $biz) {
            $linhas = $cobrador->cobrar($biz, $this->option('data') ?: null, $dryRun);
            if ($linhas !== []) {
                $this->line("business {$biz}:");
                $this->table(['fatura', 'documento', 'vencimento', 'valor (centavos)', 'tentativa', 'ação', 'motivo'],
                    array_map(fn ($l) => [$l['fatura_id'], $l['numero_documento'], $l['vencimento'], $l['valor_centavos'],
                        $l['tentativa'], $l['acao'], $l['motivo'] ?? ''], $linhas));
            }
            $falhas += count(array_filter($linhas, fn ($l) => in_array($l['acao'], ['failed', 'sem_credencial'], true)));
        }

        return $falhas > 0 ? self::FAILURE : self::SUCCESS;
    }
}
