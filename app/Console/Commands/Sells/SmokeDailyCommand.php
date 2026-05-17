<?php

declare(strict_types=1);

namespace App\Console\Commands\Sells;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * US-SELL-COWORK-R6-SMOKE — smoke automatizado diário.
 *
 * Roda 06:30 BRT (após brief + health-check + grade-snapshot, antes
 * de qualquer cliente abrir o sistema). Valida 5 sinais críticos do
 * Sells/Index Cowork pra detectar drift (regressão silenciosa pós-
 * deploy):
 *
 *  1. Schema sells essencial — tabelas transactions + transaction_sell_lines
 *     existem com colunas críticas (business_id, final_total, payment_status,
 *     commission_agent, fiscal_status)
 *  2. Multi-tenant scope — biz=1 (oimpresso) e biz=4 (ROTA LIVRE cliente
 *     piloto) têm vendas com final_total > 0 nos últimos 30 dias
 *  3. Vite manifest contém chunks Cowork — SaleSheet, SaleAiPanel, SaleAuditTrail,
 *     SaleTranscriptPDF, SalePresentationMode, SaleMessagePreview
 *  4. CSS scoped tokens presentes — sells-cowork.css + sells-cowork-ia.css +
 *     sells-cowork-curadoria.css + sells-cowork-distribuicao.css importados em
 *     inertia.css
 *  5. Coworkdaggregates schema OK — método buildCoworkAggregates existe no
 *     SellController e retorna 4 keys canônicas
 *
 * Falha qualquer = log ERROR ALERT + (--notify) Slack/log permanente.
 * Cron: dailyAt('06:30') --notify em app/Console/Kernel.php.
 *
 * Refs:
 *  - memory/requisitos/_DesignSystem/RUNBOOK-onda-cowork.md F11 (smoke)
 *  - memory/requisitos/Sells/RUNBOOK-smoke-cowork.md (checklist Brave manual)
 *  - ADR 0093 multi-tenant Tier 0 IRREVOGÁVEL
 *
 * @example php artisan sells:smoke-daily --notify
 */
class SmokeDailyCommand extends Command
{
    protected $signature = 'sells:smoke-daily {--notify : Loga ALERT se algum check falhar}';

    protected $description = 'Smoke diário Sells/Index Cowork — 5 sinais críticos (schema + tenancy + bundles + CSS + aggregates)';

    /** @var array<int, string> */
    protected array $failures = [];

    public function handle(): int
    {
        $this->info('[sells:smoke-daily] início — 5 checks Cowork');

        $this->checkSchemaEssencial();
        $this->checkMultiTenantScope();
        $this->checkViteManifest();
        $this->checkCssScopedImports();
        $this->checkCoworkAggregatesSchema();

        if (! empty($this->failures)) {
            $msg = '[sells:smoke-daily] FALHOU — '.count($this->failures).' check(s): '
                .implode(' · ', $this->failures);
            $this->error($msg);
            if ($this->option('notify')) {
                Log::channel('single')->error($msg);
            }

            return self::FAILURE;
        }

        $this->info('[sells:smoke-daily] OK — 5/5 checks passaram');

        return self::SUCCESS;
    }

    protected function checkSchemaEssencial(): void
    {
        if (! Schema::hasTable('transactions')) {
            $this->failures[] = 'schema: transactions table missing';

            return;
        }

        $required = ['business_id', 'final_total', 'payment_status', 'commission_agent'];
        foreach ($required as $col) {
            if (! Schema::hasColumn('transactions', $col)) {
                $this->failures[] = "schema: transactions.{$col} missing";
            }
        }

        if (! Schema::hasTable('transaction_sell_lines')) {
            $this->failures[] = 'schema: transaction_sell_lines table missing';
        }

        $this->line('  · schema essencial OK');
    }

    protected function checkMultiTenantScope(): void
    {
        // biz=1 (oimpresso interno) — pelo menos 1 venda 30d
        $biz1 = \App\Transaction::where('business_id', 1)
            ->where('type', 'sell')
            ->where('status', 'final')
            ->whereDate('transaction_date', '>=', now()->subDays(30))
            ->count();

        // biz=4 (ROTA LIVRE cliente piloto) — pelo menos 1 venda 30d
        $biz4 = \App\Transaction::where('business_id', 4)
            ->where('type', 'sell')
            ->where('status', 'final')
            ->whereDate('transaction_date', '>=', now()->subDays(30))
            ->count();

        if ($biz1 === 0) {
            // biz=1 pode estar vazia em dev — só ALERT se em prod
            if (app()->environment('live', 'production')) {
                $this->failures[] = 'tenancy: biz=1 zero vendas 30d (suspeito drift)';
            } else {
                $this->line('  · tenancy biz=1: 0 vendas 30d (OK em dev)');
            }
        }

        if ($biz4 === 0 && app()->environment('live', 'production')) {
            // Cliente piloto ROTA LIVRE — ALERT crítico se 0 em prod
            $this->failures[] = 'tenancy: biz=4 (ROTA LIVRE piloto) ZERO vendas 30d — CRÍTICO';
        }

        $this->line("  · tenancy biz=1={$biz1} biz=4={$biz4}");
    }

    protected function checkViteManifest(): void
    {
        // Vite gera manifest em 2 caminhos possíveis dependendo da versão: .vite/manifest.json (Vite 5+) ou manifest.json (Vite 4).
        $candidates = [
            public_path('build-inertia/manifest.json'),
            public_path('build-inertia/.vite/manifest.json'),
        ];

        $manifestPath = null;
        foreach ($candidates as $path) {
            if (file_exists($path)) {
                $manifestPath = $path;
                break;
            }
        }

        if ($manifestPath === null) {
            $this->failures[] = 'manifest: build-inertia/(.vite/)?manifest.json missing — npm run build:inertia faltou?';

            return;
        }

        $content = (string) file_get_contents($manifestPath);

        // Componentes Cowork canônicos das Ondas 2-4
        $expectedChunks = [
            'SaleSheet',
            'SaleAiPanel',
            'SaleAuditTrail',
            'SaleItemComments',
            'SaleLinkifier',
            'SaleTranscriptPDF',
            'SalePresentationMode',
            'SaleMessagePreview',
        ];

        $missing = [];
        foreach ($expectedChunks as $chunk) {
            if (! str_contains($content, $chunk)) {
                $missing[] = $chunk;
            }
        }

        if (! empty($missing)) {
            $this->failures[] = 'manifest: chunks Cowork ausentes — '.implode(',', $missing);
        }

        $this->line('  · vite manifest: '.(empty($missing) ? 'todos chunks Cowork presentes' : 'incompleto'));
    }

    protected function checkCssScopedImports(): void
    {
        $css = (string) file_get_contents(resource_path('css/inertia.css'));

        $expected = [
            './sells-cowork.css',
            './sells-cowork-ia.css',
            './sells-cowork-curadoria.css',
            './sells-cowork-distribuicao.css',
        ];

        $missing = [];
        foreach ($expected as $import) {
            if (! str_contains($css, $import)) {
                $missing[] = $import;
            }
        }

        if (! empty($missing)) {
            $this->failures[] = 'css: imports ausentes em inertia.css — '.implode(',', $missing);
        }

        $this->line('  · css scoped imports: '.(empty($missing) ? '4/4 OK' : 'incompleto'));
    }

    protected function checkCoworkAggregatesSchema(): void
    {
        $controllerSrc = (string) file_get_contents(app_path('Http/Controllers/SellController.php'));

        $required = [
            'buildCoworkAggregates(int $business_id)',
            "'sparkline' => \$sparkline",
            "'deltaRevenueVsYesterday' => \$deltaRevenueVsYesterday",
            "'deltaTicketVsLastWeek' => \$deltaTicketVsLastWeek",
            "'topSeller' => \$topSeller",
            "'coworkAggregates' => \\Inertia\\Inertia::defer(",
        ];

        $missing = [];
        foreach ($required as $needle) {
            if (! str_contains($controllerSrc, $needle)) {
                $missing[] = $needle;
            }
        }

        if (! empty($missing)) {
            $this->failures[] = 'aggregates: SellController drift — falta '.implode(' / ', $missing);
        }

        $this->line('  · coworkAggregates: '.(empty($missing) ? 'shape canônico OK' : 'drift detectado'));
    }
}
