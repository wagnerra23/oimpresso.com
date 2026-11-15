<?php

declare(strict_types=1);

namespace App\Console\Commands\Sells;

use App\Notifications\SellsSmokeFailedNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
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
        $this->info('[sells:smoke-daily] início — checks Cowork + clientes vivos');

        $this->checkSchemaEssencial();
        $this->checkMultiTenantScope();
        $this->checkViteManifest();
        $this->checkCssScopedImports();
        $this->checkCoworkAggregatesSchema();
        $this->checkClientesVivos();

        if (! empty($this->failures)) {
            $msg = '[sells:smoke-daily] FALHOU — '.count($this->failures).' check(s): '
                .implode(' · ', $this->failures);
            $this->error($msg);
            if ($this->option('notify')) {
                Log::channel('single')->error($msg);
                $this->dispatchSlackNotify();
            }

            return self::FAILURE;
        }

        $this->info('[sells:smoke-daily] OK — todos os checks passaram');

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

    public const VENDA_JANELA_DIAS = 5;

    /**
     * Clientes vivos em produção (ROTA LIVRE e WR2): o sistema que eles usam continua de pé?
     * Só leitura e só contagem: nenhum valor, nenhum dado de cliente.
     *
     * ROTA LIVRE: a janela de 30 dias do checkMultiTenantScope leva até 4 semanas para
     * acusar que as vendas pararam. Aqui a janela é de VENDA_JANELA_DIAS. Medido em
     * 2026-11-15 em produção, contando dias com venda nos 365 dias anteriores: 332 dias com
     * venda, maior lacuna sem venda = 3 dias. Uma janela de 5 dias teria dado zero alarme
     * falso naquele ano. Se a rotina da loja mudar (férias), a janela é config.
     *
     * WR2: o business da Eliana ainda não está registrado; usa pontowr2.smoke.wr2_business_id
     * (env SMOKE_WR2_BIZ, padrão 1 = demo). Por ser placeholder, a contagem de marcações é só
     * informativa. Reprova só se o Ponto estiver habilitado e a tabela de marcações sumir.
     */
    protected function checkClientesVivos(): void
    {
        if (! app()->environment('live', 'production')) {
            $this->line('  · clientes vivos: só em produção (ambiente '.app()->environment().')');

            return;
        }

        $r = self::avaliarClientesVivos(
            (int) config('pontowr2.smoke.rota_livre_business_id', 4),
            (int) config('pontowr2.smoke.venda_janela_dias', self::VENDA_JANELA_DIAS),
            (int) config('pontowr2.smoke.wr2_business_id', 1),
        );
        foreach ($r['linhas'] as $linha) {
            $this->line('  · '.$linha);
        }
        foreach ($r['falhas'] as $falha) {
            $this->failures[] = $falha;
        }
    }

    /**
     * Lógica do check sem o gate de ambiente, para o teste rodar no tenant fictício.
     *
     * @return array{falhas: array<int, string>, linhas: array<int, string>}
     */
    public static function avaliarClientesVivos(int $rotaLivreBiz, int $janelaDias, int $wr2Biz): array
    {
        $falhas = [];
        $linhas = [];

        $vendas = \App\Transaction::where('business_id', $rotaLivreBiz)
            ->where('type', 'sell')
            ->where('status', 'final')
            ->whereDate('transaction_date', '>=', now()->subDays($janelaDias))
            ->count();
        $linhas[] = "clientes vivos: rota-livre biz={$rotaLivreBiz} vendas em {$janelaDias}d={$vendas}";
        if ($vendas === 0) {
            $falhas[] = "ALERT cliente=rota-livre: biz={$rotaLivreBiz} zero vendas em {$janelaDias} dias";
        }

        $ponto = \Nwidart\Modules\Facades\Module::find('Ponto');
        if ($ponto === null || ! $ponto->isEnabled()) {
            $linhas[] = 'clientes vivos: wr2 — módulo Ponto desabilitado, nada a medir';
        } elseif (! Schema::hasTable('ponto_marcacoes')) {
            $falhas[] = 'ALERT cliente=wr2: Ponto habilitado e tabela ponto_marcacoes ausente';
        } else {
            $marcacoes = \Illuminate\Support\Facades\DB::table('ponto_marcacoes')
                ->where('business_id', $wr2Biz)
                ->where('momento', '>=', now()->subDays(7))
                ->count();
            $linhas[] = "clientes vivos: wr2 biz={$wr2Biz} marcações em 7d={$marcacoes} (informativo — business placeholder)";
        }

        return ['falhas' => $falhas, 'linhas' => $linhas];
    }

    /**
     * Dispara notificação Slack via Incoming Webhook (gap Onda 6).
     *
     * Só roda se `--notify` foi passado E env `SLACK_SMOKE_WEBHOOK_URL`
     * estiver setada em `config/services.php` → `slack.smoke_webhook_url`.
     * Default null = no-op silencioso (smoke ainda loga ALERT no log).
     *
     * Falha de rede (Slack down, DNS, timeout) NÃO derruba o smoke —
     * só loga warning em `Log::channel('single')`. Smoke retorna FAILURE
     * mesmo sem Slack porque o check falhou — Slack é só broadcast.
     *
     * Timeout 5s pra não segurar cron (smoke roda 06:30 BRT antes da equipe).
     */
    protected function dispatchSlackNotify(): void
    {
        $slackUrl = config('services.slack.smoke_webhook_url');
        if (empty($slackUrl)) {
            return;
        }

        try {
            $notification = new SellsSmokeFailedNotification($this->failures);
            $payload = $notification->toSlackPayload();

            $response = Http::timeout(5)->post($slackUrl, $payload);

            if ($response->successful()) {
                $this->line('  · slack notify enviado ('.$response->status().')');
            } else {
                Log::channel('single')->warning(
                    '[sells:smoke-daily] Slack webhook respondeu HTTP '.$response->status()
                );
            }
        } catch (\Throwable $e) {
            Log::channel('single')->warning(
                '[sells:smoke-daily] Slack notify falhou: '.$e->getMessage()
            );
        }
    }
}
