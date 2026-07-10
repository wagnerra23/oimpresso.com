<?php

declare(strict_types=1);

namespace Modules\RecurringBilling\Console\Commands;

use Illuminate\Console\Command;
use Modules\RecurringBilling\Services\GatewayBackfillService;

/**
 * US-RB-052 · CLI do backfill de gateway nas assinaturas dormentes.
 *
 * DEFAULT É DRY-RUN (Tier 0 dinheiro — REGRA MESTRE memory/proibicoes.md):
 * sem --execute NADA é escrito. O output antes→depois deste comando é a
 * evidência exigida pra aprovação [W] antes da execução real.
 *
 * Este comando NÃO emite cobrança — só atribui `metadata.gateway`. A emissão
 * de boleto real depende de (a) credencial ativa em prod e (b) régua de
 * emissão (hoje NENHUM código de produção cria ChargeAttempt — gap documentado
 * no PR US-RB-052). Decisão de emitir = Wagner (R1/R10).
 *
 * Uso:
 *   php artisan rb:backfill-gateway 1                 # dry-run biz=1 (default)
 *   php artisan rb:backfill-gateway 1 --detail        # dry-run + tabela por assinatura
 *   php artisan rb:backfill-gateway 1 --execute       # aplica de fato (após aprovação W)
 */
class BackfillGatewayCommand extends Command
{
    protected $signature = 'rb:backfill-gateway
                            {business : business_id alvo (obrigatório — Tier 0, sem modo "todos")}
                            {--execute : Aplica de fato. Sem esta flag roda DRY-RUN}
                            {--detail : Mostra tabela antes→depois por assinatura}';

    protected $description = 'US-RB-052 — atribui gateway (metadata.gateway) às assinaturas dormentes com base na credencial da conta bancária. Default dry-run.';

    public function handle(GatewayBackfillService $service): int
    {
        $businessId = (int) $this->argument('business');
        $execute = (bool) $this->option('execute');

        if ($businessId <= 0) {
            $this->error('business_id inválido.');

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Backfill gateway biz=%d — modo %s',
            $businessId,
            $execute ? 'EXECUTE (escreve!)' : 'DRY-RUN (nada escrito)',
        ));

        $stats = $service->run($businessId, $execute);

        if ($this->option('detail')) {
            $this->table(
                ['subscription_id', 'status', 'conta', 'banco', 'antes', 'depois', 'fonte', 'motivo bloqueio'],
                array_map(static fn (array $l): array => [
                    $l['subscription_id'],
                    $l['status'],
                    $l['conta_bancaria_id'] ?? '—',
                    $l['banco_codigo'] ?? '—',
                    'NULL',
                    $l['depois'] ?? '—',
                    $l['fonte'] ?? '—',
                    $l['motivo'] ?? '—',
                ], $stats['linhas']),
            );
        }

        $this->newLine();
        $this->info(sprintf('Assinaturas com gateway=NULL: %d', $stats['total_null']));
        $this->info(sprintf('  %s atribuídas: %d', $execute ? 'Foram' : 'Seriam', $stats['atribuidas']));

        foreach ($stats['por_gateway'] as $gateway => $qtd) {
            $this->line(sprintf('    - %s: %d', $gateway, $qtd));
        }

        $this->warn(sprintf('  Bloqueadas: %d', $stats['bloqueadas']));

        foreach ($stats['por_motivo'] as $motivo => $qtd) {
            $this->line(sprintf('    - %s: %d', $motivo, $qtd));
        }

        $this->line(sprintf('  Skip (já tinham gateway): %d', $stats['skipped']));

        if (! $execute) {
            $this->newLine();
            $this->warn('DRY-RUN — nada foi escrito. Pra aplicar: --execute (requer aprovação Wagner — Tier 0 dinheiro).');
        }

        return self::SUCCESS;
    }
}
