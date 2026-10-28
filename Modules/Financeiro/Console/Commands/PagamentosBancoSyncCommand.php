<?php

declare(strict_types=1);

namespace Modules\Financeiro\Console\Commands;

use Illuminate\Console\Command;
use Modules\Financeiro\Models\Concerns\BusinessScopeImpl;
use Modules\Financeiro\Models\PagamentoBanco;
use Modules\Financeiro\Services\PagamentoBancoService;

/**
 * `financeiro:pagamentos-banco-sync` — consulta no banco os pagamentos de título que aguardam
 * resposta e dá a baixa dos que o banco confirmou (US-FIN-079). Não ENVIA nada: só o usuário,
 * na tela, pede pagamento. Agendado a cada 15 min, e só com FINANCEIRO_PAGAMENTO_BANCO_ENABLED.
 */
class PagamentosBancoSyncCommand extends Command
{
    protected $signature = 'financeiro:pagamentos-banco-sync {--business= : só este business}';

    protected $description = 'Pagamento pelo banco: consulta os pedidos aguardando e baixa os confirmados.';

    public function handle(PagamentoBancoService $service): int
    {
        if (! config('financeiro.pagamento_banco.enabled')) {
            $this->warn('Pagamento pelo banco desligado (FINANCEIRO_PAGAMENTO_BANCO_ENABLED=false) — nada consultado.');

            return self::SUCCESS;
        }

        // SUPERADMIN: cron sem sessão; cada linha é sincronizada com o business_id DELA.
        $pendentes = PagamentoBanco::withoutGlobalScope(BusinessScopeImpl::class)
            ->where('status', PagamentoBanco::STATUS_AGUARDANDO)
            ->when($this->option('business'), fn ($q, $b) => $q->where('business_id', (int) $b))
            ->orderBy('id')
            ->get(['id', 'business_id']);

        $falhas = 0;
        foreach ($pendentes as $p) {
            try {
                $service->sincronizar((int) $p->business_id, (int) $p->id);
            } catch (\Throwable $e) {
                $falhas++;
                $this->error("Pagamento {$p->id} (business {$p->business_id}): ".$e->getMessage());
            }
        }

        $this->info("Pagamentos consultados: {$pendentes->count()} · falhas: {$falhas}.");

        return $falhas > 0 ? self::FAILURE : self::SUCCESS;
    }
}
