<?php

declare(strict_types=1);

namespace Modules\Financeiro\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Backfill plano_conta_id em fin_titulos NULL.
 *
 * Origem: 2026-05-20 — Wagner reportou DRE em prod zerada porque os 18.054
 * titulos do biz=4 ROTA LIVRE foram criados antes do schema fin_planos_conta
 * (campos plano_conta_id e categoria_id ficaram NULL em todos).
 *
 * Estratégia (Wagner aprovou):
 *  - tipo='receber' AND plano_conta_id IS NULL → '3.1.01.999 Vendas (a classificar)'
 *  - tipo='pagar'   AND plano_conta_id IS NULL → '5.1.99.999 Despesas (a classificar)'
 *
 * Cria as 2 contas idempotentes se não existirem pro biz (so attach se faltar).
 * DRE passa a mostrar dados em vez de R$ 0,00 em tudo. Eliana reatribui plano
 * correto via UI quando souber (US-FIN-CATEGORIA-UX backlog).
 *
 * Multi-tenant Tier 0 (ADR 0093 IRREVOGÁVEL): --business obrigatório, batch
 * SCOPED. Sem --business, recusa.
 *
 * Uso:
 *   php artisan financeiro:backfill-plano-conta --business=4 --dry
 *   php artisan financeiro:backfill-plano-conta --business=4
 *
 * Idempotente: re-roda só toca rows ainda NULL. Safe pra cron/CI.
 */
class BackfillPlanoContaCommand extends Command
{
    protected $signature = 'financeiro:backfill-plano-conta
        {--business= : ID do business (obrigatório — Tier 0 IRREVOGÁVEL)}
        {--dry : Mostra o que vai fazer sem aplicar}';

    protected $description = 'Backfill plano_conta_id em fin_titulos NULL (DRE depende disso)';

    private const CODIGO_RECEITA_ACL = '3.1.01.999';
    private const NOME_RECEITA_ACL = 'Vendas (a classificar)';
    private const CODIGO_DESPESA_ACL = '5.1.99.999';
    private const NOME_DESPESA_ACL = 'Despesas (a classificar)';

    public function handle(): int
    {
        $businessId = (int) $this->option('business');
        $dry = (bool) $this->option('dry');

        if ($businessId <= 0) {
            $this->error('--business=ID obrigatório (Tier 0 IRREVOGÁVEL — ADR 0093)');
            return self::FAILURE;
        }

        $this->info(($dry ? '[DRY-RUN] ' : '') . "Backfill plano_conta_id em business={$businessId}");

        // 1) Garantir/criar contas "(a classificar)"
        $receitaId = $this->ensureConta(
            $businessId,
            self::CODIGO_RECEITA_ACL,
            self::NOME_RECEITA_ACL,
            'receita',
            'credito',
            '3.1.01', // parent_codigo (RECEITA OPERACIONAL > Receita Bruta de Vendas)
            $dry
        );

        $despesaId = $this->ensureConta(
            $businessId,
            self::CODIGO_DESPESA_ACL,
            self::NOME_DESPESA_ACL,
            'despesa',
            'debito',
            '5.1', // parent_codigo (DESPESAS OPERACIONAIS)
            $dry
        );

        if ($dry) {
            $this->line("  → receita conta ID seria criada (ou existe): {$receitaId}");
            $this->line("  → despesa conta ID seria criada (ou existe): {$despesaId}");
        }

        // 2) Contar quantos titulos vão ser atualizados (sem mexer)
        $countReceber = DB::table('fin_titulos')
            ->where('business_id', $businessId)
            ->where('tipo', 'receber')
            ->whereNull('plano_conta_id')
            ->where('status', '!=', 'cancelado')
            ->count();

        $countPagar = DB::table('fin_titulos')
            ->where('business_id', $businessId)
            ->where('tipo', 'pagar')
            ->whereNull('plano_conta_id')
            ->where('status', '!=', 'cancelado')
            ->count();

        $this->line("  Titulos a backfillar (NULL ainda):");
        $this->line("    receber → " . number_format($countReceber, 0, ',', '.') . " títulos");
        $this->line("    pagar   → " . number_format($countPagar, 0, ',', '.') . " títulos");
        $this->line('    TOTAL   → ' . number_format($countReceber + $countPagar, 0, ',', '.'));

        if ($dry) {
            $this->info('[DRY-RUN] nenhum UPDATE executado. Re-rode sem --dry pra aplicar.');
            return self::SUCCESS;
        }

        if ($countReceber + $countPagar === 0) {
            $this->info('Nada a fazer — todos titulos já têm plano_conta_id.');
            return self::SUCCESS;
        }

        // 3) UPDATE batch (transação)
        DB::transaction(function () use ($businessId, $receitaId, $despesaId) {
            DB::table('fin_titulos')
                ->where('business_id', $businessId)
                ->where('tipo', 'receber')
                ->whereNull('plano_conta_id')
                ->where('status', '!=', 'cancelado')
                ->update(['plano_conta_id' => $receitaId, 'updated_at' => now()]);

            DB::table('fin_titulos')
                ->where('business_id', $businessId)
                ->where('tipo', 'pagar')
                ->whereNull('plano_conta_id')
                ->where('status', '!=', 'cancelado')
                ->update(['plano_conta_id' => $despesaId, 'updated_at' => now()]);
        });

        $this->info("✓ Backfill concluído em business={$businessId}.");
        $this->info("  Eliana pode reatribuir plano correto via UI (/financeiro/plano-contas) ou query SQL.");

        return self::SUCCESS;
    }

    /**
     * Cria conta no fin_planos_conta se não existir. Retorna o id.
     * Idempotente: re-roda devolve o mesmo id.
     */
    private function ensureConta(
        int $businessId,
        string $codigo,
        string $nome,
        string $tipo,
        string $natureza,
        string $parentCodigo,
        bool $dry
    ): int {
        $existing = DB::table('fin_planos_conta')
            ->where('business_id', $businessId)
            ->where('codigo', $codigo)
            ->value('id');

        if ($existing) {
            return (int) $existing;
        }

        if ($dry) {
            return 0;
        }

        $parentId = DB::table('fin_planos_conta')
            ->where('business_id', $businessId)
            ->where('codigo', $parentCodigo)
            ->value('id');

        $id = DB::table('fin_planos_conta')->insertGetId([
            'business_id'       => $businessId,
            'codigo'            => $codigo,
            'nome'              => $nome,
            'tipo'              => $tipo,
            'nivel'             => 4,
            'parent_id'         => $parentId,
            'natureza'          => $natureza,
            'aceita_lancamento' => true,
            'protegido'         => false,
            'ativo'             => true,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        $this->line("  + Conta criada: {$codigo} \"{$nome}\" (id={$id}, business_id={$businessId})");

        return (int) $id;
    }
}
