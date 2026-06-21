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

        // Erros (Fase 2 · E-2) — janela de decaimento: arquiva grupos de erro abertos
        // sem ocorrência há N dias (config errors.group_decay_days). Plataforma, não
        // business-scoped. @see prototipo-ui/handoffs/erros-dedup.md
        $schedule->command('errors:archive-stale-groups')
            ->dailyAt('04:00')
            ->withoutOverlapping();

        if ($env === 'live') {
            //Scheduling backup, specify the time when the backup will get cleaned & time when it will run.
            
            $schedule->command('backup:clean')->daily()->at('01:00');
            $schedule->command('backup:run')->daily()->at('01:30');


            //Schedule to create recurring invoices
            $schedule->command('pos:generateSubscriptionInvoices')->dailyAt('23:30');
            $schedule->command('pos:updateRewardPoints')->dailyAt('23:45');

            $schedule->command('pos:autoSendPaymentReminder')->dailyAt('8:00');

        }

        // PaymentGateway — polling de reconciliação PIX Inter (fallback do webhook).
        // O Inter não empurra confirmação sozinho; este cron PERGUNTA ao Inter
        // quais cobranças PIX emitidas já foram pagas e reconcilia (marca paga +
        // quita título + dispara CobrancaPaga). Roda em local+live pra permitir
        // teste no sandbox. withoutOverlapping evita sobreposição se um tick demorar.
        $schedule->command('paymentgateway:inter-reconcile-pix')
            ->everyTenMinutes()
            ->withoutOverlapping()
            ->environments(['local', 'live'])
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::channel('single')->error(
                    'Schedule paymentgateway:inter-reconcile-pix FALHOU — cobranças PIX podem ficar não-reconciliadas'
                );
            });

        // PaymentGateway — import diário de recebimentos Inter pro Financeiro
        // (US-PG-008). WR2 (biz=1) emite boletos no legado mas recebe no Inter;
        // este cron puxa os boletos pagos no Inter (GET /cobranca/v3/cobrancas
        // RECEBIDO) e cria título recebido + baixa na conta Inter (id=12).
        // Idempotente (dedup por metadata->inter_ref) — janela 15d cobre runs
        // perdidos sem duplicar. 07h BRT (antes do dia operacional).
        $schedule->command('paymentgateway:inter-importar-recebimentos --business=1 --conta=12 --days=15')
            ->dailyAt('07:00')
            ->timezone('America/Sao_Paulo')
            ->withoutOverlapping()
            ->environments(['live'])
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::channel('single')->error(
                    'Schedule paymentgateway:inter-importar-recebimentos FALHOU — recebimentos Inter podem não entrar no Financeiro'
                );
            });

        // SRS — sincroniza memória Claude pra dentro do repo todo dia 23:00.
        // Histórico de renames: docvault:sync-memories → memcofre:sync-memories
        // (2026-04-24, DocVault → MemCofre) → Modules/MemCofre → Modules/SRS
        // (2026-05-06, Fase 3.7 PR-2). Signature do command mantida `memcofre:*`
        // por backwards compat (ADR 0088 — rename PHP-only, fachada legacy).
        // Scheduler ficou apontando pro nome antigo `docvault:*` até 2026-05-04,
        // causando 40+ arquivos atrasados (drift descoberto ao auditar 2 fontes
        // de verdade auto-mem ↔ git).
        // ⛔ DESATIVADO 2026-06-07 (auditoria de conflitos de memória).
        // Este sync copiava auto-mem local (~/.claude/.../memory/) → git memory/claude/:
        // foi o MECANISMO que vazou credenciais em claro pro git e ressuscitava o
        // legado a cada noite. Viola ADR 0061 (zero auto-mem privada — o próprio
        // comando cita o ADR mas rodava mesmo assim). O command memcofre:sync-memories
        // continua existindo (Modules/SRS) e pode ser rodado manual se algum dia
        // precisar — mas NÃO volta ao scheduler sem ADR que reverta o 0061.
        // $schedule->command('memcofre:sync-memories')
        //     ->dailyAt('23:00')
        //     ->withoutOverlapping()
        //     ->environments(['local', 'live']);

        // H5 Onda 3 (Gap #5 COMPARATIVO-MCP-2026-05-13) — auto-fechamento cycles
        // expirados (Linear-style desde 2019). Daily 23:55 BRT detecta cycles com
        // end_date < today + status != closed, chama cycles-close --rollover auto.
        // Se não tem próximo cycle, cria CYCLE-N+1 (auto-created) em status=planning.
        $schedule->command('jana:cycles:auto-close-expired')
            ->dailyAt('23:55')
            ->timezone('America/Sao_Paulo')
            ->withoutOverlapping()
            ->environments(['live'])
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::channel('single')->error(
                    'Schedule jana:cycles:auto-close-expired FALHOU — cycles expirados ficarão abertos'
                );
            });

        // PR-4 Loop de Handoff Zero-Paste (Fase 0 · ADR 0283) — anti feedback-void:
        // handoffs de design pendentes > 3d alertam o inbox ops (Wagner) pra
        // reauditar/aplicar ou rejeitar. Daily 08:30 BRT, idempotente (1 digest/dia).
        $schedule->command('handoff:stale-alert')
            ->dailyAt('08:30')
            ->timezone('America/Sao_Paulo')
            ->withoutOverlapping()
            ->environments(['live'])
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::channel('single')->error(
                    'Schedule handoff:stale-alert FALHOU — handoffs pendentes velhos não alertados'
                );
            });

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

        // H6 Onda 3 (G8 P2 AUDITORIA-KNOWLEDGE-2026-05-13) — Reflect-style weekly
        // digest. Coleta commits + PRs gh + US mcp_tasks + ADRs novas + handoffs +
        // cycle goals delta + audit log, chama gpt-4o-mini ~R$ [redacted Tier 0] → 5 seções
        // (marco/trabalho/cycle/decisões/próxima semana). Segunda 09h BRT pra
        // estar pronto antes do dia começar. Coexiste com `copiloto:sintese-semanal`
        // (sex 18h Haiku) — funções complementares.
        $schedule->command('jana:weekly-digest')
            ->mondays()
            ->at('09:00')
            ->timezone('America/Sao_Paulo')
            ->withoutOverlapping()
            ->environments(['live'])
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::channel('copiloto-ai')->error(
                    'Schedule jana:weekly-digest FALHOU — Reflect-style weekly não gerado'
                );
            });

        // MemoriaAutonoma Fase 1 — auto-sintese semanal (sex 18:00).
        // Le commits + arquivos memory/ + diffs CURRENT/TASKS/TEAM da semana
        // anterior, chama Haiku 4.5, salva memory/sessions/SEMANA-YYYY-Www-resumo.md.
        // Custo ~R$ [redacted Tier 0]/execucao. Ver ADR MemoriaAutonoma/0001.
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

        // Parte B do T7 (auditoria IA OS) — loop telemetria→tier de skills (ADR 0095).
        // Trimestral: emite relatório APPEND-ONLY em memory/governance/skill-tier-review-AAAA-QN.md
        // com sugestões de promoção/rebaixamento. NÃO auto-aplica (sem --apply-suggestions):
        // B→A exige ADR, C→arquivar exige ADR HISTORICAL. quarterlyOn(1, '06:40') = 1º dia de
        // cada trimestre, ancorado no eixo 06:00-06:50 BRT (após jana:health-check 06:00).
        // Cross-tenant intencional (mcp_skill_telemetry é governança, sem business_id — ADR 0093).
        // Hostinger ≠ CT 100 (ADR 0062): só artisan + schedule, sem daemon.
        $schedule->command('skills:tier-review')
            ->quarterlyOn(1, '06:40')
            ->timezone('America/Sao_Paulo')
            ->withoutOverlapping()
            ->environments(['live'])
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::channel('single')->error(
                    'Schedule skills:tier-review FALHOU — investigar storage/logs/laravel.log'
                );
            });

        // Distiller-módulo-verdade ([ADR 0291] · keystone SDD×memória, peça 2).
        // Reescreve as portas BRIEFING.md a partir dos eventos recentes (diário→manual).
        // COMENTADO DE PROPÓSITO: D-E exige gate Wagner/CT100 (smoke skim 10min/lote) —
        // a destilação chama LLM e MUTA memória canônica PÚBLICA; não pode auto-rodar sem
        // supervisão. O comando existe e roda manual (`php artisan jana:distill-module-truth
        // --all [--dry-run]`); o Wagner DESCOMENTA aqui quando o processo de skim estiver de pé.
        // $schedule->command('jana:distill-module-truth --all')
        //     ->dailyAt('05:30')
        //     ->withoutOverlapping()
        //     ->environments(['live'])
        //     ->onFailure(function () {
        //         \Illuminate\Support\Facades\Log::channel('single')->error(
        //             'Schedule jana:distill-module-truth FALHOU — portas BRIEFING podem envelhecer (ADR 0291 D-5)'
        //         );
        //     });

        // Sentinela de FLUXO de inbound WhatsApp — cadência HORÁRIA em horário
        // comercial BRT (incidente 2026-06-16 #2726: recebimento morto 3 dias sem
        // ninguém ver; o cron diário 06:00 só detectaria ~22h depois). Reusa o
        // mesmo --notify/ALERT; o check whatsapp_inbound_flow só acende em horário
        // comercial e ignora canal sem histórico de inbound (baseline por canal).
        $schedule->command('jana:health-check --notify')
            ->hourlyAt(7)
            ->timezone('America/Sao_Paulo')
            ->between('8:00', '20:00')
            ->withoutOverlapping()
            ->environments(['live'])
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::channel('single')->error(
                    'Schedule jana:health-check (horário · sentinela inbound) FALHOU'
                );
            });

        // Canário do webhook WhatsApp — Fase 1 perda-zero (proposta
        // whatsapp-ingestao-perda-zero.md, camadas 1+5). Posta um Presence
        // sintético na URL pública com ?wh= e confere 200; não-200 → ALERT + exit≠0.
        // Cadência 5min em horário comercial BRT: detecta a classe do incidente
        // #2726 (webhook recusando, 3 dias mudo) em <5min — dentro da janela de
        // retry do daemon (~10-15min), logo ANTES de qualquer mensagem ser perdida.
        // Só OBSERVA a via (Presence ACKa 200 sem criar mensagem) — baixo risco.
        $schedule->command('whatsapp:webhook-canary')
            ->everyFiveMinutes()
            ->timezone('America/Sao_Paulo')
            ->between('8:00', '20:00')
            ->withoutOverlapping()
            ->environments(['live'])
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::channel('single')->error(
                    'Schedule whatsapp:webhook-canary FALHOU — recebimento WhatsApp pode estar parado (classe #2726)'
                );
            });

        // US-SELL-COWORK-R6-SMOKE — smoke automatizado Sells/Index Cowork.
        // 5 sinais críticos: schema essencial + multi-tenant biz=1/biz=4 com vendas 30d
        // + Vite manifest contém chunks Cowork (Sale*.tsx) + CSS scoped imports
        // + SellController::buildCoworkAggregates shape canônico.
        // 06:30 BRT — após brief (06:00) + grade-snapshot (06:05), antes de cliente abrir.
        // ROTA LIVRE (biz=4 piloto) zero vendas 30d = ALERT CRÍTICO em prod.
        $schedule->command('sells:smoke-daily --notify')
            ->dailyAt('06:30')
            ->timezone('America/Sao_Paulo')
            ->withoutOverlapping()
            ->environments(['live'])
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::channel('single')->error(
                    'Schedule sells:smoke-daily FALHOU — investigar Cowork drift'
                );
            });

        // Ciclo diário de governança [CC]×Jana (ADVISORY — orquestra checks que já
        // existem; não cria daemon, não numera ADR, não auto-mergeia Tier 0).
        // Regenera o estado (governanca:scorecard) + frescor (graduation_ratio,
        // charter coverage, review/protocol-freshness) + gradua o inbox [W]
        // (COWORK_NOTES) + emite 1 digest/dia em storage/reports/governanca-digest.md.
        // 06:50 BRT — DEPOIS de health-check (06:00) + grade-snapshot (06:05) +
        // system-audit (06:15) + smoke (06:30), sobre o estado já fresco do dia.
        // SEM --notify e SEM onFailure ALERT: drift de processo não pagina à noite.
        // SEM --code-notes no cron: o append a CODE_NOTES.md (versionado) é manual
        // pro marco; o cron grava só o storage/digest (não suja arquivo git diário).
        $schedule->command('governanca:ciclo-diario')
            ->dailyAt('06:50')
            ->timezone('America/Sao_Paulo')
            ->withoutOverlapping()
            ->environments(['live']);

        // ADR 0153/0155 — Module Grades snapshot diário pra sparkline 7d.
        // Persiste 1 row/módulo em mcp_module_grades_history (~34 módulos × 1KB).
        // Pareado com jana:health-check (06:00) — ambos rodam após brief regenerar.
        // 06:05 BRT pra evitar disputa DB com health-check. Cross-tenant intencional
        // (Governance Art. 6 — observabilidade cross-business — pareado mcp_* tables).
        $schedule->command('module:grade-snapshot')
            ->dailyAt('06:05')
            ->timezone('America/Sao_Paulo')
            ->withoutOverlapping()
            ->environments(['live'])
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::channel('single')->error(
                    'Schedule module:grade-snapshot FALHOU — sparkline 7d defasada'
                );
            });

        // Wave 24 Agent A (2026-05-16) — Scorecard snapshot bucket-scoped + drift detection.
        // Persiste 1 row/módulo/dia em mcp_scorecard_runs + alerta drifts >=5pts em mcp_alertas.
        // 07:00 BRT (55min após module:grade-snapshot) — usa scorecards YAML curated
        // (memory/governance/scorecards/<slug>.yaml + buckets/<bucket>.yaml).
        // Paired enforcement (cap 50%) canônico Wave 24. Cross-tenant intencional.
        $schedule->command('governance:scorecard-snapshot --alert')
            ->dailyAt('07:00')
            ->timezone('America/Sao_Paulo')
            ->onOneServer()
            ->withoutOverlapping()
            ->environments(['live'])
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::channel('single')->error(
                    'Schedule governance:scorecard-snapshot FALHOU — drift detection defasada (Wave 24)'
                );
            });

        // GT-G7 (ADR 0275 §1) — snapshot diário do scorecard SDD em
        // mcp_sdd_scorecard_history (composta v1 + alertas) via agregador node
        // determinístico. 1 row/dia, re-run substitui. 07:10 BRT — 10min após
        // governance:scorecard-snapshot (07:00) pra evitar disputa DB (mesmo
        // precedente do stagger 06:05 do module:grade-snapshot). O brief das
        // 07h pega o snapshot de ontem; o das 11h pega o de hoje (GT-G8).
        $schedule->command('governance:sdd-scorecard-snapshot')
            ->dailyAt('07:10')
            ->timezone('America/Sao_Paulo')
            ->onOneServer()
            ->withoutOverlapping()
            ->environments(['live'])
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::channel('single')->error(
                    'Schedule governance:sdd-scorecard-snapshot FALHOU — histórico SDD defasado (GT-G7)'
                );
            });

        // Wave 28 Agent 1 (2026-05-17) — Initiatives Cortex-style.
        // Sync diário Initiatives ↔ scorecards: abre breach (rule abaixo target),
        // fecha recuperadas (score_after >= target), expira deadlines passadas.
        // 08:00 BRT — 60min após governance:scorecard-snapshot (07:00), pra usar
        // scorecard_runs do dia. Cross-tenant intencional (mcp_governance_initiatives
        // é repo-wide). Alertas expired persistidos em mcp_alertas business_id=1 superadmin.
        $schedule->command('governance:initiative-sync')
            ->dailyAt('08:00')
            ->timezone('America/Sao_Paulo')
            ->onOneServer()
            ->withoutOverlapping()
            ->environments(['live'])
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::channel('single')->error(
                    'Schedule governance:initiative-sync FALHOU — initiatives auto-loop defasado (Wave 28)'
                );
            });

        // Wave 26 Agent 3 (2026-05-17, ADR 0162) — Rollup diário OTel spans.
        // Pega spans crus em mcp_observability_spans, computa p50/p95/p99 + error rate
        // por par (module, span_name) e popula mcp_observability_aggregates_daily.
        // 02:00 BRT — janela conservadora ANTES de jana:health-check (06:00) e
        // module:grade-snapshot (06:05) que consomem o aggregate via D9.b.
        $schedule->command('observability:aggregate-daily')
            ->dailyAt('02:00')
            ->timezone('America/Sao_Paulo')
            ->withoutOverlapping()
            ->environments(['live'])
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::channel('single')->error(
                    'Schedule observability:aggregate-daily FALHOU — D9.b governance v4 ficará defasada'
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

        // Wave 23 — jana:drift-sentinel canary SEMANAL da Jana (dom 06:00 BRT).
        // Compara faithfulness atual vs baseline canon; ALERT se >10% das perguntas
        // divergirem. RELIGADO 2026-06-20 (auditoria de sentinelas): o docblock do
        // comando afirmava "Schedule weekly Sun 06:00" mas NÃO existia entry aqui —
        // ghost canary (existe + tem teste de mordida, nunca rodava).
        //
        // Skip-guard honesto (2026-06-20): SEM OPENAI_API_KEY o canary NÃO falha o
        // cron toda semana (era ruído / falso "DRIFT 100%") — sai DORMANT (exit 0,
        // status=dormant), visível como ⊘ no agregador governance-audit.mjs. O
        // onFailure abaixo só dispara em drift REAL acima do threshold. Domingo cedo
        // pra não disputar DB com os health-checks diários (06:00-06:30).
        $schedule->command('jana:drift-sentinel')
            ->weeklyOn(0, '06:00')
            ->timezone('America/Sao_Paulo')
            ->withoutOverlapping()
            ->environments(['live'])
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::channel('copiloto-ai')->error(
                    'Schedule jana:drift-sentinel FALHOU — drift Jana acima do threshold. ' .
                    'Ver storage/logs (canary sem OPENAI_API_KEY sai DORMANT, não falha).'
                );
            });

        // Bug #4 BUGS-MCP-SYNC-2026-05-13 — mcp:tasks:health-check daily 06:20 BRT.
        // Flagga tasks dormentes em mcp_tasks (stale_todo >21d, stale_blocked >30d,
        // stale_doing >7d sem commit, stale_review >5d). Roda SEM --auto-comment
        // (só relatório no log) pra não poluir as US com comentários automáticos
        // diários. Tool MCP `tasks-health` permite Claude/Wagner rodar com
        // auto_comment=true quando quiser cleanup explícito.
        // 06:20 BRT pra evitar disputa DB com health-check (06:00) e system-audit (06:15).
        $schedule->command('mcp:tasks:health-check')
            ->dailyAt('06:20')
            ->timezone('America/Sao_Paulo')
            ->withoutOverlapping()
            ->environments(['live'])
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::channel('single')->error(
                    'Schedule mcp:tasks:health-check FALHOU — investigar ' .
                    'storage/logs/laravel.log pra entries "mcp:tasks:health-check"'
                );
            });

        // US-COPI-100 — Brain A narrador horário do Cockpit Saúde do Ecossistema.
        // Lê snapshot via HealthSnapshotService + invoca HealthNarratorService
        // (gpt-4o-mini canônico ADR 0035) → persiste em jana_health_narratives.
        // Severity=critical escala via Log::single ALERT (mesmo padrão health-check).
        // Custo: ~R$ [redacted Tier 0]/dia (24 chamadas × R$ [redacted Tier 0]) — protegido por
        // `jana:health-check` check custo_brain_b_24h alvo ≤ R$ [redacted Tier 0]/dia.
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

        // ENFORCEMENT.md §2 #5 (Constituição Art. 7) — Drift detection cron.
        // Compara Modules/<X>/SCOPE.md.contains[] × filesystem real de
        // Modules/<X>/Http/Controllers/*. Persiste alertas idempotentes em
        // mcp_alertas_eventos (tipo=module_drift). UI consome via
        // Modules/Governance/Http/Controllers/DriftAlertsController.
        //
        // Por que daily 06:15: defesa cron complementa Mecanismo #3 (pre-commit
        // hook) — pega PRs antigos pré-SCOPE.md, branches paralelas que escaparam
        // do gate, edits SSH direto em prod (violação "mexeu, registra"). Time
        // entra em breve no MCP — drift escala N× pessoas.
        //
        // 06:15 BRT pra evitar disputa DB com jana:health-check (06:00) e ficar
        // antes do horário comercial. Exit 1 quando drift_added > 0 → Log warning
        // → cron alerting (mesmo padrão fsm:scan-drift transactions).
        $schedule->command('governance:detect-drift')
            ->dailyAt('06:15')
            ->timezone('America/Sao_Paulo')
            ->name('governance-detect-drift-daily')
            ->onOneServer()
            ->withoutOverlapping()
            ->environments(['live'])
            ->appendOutputTo(storage_path('logs/governance-drift.log'))
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::channel('single')->error(
                    'Schedule governance:detect-drift FALHOU ou DETECTOU drift — ' .
                    'investigar storage/logs/governance-drift.log e ' .
                    'mcp_alertas_eventos WHERE tipo=module_drift'
                );
            });

        // US-RB-046 — sync extrato bancário Inter D-7 (Banking API v2).
        // Roda 07:00 BRT pra ter dia anterior fechado. Idempotente via UNIQUE
        // (conta_bancaria_id, idempotency_key) em fin_extrato_lancamentos.
        $schedule->job(new \Modules\RecurringBilling\Jobs\SyncBankStatementsJob())
            ->dailyAt('07:00')
            ->withoutOverlapping()
            ->environments(['live']);

        // US-WA-VOZ-001 — Customer Memory refresh daily (2026-05-15).
        // Re-dispatcha RebuildCustomerMemoryJob pra customers com
        // last_rebuilt_at > 24h ou NULL. Idempotente. 02:00 BRT alinhado
        // com horário de baixa atividade (canal Suporte pico 12-18h BRT).
        $schedule->command('customer-memory:refresh-daily')
            ->dailyAt('02:00')
            ->timezone('America/Sao_Paulo')
            ->name('customer-memory-refresh-daily')
            ->withoutOverlapping()
            ->environments(['live'])
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::channel('single')->error(
                    'Schedule customer-memory:refresh-daily FALHOU — stats agregados ficarão stale'
                );
            });

        // US-WA-VOZ-003 — Employee Performance refresh daily (2026-05-15).
        // Re-dispatcha rebuild de scorecards. Offset 30min do customer-memory
        // pra evitar disputa DB (02:30 BRT).
        $schedule->command('employee-performance:refresh-daily')
            ->dailyAt('02:30')
            ->timezone('America/Sao_Paulo')
            ->name('employee-performance-refresh-daily')
            ->withoutOverlapping()
            ->environments(['live'])
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::channel('single')->error(
                    'Schedule employee-performance:refresh-daily FALHOU — scorecards ficarão stale'
                );
            });

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

        // G1 P0 AUDIT-SENIOR-2026-05-25 §6 — D7.d LGPD purge job daily 03:00 BRT.
        // Aplica Modules/Jana/Config/retention.php sobre 7 entidades PII-relevantes
        // (Conversa, Mensagem, Sugestao, CacheSemantico, MemoriaFato, MemoriaMetrica,
        // HealthNarrative). Estratégia default `anonymize` (LGPD-preferred — preserva
        // métricas agregadas, redacta PII via PiiRedactor canônico).
        //
        // Atrás de JANA_RETENTION_ENABLED=true (default false). Wagner aprova flag=true
        // em prod biz=1 só após canary 7d (ADR 0105 sinal qualificado).
        //
        // Multi-tenant Tier 0 (ADR 0093) IRREVOGÁVEL: command itera business by business
        // via loop Business::each() explícito. NUNCA cross-tenant cleanup.
        //
        // 03:00 BRT — janela de baixa atividade, antes do horário comercial. 30min antes
        // do fsm:scan-drift (03:00 simultâneo OK — escopos diferentes), 1h antes de
        // memcofre:sync-memories conclusão (23:00 dia anterior).
        $schedule->command('jana:retention-purge')
            ->dailyAt('03:00')
            ->timezone('America/Sao_Paulo')
            ->name('jana-retention-purge-daily')
            ->withoutOverlapping(60)
            ->environments(['live'])
            ->when(fn () => (bool) config('jana.retention.enabled', false))
            ->appendOutputTo(storage_path('logs/jana-retention.log'))
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::channel('copiloto-ai')->error(
                    'Schedule jana:retention-purge FALHOU — D7 LGPD purge atrasou ' .
                    '(investigar storage/logs/jana-retention.log)'
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

        // MCP tasks:sync — rede de proteção pra task sync (US-FIN-043 incident 2026-05-21).
        // Webhook handler (SyncMemoryWebhookController::handle) chama
        // TaskParserService::syncAll() quando SPEC.md muda — mas se o webhook
        // retornar 5xx (env stale, network), as tasks NUNCA chegam no DB MCP.
        // Cron everyTenMinutes cobre essa falha. Idempotente: parser usa hash do SPEC.
        $schedule->command('mcp:tasks:sync')
            ->everyTenMinutes()
            ->withoutOverlapping(15)
            ->environments(['live'])
            ->appendOutputTo(storage_path('logs/mcp-tasks-cron.log'))
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::channel('copiloto-ai')->error(
                    'Schedule mcp:tasks:sync FALHOU — tasks SPEC.md podem nao estar no DB'
                );
            });

        // GAP D7 #2 (auditoria memoria-senior 2026-05-15) — Freshness pipeline ativo.
        // Complementa `mcp:sync-memory` (every5min): classifica docs em 4 níveis
        // (FRESH/WARM/STALE/CRITICAL), detecta drift DB↔git, alerta CRITICAL em
        // mcp_alertas_eventos (idempotente por dia) e dispatcha ReindexarDocumentoJob
        // pros stale/drift (max 50/execução, queue jana-index).
        // Daily 04:30 BRT pra não conflitar com backup:run (01:30), Brief Brain B
        // (cron hourly) nem sync-memory (every5min). Exit code 1 quando CRITICAL > 0
        // pra cron enviar alerta operacional.
        $schedule->command('jana:freshness-check --alert --reindex --limit=50')
            ->dailyAt('04:30')
            ->timezone('America/Sao_Paulo')
            ->onOneServer()
            ->withoutOverlapping(20)
            ->environments(['live'])
            ->appendOutputTo(storage_path('logs/jana-freshness.log'))
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::channel('copiloto-ai')->error(
                    'Schedule jana:freshness-check FALHOU — docs memory podem estar STALE/CRITICAL'
                );
            });

        // NOTA: a detecção de drift de settings/embedder do Meilisearch NÃO tem cron
        // próprio — é o MeilisearchSettingsDriftChecker (Modules/Governance, ADR 0216)
        // que roda no `governance:audit --all --notify` já agendado abaixo. Cura =
        // `php artisan jana:meilisearch-setup`.

        // MEM-MULTI-1 (auditoria seed 2026-05-28) — re-seed ADRs → jana_memoria_facts
        // diário. Sem este schedule os fatos de ADR ficavam STALE: nova ADR aceita /
        // ADR supersedida não viravam fato pesquisável até alguém rodar o command na mão.
        //
        // 04:45 BRT escolhido de propósito: DEPOIS do jana:freshness-check (04:30) que
        // reindexa docs STALE/drift no Meilisearch, e bem depois do último ciclo de
        // mcp:sync-memory (every5min — withoutOverlapping protege se o sync ainda estiver
        // rodando). Idempotente (upsert por source_slug), safe pra cron diário.
        // --type=all cobre adr+spec+reference numa passada.
        $schedule->command('copiloto:seed-adrs --type=all')
            ->dailyAt('04:45')
            ->timezone('America/Sao_Paulo')
            ->name('copiloto-seed-adrs-daily')
            ->withoutOverlapping()
            ->environments(['live'])
            ->appendOutputTo(storage_path('logs/copiloto-seed-adrs.log'))
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::channel('copiloto-ai')->error(
                    'Schedule copiloto:seed-adrs FALHOU — fatos de ADR podem ficar STALE ' .
                    '(investigar storage/logs/copiloto-seed-adrs.log)'
                );
            });

        // COPI-26 fix (incidente 2026-06-20) — ProfileDistiller NUNCA foi agendado:
        // `->destilar()` tinha ZERO call sites, então jana_business_profile só tinha
        // 3 seeds one-off (biz 1/4/164) que envelheceram >7d e acendiam o check
        // `profile_distiller_drift` no jana:health-check (06:00). A sentinela
        // (L-OP-002) estava CORRETA — vigiava um job de manutenção que nunca rodava.
        // Este schedule é o job que faltava.
        //
        // 04:50 BRT: DEPOIS de copiloto:seed-adrs (04:45) e jana:freshness-check (04:30),
        // ANTES do jana:health-check (06:00) reavaliar o check. Bem dentro da janela 7d.
        // Multi-tenant Tier 0 (ADR 0093): o command itera business by business EXPLÍCITO.
        // ~76 chamadas LLM/dia (~$0,02). withoutOverlapping(15) cobre run lento (76 × ~3s).
        $schedule->command('jana:profile-distill')
            ->dailyAt('04:50')
            ->timezone('America/Sao_Paulo')
            ->name('jana-profile-distill-daily')
            ->withoutOverlapping(15)
            ->environments(['live'])
            ->appendOutputTo(storage_path('logs/jana-profile-distill.log'))
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::channel('copiloto-ai')->error(
                    'Schedule jana:profile-distill FALHOU — jana_business_profile pode ficar ' .
                    'STALE (profile_distiller_drift acende no jana:health-check 06:00)'
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

        // US-WA-078 (PR-5 CYCLE-07) — Backfill weekly auto-link Conversation→Contact CRM
        // por phone match. Webhook auto-link cobre NOVAS conversations; este cron
        // cuida das órfãs históricas e dos casos onde o Contact CRM foi cadastrado
        // DEPOIS da conv ser criada (atendente cadastrou via /contacts).
        // Segunda 03:00 BRT — horário de baixa carga + após scan-drift 03:00 sem
        // disputa por being weekly. Limit 500 protege contra business com 10k+
        // órfãs travando a janela; próxima execução pega o restante.
        $schedule->command('whatsapp:auto-link-contacts --business=all --limit=500')
            ->weeklyOn(1, '03:00')
            ->timezone('America/Sao_Paulo')
            ->withoutOverlapping()
            ->environments(['live'])
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::channel('single')->error(
                    'Schedule whatsapp:auto-link-contacts FALHOU — convs órfãs podem acumular sem Contact CRM vinculado'
                );
            });

        // Self-healing Camada 2 — probe + auto-recovery canais Baileys.
        // Itera Channels Baileys ativos, pinga /status no daemon CT 100, e
        // tenta /connect (3 retries com backoff 1s/5s/30s) se algum não
        // estiver connected. Cobre falhas do bootstrap auto-reconnect
        // Camada 1 daemon-side (Agent A paralelo). Daily 03:30 BRT — antes
        // do horário comercial pra deixar canais frescos. withoutOverlapping(30)
        // protege contra job lento (~3 canais × 3 retries × 30s = ~5min worst).
        $schedule->command('whatsapp:health-probe-channels')
            ->dailyAt('03:30')
            ->timezone('America/Sao_Paulo')
            ->withoutOverlapping(30)
            ->environments(['live'])
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::channel('single')->error(
                    'Schedule whatsapp:health-probe-channels FALHOU — canais Baileys podem estar sem auto-recovery'
                );
            });

        // Reconciler channels Baileys (5 em 5 min) — sincroniza DB.status vs daemon.state
        // e auto-corrige drift (banned/disconnected sem aviso, instance órfã, etc).
        // Wagner pediu 2026-05-13: "como resolve isso vai sempre você? automatize" —
        // este cron remove necessidade de intervenção manual no caso comum.
        // withoutOverlapping(5) protege contra round lento (20 canais * 500ms = ~10s).
        // Diferente de `whatsapp:health-probe-channels` (daily, full check com retry connect)
        // — reconcile é leve, só GET status no daemon + UPDATE drift detectado.
        $schedule->command('whatsapp:channels-reconcile')
            ->everyFiveMinutes()
            ->withoutOverlapping(5)
            ->environments(['live'])
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::channel('single')->error(
                    'Schedule whatsapp:channels-reconcile FALHOU — drift DB↔daemon pode acumular'
                );
            });

        // whatsmeow:health-probe (3 em 3 min) — fecha a lacuna que o reconciler
        // Baileys-only NÃO cobre (incidente 2026-06-18, US-WA-308): canal whatsmeow
        // "logged out from another device" sem webhook LoggedOut → channel_health
        // ficava 'healthy' e a Caixa não avisava (linha caída ~3h sem ninguém ver).
        // Probe consulta /session/status REAL e converge disconnected/banned/healthy.
        $schedule->command('whatsmeow:health-probe')
            ->everyThreeMinutes()
            ->withoutOverlapping(3)
            ->environments(['live'])
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::channel('single')->error(
                    'Schedule whatsmeow:health-probe FALHOU — queda de canal whatsmeow pode ficar invisível'
                );
            });

        // whatsapp:channel-health-snapshot (5 em 5 min) — série temporal append-only de
        // channel_health (ADR 0288): habilita uptime%/time-to-detect (SLIs) e ALERTA
        // canal-down > N min (1× por streak). Fecha o pilar de observabilidade (não
        // depende de ninguém olhar a tela).
        $schedule->command('whatsapp:channel-health-snapshot')
            ->everyFiveMinutes()
            ->withoutOverlapping(5)
            ->environments(['live'])
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::channel('single')->error(
                    'Schedule whatsapp:channel-health-snapshot FALHOU — observabilidade de canal pode estar cega'
                );
            });

        // Worker da fila `whatsapp-history` (Wagner request 2026-05-14 02h):
        // "recebe tudo de maneira rapida... depois sincroniza com o banco,
        // sempre guarda para não perder". Cron everyMinute pra processar
        // PersistHistorySyncBatchJob da tabela `jobs` (queue=database).
        //
        // --max-time=55 sai antes do próximo tick. SEM --stop-when-empty
        // (2026-06-19): o worker fica vivo os 55s pegando os batches em ~1-3s em
        // vez de morrer assim que a fila esvazia (antes: até 1min de latência pra
        // 1ª msg histórica aparecer pós-pareamento). withoutOverlapping(1) mantém
        // 1 processo/fila (respeita o limite de LSPHP do shared hosting).
        //
        // Hostinger shared hosting sem supervisor — cron é o workaround
        // padrão pra rodar queue worker.
        $schedule->command('queue:work database --queue=whatsapp-history --max-time=55 --tries=3')
            ->everyMinute()
            ->withoutOverlapping(1)
            ->environments(['live'])
            ->runInBackground()
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::channel('single')->error(
                    'Schedule queue:work whatsapp-history FALHOU — msgs históricas podem acumular em jobs table'
                );
            });

        // Worker da fila `whatsapp` (default do `ProcessIncomingWebhookJob`):
        // recebe webhooks Meta/Z-API/Baileys/whatsmeow + dispara persistência.
        // SEM ESTE CRON, msgs entrantes não persistem — incident 2026-05-28:
        // queue worker `whatsapp` órfão fazia tela ficar vazia mesmo com daemon
        // healthy e fix de código aplicado (PR #1825). Webhook chegava, job
        // enfileirado, ninguém executava. Worker manual processou 54 jobs em 2s.
        //
        // Hostinger shared hosting cron-based (sem supervisor). SEM --stop-when-empty
        // (2026-06-19): antes o worker morria assim que a fila esvaziava, deixando a
        // maior parte do minuto SEM worker → a msg entrante esperava o próximo tick
        // (0-60s, ~30s médio; reclamação real "~20s pra um oi" chegar na Caixa).
        // Agora fica vivo os 55s do --max-time e pega o webhook em ~1-3s. Custo:
        // poll leve do DB durante os 55s — limitado por --max-time=55 +
        // withoutOverlapping(1) (1 processo/fila → respeita LSPHP). Tries=3 cobre
        // retry transient (DB lock, OTel sidecar momentâneo).
        $schedule->command('queue:work database --queue=whatsapp --max-time=55 --tries=3')
            ->everyMinute()
            ->withoutOverlapping(1)
            ->environments(['live'])
            ->runInBackground()
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::channel('single')->error(
                    'Schedule queue:work whatsapp FALHOU — msgs recebidas podem ficar órfãs em jobs table'
                );
            });

        // US-WA-082 — Cleanup nonces antigos (>24h) da tabela webhook_nonces.
        // Replay window é 5min, mas mantemos 24h por margem segurança vs time
        // skew + audit forense. Após 24h é seguro deletar (replay já seria
        // rejeitado pelo REPLAY_WINDOW_SECONDS no middleware).
        $schedule->command('whatsapp:cleanup-webhook-nonces')
            ->hourly()
            ->withoutOverlapping()
            ->environments(['live']);

        // US-WA-084 — Cleanup jobs presos na fila `whatsapp-history` (>6h
        // default). Worker crashou mid-flight ou jobs órfãos consumindo queue
        // depth sem progresso → backpressure dispararia 429 sem necessidade.
        $schedule->command('whatsapp:jobs-cleanup-stale')
            ->hourly()
            ->withoutOverlapping()
            ->environments(['live'])
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::channel('single')->error(
                    'Schedule whatsapp:jobs-cleanup-stale FALHOU — backpressure pode disparar falso-positivo'
                );
            });

        // Drift sentinel daemon CT 100 (semanal segunda 09:00 BRT) — alerta se
        // source do daemon prod ficou desatualizado vs main local. Catalogado
        // 2026-05-13: ~15 commits drift descoberto na unha durante incidente.
        // Cron weekly + log warning permite catch antes de drift virar bug
        // (rebuild falha por compatibility de versions).
        $schedule->command('whatsapp:daemon-source-drift-check')
            ->weeklyOn(1, '09:00') // segunda-feira 09:00
            ->timezone('America/Sao_Paulo')
            ->environments(['live'])
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::channel('single')->error(
                    'Schedule whatsapp:daemon-source-drift-check FALHOU — daemon CT 100 pode estar offline'
                );
            });

        // whatsapp:auth-state-drift-check — daily 03h BRT
        // Detecta orphans (instance_id sem channel), banned/inactive rows
        // residuais, e stale >90d (sinal de major bump não-purgado).
        //
        // Por que daily 03h: incident 2026-05-15 catalogou que auth_state
        // 6.x → 7.x produziu 103 rows corrompidas que travaram daemon. Com
        // detection daily, alerta cai em ≤24h se algum drift novo aparecer.
        // Lição em [skill baileys-update-procedure §Fase 1.5].
        $schedule->command('whatsapp:auth-state-drift-check')
            ->dailyAt('03:00')
            ->timezone('America/Sao_Paulo')
            ->environments(['live'])
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::channel('single')->error(
                    'Schedule whatsapp:auth-state-drift-check FALHOU — drift Baileys auth_state pode estar acumulando'
                );
            });

        // Secrets Governance — ADR 0215 Camada 3 (auto-validate daily).
        // Lê memory/_INDEX-SECRETS.md, valida cada secret (curl/grep/ssh),
        // atualiza status, alerta Centrifugo + Brief Jana se drift.
        // 06h BRT (após brief regenerar primeira vez do dia).
        // --auto-pr commita mudanças; --notify publica Centrifugo.
        $schedule->command('secrets:audit --auto-pr --notify')
            ->dailyAt('06:15')
            ->timezone('America/Sao_Paulo')
            ->environments(['live'])
            ->withoutOverlapping()
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::channel('single')->error(
                    'Schedule secrets:audit FALHOU — secrets drift pode estar passando despercebido'
                );
            });

        // ADR 0215 Camada 1 — discovery weekly (segundas 09h BRT).
        // Procura secrets em git canon sem entry no índice.
        $schedule->command('secrets:scan')
            ->weeklyOn(1, '09:00')
            ->timezone('America/Sao_Paulo')
            ->environments(['live']);

        // ADR 0216 — Governance Drift Framework (orchestrator)
        // Slot 06:35 BRT escolhido (06:15 disputado por 4 schedules; 06:30 charter:health).
        // Roda TODOS DriftCheckers registrados em config/governance.php > drift_checkers[].
        // PR1 ships com 4 checkers: composer_audit, multi_tenant_scope, adr_link_rot, routes_zombie.
        // --notify publica governance:drift Centrifugo (consumido por Brief Jana 07h).
        // Canary 7d: roda em paralelo com schedules legacy (secrets:audit, governance:detect-drift).
        // Após 7d sem regressão, PR cleanup remove entries legacy.
        $schedule->command('governance:audit --all --notify')
            ->dailyAt('06:35')
            ->timezone('America/Sao_Paulo')
            ->environments(['live'])
            ->onOneServer()
            ->withoutOverlapping(60)
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::channel('single')->error(
                    'Schedule governance:audit FALHOU — drift detection paralisado, fallback nos checkers legacy 06:15'
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

        // Wave 3 Agent B (2026-05-15) — retry mídia inbound recente (24h) órfã.
        // Camada 4 pareada: Camada 4 só pega `status IN (pending, downloading)`.
        // Este comando é MAIS PERMISSIVO (qualquer media_url IS NULL com media_mime
        // NOT NULL, status irrelevante exceto failed_permanent) e MAIS CONSERVADOR
        // (só últimas 24h, limit 200).
        //
        // Cenário origem: 2026-05-15 09:25 BRT re-pareamento Baileys 7.x prod biz=1
        // (ROTA LIVRE) — 55 msgs history sync ficaram com `media_url=NULL` em estado
        // anômalo (status NULL ou success-sem-URL) — Camada 4 não pega; este pega.
        //
        // hourlyAt(15) pra NÃO disputar com Camada 4 (hourlyAt(0)) — minimiza
        // chance de 2 cron jobs batendo no daemon CT 100 no mesmo segundo.
        // withoutOverlapping(30) cobre run lento sem stomp do próximo tick.
        $schedule->command('whatsapp:retry-recent-media-downloads --hours=24 --limit=200')
            ->hourlyAt(15)
            ->withoutOverlapping(30)
            ->environments(['live'])
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::channel('single')->error(
                    'Schedule whatsapp:retry-recent-media-downloads FALHOU — mídia recente pode atrasar no Inbox'
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

        // CYCLE-07 PR-2 — SLA scan policies + alertas escalation (Gap P0 #2).
        // Itera sla_policies ativas cross-tenant, varre conversations que
        // violam threshold, dispara action (Centrifugo notify / reassign /
        // set_status). withoutOverlapping(10) cobre ~2 ciclos de 5min.
        $schedule->command('whatsapp:sla-scan --business=all')
            ->everyFiveMinutes()
            ->withoutOverlapping(10)
            ->environments(['live'])
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::channel('single')->error(
                    'Schedule whatsapp:sla-scan FALHOU — alertas SLA podem não disparar'
                );
            });

        // US-WA-021/041 (CYCLE-07 PR-3) — Snapshot diário de métricas
        // omnichannel (conversation/messages) em `whatsapp_conversation_metricas`.
        // 02:30 BRT daily, ANTES do health-check 06:00, pra dashboard
        // `/atendimento/metricas` já estar fresh quando time chega.
        // Idempotente (UPSERT) — re-run mesmo dia substitui rows.
        $schedule->command('whatsapp:metrics-aggregate')
            ->dailyAt('02:30')
            ->timezone('America/Sao_Paulo')
            ->name('whatsapp-metrics-aggregate-daily')
            ->withoutOverlapping()
            ->environments(['live'])
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::channel('single')->error(
                    'Schedule whatsapp:metrics-aggregate FALHOU — dashboard /atendimento/metricas pode mostrar dados stale'
                );
            });

        // ADR 0195 Fase B — Feedback reindex semanal (rescore + INDEX.md HOT +
        // archive trimestral COLD). Domingo 03:00 BRT, depois que activity baixa
        // de fim-de-semana. Roda quieto sem PII em log (LGPD).
        $schedule->command('feedback:reindex')
            ->weeklyOn(0, '03:00')                // 0 = domingo
            ->timezone('America/Sao_Paulo')
            ->name('feedback-reindex-weekly')
            ->withoutOverlapping()
            ->environments(['live'])
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::channel('single')->error(
                    'Schedule feedback:reindex FALHOU — INDEX.md pode estar stale (impacto baixo, semanal)'
                );
            });

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

        // Bug #3 fix MCP inbox (2026-05-13) — auto-cleanup notifications stale.
        // Marca read todas as mcp_inbox_notifications unread com created_at > 7d.
        // Daily 04:00 BRT — antes do horário comercial, depois das janelas Brain B
        // (02:00 ads:learn-patterns, 03:00 fsm:scan-drift, 03:30 health-probe).
        // Multi-tenant safe (per-user via user_id, sem business_id).
        // Ver memory/requisitos/Jana/BUGS-MCP-SYNC-2026-05-13.md.
        $schedule->job(new \Modules\Jana\Jobs\Mcp\InboxAutoCleanupJob())
            ->dailyAt('04:00')
            ->timezone('America/Sao_Paulo')
            ->withoutOverlapping()
            ->environments(['live'])
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::channel('single')->error(
                    'Schedule InboxAutoCleanupJob FALHOU — inbox notifications stale podem acumular'
                );
            });

        // ADR 0149 (ONDA 1 — 2026-05-15) — KB unificado bridge job.
        // Sincroniza mcp_memory_documents → kb_nodes incrementalmente
        // (filtro mcp_memory_documents.updated_at > kb_bridge_state.last_bridge_at).
        // Multi-tenant Tier 0: itera businesses ativos; cada biz dispatch separado.
        //
        // 15min: balance entre frescor do grafo (Wagner cria ADR, vê node em ≤15min)
        // e custo (~700 docs biz=1 + biz=4 → < 30s por run no caso incremental).
        //
        // TODO[CL]: por ora dispara só biz=1 e biz=4 explicit. Quando expandir pro
        // time MCP completo (5 pessoas), trocar pra `foreach business`. Sem afetar
        // contrato, ajuste em PR separado.
        $schedule->call(function () {
            foreach ([1, 4] as $bizId) {
                \Modules\KB\Jobs\KbBridgeFromMcpJob::dispatch($bizId, false);
            }
        })
            ->name('kb-bridge-from-mcp')
            ->everyFifteenMinutes()
            ->withoutOverlapping(10)
            ->environments(['live'])
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::channel('single')->error(
                    'Schedule KbBridgeFromMcpJob FALHOU — grafo KB pode estar stale ' .
                    '(ver kb_bridge_state.last_error por business)'
                );
            });

        // PR G (2026-05-25) G11 auditoria pós Ondas 24/25 — Backfill plano_conta_id
        // weekly pra businesses ativos. Cobre auto-criação Observer venda/compra
        // que cria Titulo sem classificação (não-Observer caminho dá DRE zerada).
        // Idempotente: só toca rows ainda NULL.
        //
        // Domingo 04:00 BRT: baixa carga, depois fsm:scan-drift (03:00) e antes
        // do horário comercial. withoutOverlapping(60) cobre business com 50k+
        // títulos (ROTA LIVRE biz=4 tinha 18054 backfilled 2026-05-20).
        $schedule->call(function () {
            try {
                $businesses = \App\Business::query()->pluck('id');
                foreach ($businesses as $bizId) {
                    \Illuminate\Support\Facades\Artisan::call('financeiro:backfill-plano-conta', [
                        '--business' => $bizId,
                    ]);
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::channel('single')->error(
                    'Schedule financeiro:backfill-plano-conta FALHOU: '.$e->getMessage()
                );
            }
        })
            ->name('financeiro-backfill-plano-conta-weekly')
            ->weeklyOn(0, '04:00') // Domingo 04:00
            ->timezone('America/Sao_Paulo')
            ->withoutOverlapping(60)
            ->environments(['live'])
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::channel('single')->error(
                    'Schedule financeiro:backfill-plano-conta FALHOU — DRE pode zerar pra business sem classificação'
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
