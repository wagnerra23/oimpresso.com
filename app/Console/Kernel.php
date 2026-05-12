<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $env = config('app.env');
        $email = config('mail.username');

        if ($env === 'live') {
            //Scheduling backup, specify the time when the backup will get cleaned & time when it will run.
            
            $schedule->command('backup:clean')->daily()->at('01:00');
            $schedule->command('backup:run')->daily()->at('01:30');


            //Schedule to create recurring invoices
            $schedule->command('pos:generateSubscriptionInvoices')->dailyAt('23:30');
            $schedule->command('pos:updateRewardPoints')->dailyAt('23:45');

            $schedule->command('pos:autoSendPaymentReminder')->dailyAt('8:00');

        }

        // SRS — sincroniza memória Claude pra dentro do repo todo dia 23:00.
        // Histórico de renames: docvault:sync-memories → memcofre:sync-memories
        // (2026-04-24, DocVault → MemCofre) → Modules/MemCofre → Modules/SRS
        // (2026-05-06, Fase 3.7 PR-2). Signature do command mantida `memcofre:*`
        // por backwards compat (ADR 0088 — rename PHP-only, fachada legacy).
        // Scheduler ficou apontando pro nome antigo `docvault:*` até 2026-05-04,
        // causando 40+ arquivos atrasados (drift descoberto ao auditar 2 fontes
        // de verdade auto-mem ↔ git).
        $schedule->command('memcofre:sync-memories')
            ->dailyAt('23:00')
            ->withoutOverlapping()
            ->environments(['local', 'live']);

        // MEM-MET-3 (ADRs 0050+0051) — apura 8 métricas obrigatórias + 3 RAGAS
        // por business + plataforma, gravando 1 linha/dia em
        // copiloto_memoria_metricas via upsert idempotente.
        // Roda 23:55 pra fechar o dia (após scout:import e antes da rotação de log).
        $schedule->command('copiloto:metrics:apurar --business=all')
            ->dailyAt('23:55')
            ->withoutOverlapping()
            ->environments(['local', 'live'])
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::channel('copiloto-ai')->error(
                    'Schedule MEM-MET-3 (copiloto:metrics:apurar --business=all) FALHOU'
                );
            });

        // MemoriaAutonoma Fase 1 — auto-sintese semanal (sex 18:00).
        // Le commits + arquivos memory/ + diffs CURRENT/TASKS/TEAM da semana
        // anterior, chama Haiku 4.5, salva memory/sessions/SEMANA-YYYY-Www-resumo.md.
        // Custo ~R$ 0.10/execucao. Ver ADR MemoriaAutonoma/0001.
        $schedule->command('copiloto:sintese-semanal')
            ->fridays()
            ->at('18:00')
            ->withoutOverlapping()
            ->environments(['live'])
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::channel('copiloto-ai')->error(
                    'Schedule MemoriaAutonoma F1 (copiloto:sintese-semanal) FALHOU'
                );
            });

        // Health-check Jana + Constituição v2 — 5 checks SQL diários.
        // Multi-tenant Tier 0 + Brief uptime + Custo Brain B + PII leak + Profile drift.
        // 06:00 BRT (após brief regenerar a primeira vez do dia).
        $schedule->command('jana:health-check --notify')
            ->dailyAt('06:00')
            ->withoutOverlapping()
            ->environments(['live'])
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::channel('single')->error(
                    'Schedule jana:health-check FALHOU — investigar ' .
                    'storage/logs/laravel.log pra ALERT entries'
                );
            });

        // ADR 0133 — System audit (5 dimensões: observability/evals/ADR-stale/cost-agg/test-coverage).
        // Princípio 2 (tiered cost): SQL+FS only, ZERO LLM. 06:15 BRT (15min após health-check
        // pra evitar disputa DB). Tool MCP system-health-audit consulta o mesmo output.
        $schedule->command('jana:system-audit --notify')
            ->dailyAt('06:15')
            ->withoutOverlapping()
            ->environments(['live'])
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::channel('single')->error(
                    'Schedule jana:system-audit FALHOU — investigar ' .
                    'storage/logs/laravel.log pra ALERT entries (ADR 0133)'
                );
            });

        // US-COPI-100 — Brain A narrador horário do Cockpit Saúde do Ecossistema.
        // Lê snapshot via HealthSnapshotService + invoca HealthNarratorService
        // (gpt-4o-mini canônico ADR 0035) → persiste em jana_health_narratives.
        // Severity=critical escala via Log::single ALERT (mesmo padrão health-check).
        // Custo: ~R$ 0.30/dia (24 chamadas × R$ 0.013) — protegido por
        // `jana:health-check` check custo_brain_b_24h alvo ≤ R$ 5/dia.
        // Roda hourlyAt(30) pra evitar conflito com brief:generate (00 cada 3h)
        // e mcp:sync-memory (every 5min — múltiplos de 0).
        $schedule->job(new \Modules\Jana\Jobs\NarrarSaudeEcosistemaJob)
            ->hourlyAt(30)
            ->withoutOverlapping()
            ->environments(['live'])
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::channel('copiloto-ai')->error(
                    'Schedule NarrarSaudeEcosistemaJob FALHOU — narrativa horária pulada'
                );
            });

        // S6 F2 charter:health — drift detector daily de Page Charters.
        // Roda 06:30 BRT (após jana:health-check). Métrica M6 (anti-hallucination
        // ratchet) lê daqui pro dashboard /copiloto/admin/qualidade em F4.
        // Spec em memory/sprints/s6-charter-capterra/03-charter-pest-runner.md.
        $schedule->command('charter:health --notify')
            ->dailyAt('06:30')
            ->withoutOverlapping()
            ->environments(['live'])
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::channel('single')->error(
                    'Schedule charter:health FALHOU — investigar drift de Page Charters'
                );
            });

        // Sprint 2 ADR 0123 — arquivos:health-check diário 06:30 BRT.
        // 5 sinais compliance LGPD + integridade DMS: orphan_files, dedupe_inconsistent,
        // audit_log_lag, retention_overdue, vault_encryption_ratio.
        // Defasagem de 30min do jana:health-check (06:00) pra evitar disputa de DB.
        // --alert ativa exit code 2=FAIL / 1=WARN pra integração com monitoring.
        $schedule->command('arquivos:health-check --alert')
            ->dailyAt('06:30')
            ->timezone('America/Sao_Paulo')
            ->name('arquivos-health-check-daily')
            ->withoutOverlapping()
            ->environments(['live'])
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::channel('single')->error(
                    'Schedule arquivos:health-check FALHOU — investigar storage/logs/laravel.log'
                );
            });

        // US-RB-046 — sync extrato bancário Inter D-7 (Banking API v2).
        // Roda 07:00 BRT pra ter dia anterior fechado. Idempotente via UNIQUE
        // (conta_bancaria_id, idempotency_key) em fin_extrato_lancamentos.
        $schedule->job(new \Modules\RecurringBilling\Jobs\SyncBankStatementsJob())
            ->dailyAt('07:00')
            ->withoutOverlapping()
            ->environments(['live']);

        // ADS Reviewer (T11 G-Eval) — review automático cada 15min de decisions sem score.
        $schedule->command('ads:review-decisions --limit=10')
            ->everyFifteenMinutes()
            ->withoutOverlapping()
            ->environments(['live'])
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::channel('single')->error(
                    'Schedule ADS Reviewer (ads:review-decisions) FALHOU'
                );
            });

        // ADS Pattern Learning (T15 Wilson Score) — diário 02:00.
        $schedule->command('ads:learn-patterns --business=all --detect-drift')
            ->dailyAt('02:00')
            ->withoutOverlapping()
            ->environments(['live']);

        // ADS Auto Task Generator (T7 Self-Instruct) — horário de 9h às 18h.
        $schedule->command('ads:auto-generate-tasks')
            ->cron('0 9-18 * * 1-5')
            ->withoutOverlapping()
            ->environments(['live']);

        // ADS Planner (T9 PlannerAgent) — decompõe decisions complexas a cada 10min.
        $schedule->command('ads:plan-decisions --limit=3')
            ->everyTenMinutes()
            ->withoutOverlapping()
            ->environments(['live']);

        // ADS Brain B — processa decisions com destination=brain_b a cada 5 min.
        // Custo estimado ~$0.05/dia em prod com prompt caching Sonnet. Limit=5
        // por execução evita gastos descontrolados; ajustar via Policy se necessário.
        $schedule->command('ads:process-brain-b --limit=5')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->environments(['live'])
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::channel('single')->error(
                    'Schedule ADS Brain B (ads:process-brain-b) FALHOU'
                );
            });

        // MEM-FASE8 — esquecimento semanal (domingo 03:00).
        // Remove bloat (hits=0, >30d) + expirados (valid_until >90d) + órfãos MCP.
        // Soft-delete por padrão. Hard-delete LGPD só via comando manual com --hard.
        $schedule->command('copiloto:cleanup-memoria')
            ->weeklyOn(0, '03:00')
            ->withoutOverlapping()
            ->environments(['live'])
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::channel('copiloto-ai')->error(
                    'Schedule MEM-FASE8 (copiloto:cleanup-memoria) FALHOU'
                );
            });

        // MCP sync-memory — rede de proteção pra webhook GitHub.
        // Webhook (SyncMemoryWebhookController) já roda pull-safe + sync a cada
        // push em main; este cron cobre falhas (timeout/network) e drift de
        // filesystem-vs-DB (admin fez git pull manual e esqueceu o sync).
        // Idempotente: só re-indexa quando sha de arquivo muda.
        $schedule->command('mcp:sync-memory --reason=cron')
            ->everyFiveMinutes()
            ->withoutOverlapping(10)
            ->environments(['live'])
            ->appendOutputTo(storage_path('logs/mcp-cron.log'))
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::channel('copiloto-ai')->error(
                    'Schedule mcp:sync-memory FALHOU'
                );
            });

        // Sprint 1 — Daily Brief (ADR 0091, camada L7 da Constituição V2).
        // Gera o brief 6x/dia em horário comercial PT-BR (07/11/14/17/20/23h).
        // Custo médio: $0.05/run × 6 = $0.30/dia. Cap diário no command.
        $schedule->command('brief:generate')
            ->cron('0 7,11,14,17,20,23 * * *')
            ->timezone('America/Sao_Paulo')
            ->withoutOverlapping(10)
            ->environments(['live'])
            ->appendOutputTo(storage_path('logs/brief-cron.log'))
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::channel('copiloto-ai')->error(
                    'Schedule brief:generate FALHOU — mantém snapshot anterior'
                );
            });

        // US-WA-076 (ADR 0142 §5) — ProcessRemindersJob hourly.
        // Varre `whatsapp_reminders` com status='pending' + due_at<=now() +
        // notified_at IS NULL, publica Centrifugo em `user:{atendente_id}` e
        // marca notified_at. Cross-tenant (job genérico, sem session) —
        // canal Centrifugo per-user impede leak. withoutOverlapping(30) cobre
        // ~2 ciclos consecutivos pra job lento.
        $schedule->job(new \Modules\Whatsapp\Jobs\ProcessRemindersJob())
            ->hourly()
            ->withoutOverlapping(30)
            ->environments(['live'])
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::channel('single')->error(
                    'Schedule ProcessRemindersJob FALHOU — lembretes /lembrete podem atrasar'
                );
            });

        // US-WA-014 (ADR 0096) — Whatsapp driver health check pra detectar
        // ban Z-API/Baileys e ativar fallback automático Meta Cloud.
        // Roda a cada 6h. Pula Meta Cloud (oficial não bane).
        $schedule->command('whatsapp:health-check-all')
            ->cron('0 */6 * * *')
            ->withoutOverlapping()
            ->environments(['local', 'live'])
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::warning(
                    'Schedule whatsapp:health-check-all FALHOU — drivers Z-API/Baileys podem estar sem fallback ativo'
                );
            });

        // Guardião 6 camadas anti-mídia-perdida — Camada 4 (retry hourly).
        // Rede de proteção pra mídia órfã (status=pending|downloading, media_url=null,
        // attempts<5, criada nos últimos 7d). Dispatcha DownloadMediaJob pra cada.
        // Observer Camada 1 já dispara no created; este cron cobre falhas onde:
        //   - Queue connection down quando Observer rodou
        //   - Daemon CT 100 offline temporariamente
        //   - Race condition entre webhook + observer
        // withoutOverlapping(30) evita 2 instâncias batendo no daemon ao mesmo tempo.
        $schedule->job(new \Modules\Whatsapp\Jobs\RetryFailedMediaDownloadsJob())
            ->hourly()
            ->withoutOverlapping(30)
            ->environments(['live'])
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::channel('single')->error(
                    'Schedule RetryFailedMediaDownloadsJob FALHOU — mídia órfã pode acumular'
                );
            });

        // Guardião 6 camadas anti-mídia-perdida — Camada 5 (scan drift daily 03:30 BRT).
        // Não corrige drift, apenas LOGA métricas (pending_count_1h/24h, failed_permanent_7d,
        // total_size_pending_bytes) pra observability. 30min após fsm:scan-drift (03:00)
        // pra evitar disputa DB. Health check Camada 6 (jana:health-check 06:00) alerta
        // se pending_count_1h > 0.
        $schedule->command('whatsapp:scan-media-drift')
            ->dailyAt('03:30')
            ->timezone('America/Sao_Paulo')
            ->name('whatsapp-scan-media-drift-daily')
            ->withoutOverlapping()
            ->environments(['live']);

        // US-NFE-051 (ADR 0116 caso Gold) — Distribuição DFe pra businesses com cert
        // ativo. Puxa NF-e emitidas contra meu CNPJ via NSU SEFAZ ambiente nacional.
        // 06:15 BRT (após jana:health-check 06:00). Cooldown 5min protege se cron
        // disparar duplicado.
        $schedule->command('nfebrasil:dist-dfe-puxar')
            ->dailyAt('06:15')
            ->withoutOverlapping()
            ->environments(['live'])
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::channel('single')->error(
                    'Schedule nfebrasil:dist-dfe-puxar FALHOU — manifestação pode atrasar prazo SEFAZ 180d'
                );
            });

        // US-SELL-032 v2 — `fsm:scan-drift transactions` daily 03:00 BRT.
        // Detecta current_stage_id que bypassou o TransactionFsmObserver via
        // mass-update Eloquent ou DB::table writes (FsmDriftDetector compara
        // current_stage_id atual vs último to_stage_id em sale_stage_history).
        // Exit 1 → log error → alerta. ADR 0129 §Drift Detection.
        $schedule->command('fsm:scan-drift transactions')
            ->dailyAt('03:00')
            ->timezone('America/Sao_Paulo')
            ->name('fsm-scan-drift-transactions')
            ->withoutOverlapping()
            ->environments(['live'])
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::channel('single')->error(
                    'Schedule fsm:scan-drift transactions FALHOU ou DETECTOU drift — ' .
                    'investigar storage/logs/laravel.log entries "FsmDriftDetector: drift detected"'
                );
            });

        // US-RB-045 — Sincroniza saldo Asaas/Inter pra contas_bancarias.saldo_cached.
        // Sem este schedule, dashboard /financeiro mostra "—" (saldo_cached NULL).
        // Hourly: latência aceitável vs custo de chamadas API gateways.
        $schedule->command('rb:sync-bank-balances')
            ->hourly()
            ->withoutOverlapping()
            ->environments(['live'])
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::channel('single')->error(
                    'Schedule rb:sync-bank-balances FALHOU — saldo Asaas/Inter ficará stale no dashboard /financeiro'
                );
            });

        if ($env === 'demo') {
            //IMPORTANT NOTE: This command will delete all business details and create dummy business, run only in demo server.
            $schedule->command('pos:dummyBusiness')
                    ->cron('0 */3 * * *')
                    //->everyThirtyMinutes()
                    ->emailOutputTo($email);
        }
    }

    /**
     * Register the Closure based commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}
