<?php

declare(strict_types=1);

namespace Modules\RecurringBilling\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Modules\RecurringBilling\Observers\SubscriptionCachedFieldsObserver;

/**
 * Aplica a retroatividade WR Comercial → RecurringBilling biz=1.
 *
 * Sessão Eliana 5-7/jun deixou 4 SQLs prontos em
 * `scripts/legacy-migration/sql-wr2-pessoas/output/planos-mensalidade/` mas
 * só rodou `rb-plans-insert-2026-06-07.sql` e `rb-subscriptions-insert-2026-06-07.sql`
 * (as 109 subscriptions ativas existem em prod biz=1). As 4 etapas (52 planos
 * cancelados + ajuste start_date dos ativos + 3.389 rb_invoices históricas) não
 * foram aplicadas — KPIs RB ficam zerados porque `rb_invoices` não existem
 * (ex: MHUNDO COMUNICACAO VISUAL LTDA — Cobranças pagas=0, LTV=R$0, histórico vazio).
 *
 * Este command resolve isso em 6 etapas idempotentes:
 *  E1 — Aplica etapa1 (planos+subs cancelados, ajuste start_date)
 *  E2/E3/E4 — Aplica invoices 2024/2025/2026
 *  E5 — Recalcula caches em rb_subscriptions (paid/missed/ltv)
 *  E6 — Interliga fin_titulos ↔ rb_invoices (origem='recurring', origem_id=rb_invoice.id)
 *
 * Idempotência: cada etapa detecta se já rodou via
 *   `JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.source')) = '<source-tag>'`
 * UPDATEs etapa1 são naturalmente idempotentes (valores fixos).
 *
 * Multi-tenant Tier 0 (ADR 0093): --business-id obrigatório. CLI sem HTTP context
 * — não usa session(); filtros sempre passam business_id explícito.
 *
 * Uso:
 *   php artisan rb:apply-wr-comercial-retroatividade --business-id=1
 *   php artisan rb:apply-wr-comercial-retroatividade --business-id=1 --dry-run
 *   php artisan rb:apply-wr-comercial-retroatividade --business-id=1 --skip-bridge
 */
class ApplyWrComercialRetroatividadeCommand extends Command
{
    protected $signature = 'rb:apply-wr-comercial-retroatividade
                            {--business-id= : business_id alvo (obrigatório — Tier 0 ADR 0093)}
                            {--dry-run : Mostra contagens antes/depois sem persistir}
                            {--skip-bridge : Pula E6 (UPDATE fin_titulos.origem=recurring)}';

    protected $description = 'Aplica SQLs retroatividade WR Comercial → RB biz=1 (idempotente)';

    private const SOURCE_ETAPA1_PLANS = 'wr-comercial-migracao-2026-06-07-etapa1';

    private const SOURCE_INVOICES = 'wr-comercial-retroatividade-2026-06-07';

    private const BASE_PATH = 'scripts/legacy-migration/sql-wr2-pessoas/output/planos-mensalidade';

    public function handle(SubscriptionCachedFieldsObserver $observer): int
    {
        $bizId = (int) $this->option('business-id');
        if ($bizId <= 0) {
            $this->error('--business-id obrigatório (multi-tenant Tier 0 ADR 0093).');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $skipBridge = (bool) $this->option('skip-bridge');

        $this->info(sprintf(
            '== RB Retroatividade WR Comercial biz=%d %s==',
            $bizId,
            $dryRun ? '(DRY RUN) ' : ''
        ));

        $this->printSnapshot($bizId, 'ANTES');

        // E1: etapa1 — 52 planos cancelados + UPDATEs start_date
        $this->runEtapa1($bizId, $dryRun);

        // E2/E3/E4: invoices históricas
        $this->runInvoiceEtapas($bizId, $dryRun);

        // E5: recalc caches
        $this->runCacheBackfill($bizId, $dryRun, $observer);

        // E6: ponte fin_titulos.origem='recurring'
        if (! $skipBridge) {
            $this->runBridge($bizId, $dryRun);
        } else {
            $this->warn('E6 pulado por --skip-bridge.');
        }

        $this->printSnapshot($bizId, 'DEPOIS');

        return self::SUCCESS;
    }

    /** E1: 52 planos cancelados + UPDATEs start_date (idempotente por source metadata). */
    private function runEtapa1(int $bizId, bool $dryRun): void
    {
        $this->line("\n-- E1: etapa1-cancelados-ativos.sql");

        $existing = DB::table('rb_plans')
            ->where('business_id', $bizId)
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.source')) = ?", [self::SOURCE_ETAPA1_PLANS])
            ->count();

        if ($existing > 0) {
            $this->info(sprintf('  ✓ Já aplicado (%d planos com source=%s).', $existing, self::SOURCE_ETAPA1_PLANS));

            return;
        }

        if ($dryRun) {
            $this->warn('  DRY RUN — etapa1 seria aplicada (52 planos + UPDATEs start_date).');

            return;
        }

        $this->applySqlFile($bizId, 'etapa1-cancelados-ativos.sql');
        $this->info('  ✓ E1 aplicada.');
    }

    /** E2/E3/E4: invoices históricas (idempotente via JSON metadata.source). */
    private function runInvoiceEtapas(int $bizId, bool $dryRun): void
    {
        $existing = DB::table('rb_invoices')
            ->where('business_id', $bizId)
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.source')) = ?", [self::SOURCE_INVOICES])
            ->count();

        $this->line(sprintf("\n-- E2/E3/E4: invoices históricas (existentes: %d)", $existing));

        if ($existing >= 3000) {
            $this->info(sprintf('  ✓ Já aplicado (%d rb_invoices com source=%s).', $existing, self::SOURCE_INVOICES));

            return;
        }

        $files = ['etapa2-invoices-2024.sql', 'etapa3-invoices-2025.sql', 'etapa4-invoices-2026.sql'];
        foreach ($files as $file) {
            if ($dryRun) {
                $count = $this->countInsertsInFile($file);
                $this->warn(sprintf('  DRY RUN — %s seria aplicado (%d INSERTs).', $file, $count));

                continue;
            }

            $this->applySqlFile($bizId, $file);
            $this->info(sprintf('  ✓ %s aplicado.', $file));
        }
    }

    /** E5: recompute total_paid_cached/failed_count_cached/total_revenue_cached. */
    private function runCacheBackfill(int $bizId, bool $dryRun, SubscriptionCachedFieldsObserver $observer): void
    {
        $this->line("\n-- E5: backfill caches rb_subscriptions");

        if ($dryRun) {
            $this->warn('  DRY RUN — rb:backfill-cached-fields seria executado.');

            return;
        }

        $this->call('rb:backfill-cached-fields', ['--business' => (string) $bizId]);
        $this->info('  ✓ E5 caches recalculados.');
    }

    /**
     * E6: UPDATE fin_titulos → origem='recurring', origem_id=rb_invoices.id
     * via JOIN por metadata.legacy_titulo_id ↔ fin_titulos.legacy_id.
     *
     * Idempotente: WHERE ft.origem='manual' AND ft.origem_id IS NULL — só toca
     * o que ainda não foi vinculado. Toca apenas títulos cujo legacy_id casa com
     * uma rb_invoice com source=wr-comercial-retroatividade.
     */
    private function runBridge(int $bizId, bool $dryRun): void
    {
        $this->line("\n-- E6: ponte fin_titulos.origem='recurring' ← rb_invoices");

        if (! \Illuminate\Support\Facades\Schema::hasColumn('fin_titulos', 'legacy_id')) {
            $this->warn('  fin_titulos.legacy_id não existe — pulando E6. (Rode `php artisan migrate` antes.)');

            return;
        }

        // Match candidates: rb_invoices com source retroatividade + fin_titulos ainda 'manual'
        $candidatesSql = <<<'SQL'
            SELECT COUNT(*) AS qtd
            FROM fin_titulos ft
            INNER JOIN rb_invoices rb
                ON CAST(JSON_UNQUOTE(JSON_EXTRACT(rb.metadata,'$.legacy_titulo_id')) AS UNSIGNED) = CAST(ft.legacy_id AS UNSIGNED)
                AND rb.business_id = ft.business_id
            WHERE ft.business_id = ?
              AND ft.origem = 'manual'
              AND ft.origem_id IS NULL
              AND ft.legacy_id IS NOT NULL
              AND JSON_UNQUOTE(JSON_EXTRACT(rb.metadata,'$.source')) = ?
        SQL;

        $candidates = (int) DB::selectOne($candidatesSql, [$bizId, self::SOURCE_INVOICES])->qtd;

        $this->info(sprintf('  Candidatos pra vincular: %d fin_titulos.', $candidates));

        if ($candidates === 0) {
            $this->info('  ✓ Nada a vincular (já feito ou sem match legacy_id).');

            return;
        }

        if ($dryRun) {
            $this->warn(sprintf('  DRY RUN — %d fin_titulos seriam atualizados.', $candidates));

            return;
        }

        // UNIQUE (business_id, origem, origem_id, parcela_numero) — invoice_id é unique
        // dentro do business, então JOIN 1-pra-1 sob source filter. parcela_numero NULL
        // não conflita com itself porque o INSERT do importer Eliana sempre setou origem
        // 'manual' + origem_id NULL. Pós-update, (biz, 'recurring', invoice_id, NULL) é
        // único por construção (invoice_id é PK auto-increment).
        $updateSql = <<<'SQL'
            UPDATE fin_titulos ft
            INNER JOIN rb_invoices rb
                ON CAST(JSON_UNQUOTE(JSON_EXTRACT(rb.metadata,'$.legacy_titulo_id')) AS UNSIGNED) = CAST(ft.legacy_id AS UNSIGNED)
                AND rb.business_id = ft.business_id
            SET ft.origem = 'recurring',
                ft.origem_id = rb.id,
                ft.updated_at = NOW()
            WHERE ft.business_id = ?
              AND ft.origem = 'manual'
              AND ft.origem_id IS NULL
              AND ft.legacy_id IS NOT NULL
              AND JSON_UNQUOTE(JSON_EXTRACT(rb.metadata,'$.source')) = ?
        SQL;

        $affected = DB::affectingStatement($updateSql, [$bizId, self::SOURCE_INVOICES]);
        $this->info(sprintf('  ✓ E6 vinculou %d fin_titulos a rb_invoices.', $affected));
    }

    /** Lê arquivo SQL, remove transação/comentários/SELECTs verificação, executa em transação Laravel. */
    private function applySqlFile(int $bizId, string $filename): void
    {
        $path = base_path(self::BASE_PATH.'/'.$filename);

        if (! File::exists($path)) {
            throw new \RuntimeException(sprintf('SQL file não encontrado: %s', $path));
        }

        $sql = File::get($path);

        // Strip cabeçalhos não-executáveis pra delegar transação ao Laravel
        $sql = preg_replace('/^--.*$/m', '', $sql);
        $sql = preg_replace('/^\s*START TRANSACTION;\s*$/mi', '', $sql);
        $sql = preg_replace('/^\s*COMMIT;\s*$/mi', '', $sql);
        // Remove SELECT de verificação no fim (sem INSERT/UPDATE, só log)
        $sql = preg_replace('/^\s*SELECT\s+[^;]+;\s*$/mi', '', $sql);

        DB::transaction(function () use ($sql) {
            DB::unprepared($sql);
        });
    }

    private function countInsertsInFile(string $filename): int
    {
        $path = base_path(self::BASE_PATH.'/'.$filename);

        if (! File::exists($path)) {
            return 0;
        }

        return substr_count(File::get($path), "\nINSERT");
    }

    private function printSnapshot(int $bizId, string $label): void
    {
        $plans = DB::table('rb_plans')->where('business_id', $bizId)->count();
        $subs = DB::table('rb_subscriptions')->where('business_id', $bizId)->count();
        $invoices = DB::table('rb_invoices')->where('business_id', $bizId)->count();
        $paid = DB::table('rb_invoices')->where('business_id', $bizId)->where('status', 'paid')->count();

        $bridge = 0;
        if (\Illuminate\Support\Facades\Schema::hasColumn('fin_titulos', 'legacy_id')) {
            $bridge = DB::table('fin_titulos')
                ->where('business_id', $bizId)
                ->where('origem', 'recurring')
                ->whereNotNull('origem_id')
                ->count();
        }

        $this->line(sprintf(
            "\n-- snapshot %s biz=%d: rb_plans=%d · rb_subscriptions=%d · rb_invoices=%d (paid=%d) · fin_titulos.recurring=%d",
            $label,
            $bizId,
            $plans,
            $subs,
            $invoices,
            $paid,
            $bridge
        ));
    }
}
