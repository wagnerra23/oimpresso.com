---
slug: oimpresso-automations
title: "Inventário canônico de automações — hooks, crons, rotinas"
type: governance-spec
authority: canonical
lifecycle: ativo
maintained_by: wagner
last_updated: 2026-07-20
related: [automation-registry-mcp, 0076, 0079, 0080]
pii: false
---

# Inventário canônico de automações — hooks, crons, rotinas

Este é o inventário canônico de automações do oimpresso. Como vive em `memory/`, é **indexado pelo MCP server** (via `IndexarMemoryGitParaDb`) — logo o time enxerga as automações via tools MCP (resolve a lacuna histórica de automações invisíveis). Será DB-backed via ADR proposta `automation-registry-mcp` (ADR 0234, tabela `mcp_automations` + tool `automations-list`); até lá, este markdown é a fonte canônica.

---

## Hooks SessionStart

Disparados a cada início de sessão Claude Code. Tipo ADR 0234: `hook_sessionstart`.

| Ordem | Hook | O que faz | Arquivo |
|-------|------|-----------|---------|
| 1 | `brief-fetch-curl` | Força chamada ao MCP `brief-fetch` via curl HTTP (JSON-RPC autenticado). Garante estado consolidado (~3k tokens) no contexto mesmo em worktrees filhos onde o MCP não conecta diretamente. Fallback gracioso para handoff index em 3 cenários de falha. | `.claude/hooks/brief-fetch-curl.ps1` |
| 2 | handoff inline | Imprime últimas 40 linhas de `memory/08-handoff.md` (se existir) + lembrete sobre tools MCP para estado de tasks/cycles (CURRENT.md/TASKS.md removidos — ADR 0070). | inline em `settings.json` (comando PowerShell direto) |
| 3 | `check-skills-fresh` | Detecta skills novas ou modificadas em `.claude/skills/` desde o último start deste dev. Avisa para rodar `/sync-skills` se houver drift. Estado salvo em `.claude/.last-skills-sync` (gitignored). | `.claude/hooks/check-skills-fresh.ps1` |
| 4 | `tier-a-banner` | Exibe banner lembrando as 5 Skills Tier A (nucleo) + 6 auto-trigger (ADR 0225). Recalibrado 2026-05-28: Claude 4.8 torna always-on de auto-trigger redundante; redução de 8 para 5 Tier A. | `.claude/hooks/tier-a-banner.ps1` |

---

## Hooks PreToolUse

Disparados antes de cada uso de ferramenta. Tipo ADR 0234: `hook_pretooluse`.

### Matcher: `Read|Glob|Grep`

| Matcher | Hook | O que faz | Arquivo |
|---------|------|-----------|---------|
| `Read\|Glob\|Grep` | `mcp-first-warning` | Avisa quando Claude tenta usar Read/Glob/Grep em `memory/*`, incentivando uso de tools MCP antes de ler filesystem. Enforcement cultural do reflexo MCP-first (ADR 0070 — CURRENT.md/TASKS.md removidos). | `.claude/hooks/mcp-first-warning.mjs` |

### Matcher: `Write|Edit|MultiEdit`

| Matcher | Hook | O que faz | Arquivo |
|---------|------|-----------|---------|
| `Write\|Edit\|MultiEdit` | `block-automem` | Bloqueia Write/Edit em auto-mem privada legada (`~/.claude/projects/*/memory/*.md`). Permite `~/.claude/oimpresso-local/` (escape valve). 3 tiers: Canônico (git), Local (~/.claude/oimpresso-local/), Segredo (Vaultwarden). ADR 0061 + ADR 0131. | `.claude/hooks/block-automem.mjs` |
| `Write\|Edit\|MultiEdit` | `block-memory-drift` | Bloqueia edits em paths canônicos (`memory/decisions/`, `memory/08-handoff.md`, etc.) sem branch `claude/*` + workflow PR. ADRs accepted são append-only irrevogáveis. ADR 0094 Art. 3 + ADR 0061 + ADR 0130. | `.claude/hooks/block-memory-drift.mjs` |
| `Write\|Edit\|MultiEdit` | `block-mwart-violation` | Bloqueia Edit/Write em `resources/js/Pages/<Mod>/<Tela>.tsx` sem RUNBOOK existir em `memory/requisitos/<Mod>/RUNBOOK-<tela-kebab>.md`. Garante que F1 PLAN acontece antes de F3 FRONTEND. Override via comentário `/mwart-override <razão>` no PR. ADR 0104. | `.claude/hooks/block-mwart-violation.mjs` |
| `Write\|Edit\|MultiEdit` | `charter-validate` | Avisa (modo warning, não bloqueia ainda) quando Claude tenta editar Page `.tsx` que tem `.charter.md` irmão sem ter chamado `charter-fetch` previamente. Vira bloqueante quando ROI provado (≥5 sessões). ADR 0094 + ADR 0101. | `.claude/hooks/charter-validate.mjs` |
| `Write\|Edit\|MultiEdit` | `modulo-preflight-warning` | Aviso (não bloqueia) quando Claude tenta Edit/Write em `Modules/<X>/` sem ter lido SPEC.md/RUNBOOK/charter do módulo X na sessão atual. Implementa FASE 1 PRÉ-FLIGHT da Regra Primária Tier 0. | `.claude/hooks/modulo-preflight-warning.ps1` |
| `Write\|Edit\|MultiEdit` | `block-bom-encoding` | Bloqueia Write/Edit que reintroduza UTF-8 BOM (EF BB BF) em arquivos de código. Origem: post-mortem v4 go-live (PR #984) — PowerShell 5.1 `Set-Content -Encoding utf8` gravava BOM que quebrava PHP (`Namespace declaration statement has to be the very first statement`). | `.claude/hooks/block-bom-encoding.mjs` |
| `Write\|Edit\|MultiEdit` | `block-merge-markers` | Bloqueia Write/Edit que contenha git merge conflict markers não-resolvidos (`<<<<<<<`, `=======`, `>>>>>>>`). Origem: post-mortem v4 go-live (PRs #1000/#1001) — markers chegaram em prod causando PHP parse error. Irmão em CI: `.github/scripts/merge-marker-scan.sh`. | `.claude/hooks/block-merge-markers.mjs` |
| `Write\|Edit\|MultiEdit` | `block-routes-string-legacy` | Bloqueia Write/Edit em `routes/*.php` e `Modules/*/Routes/*.php` que use sintaxe string legacy `'Controller@method'`. FQCN obrigatório: `[Class::class, 'method']`. Origem: post-mortem v4 go-live (PR #843) — strings quebravam `php artisan route:cache`. | `.claude/hooks/block-routes-string-legacy.mjs` |

### Matcher: `Bash`

| Matcher | Hook | O que faz | Arquivo |
|---------|------|-----------|---------|
| `Bash` | `block-destructive` | Bloqueia comandos Bash destrutivos sem confirmação humana: `rm -rf` em paths críticos, `git push --force` em main/master, `git reset --hard origin/*`, `DROP TABLE/DATABASE`, `DELETE FROM` sem WHERE, `composer update` sem `--lock`, `migrate:fresh/reset` em prod, `TRUNCATE TABLE`. | `.claude/hooks/block-destructive.mjs` |
| `Bash` | `pii-redactor` | Bloqueia `git commit` que levaria PII real (CPF, CNPJ, cartão) pro repo — escaneia a mensagem do commit + o staged diff. Comandos não-commit (mysql/grep/ssh/cat/echo) passam direto, sem inspeção (opção B, PR #2683 — não atrapalhar debug de ERP por CPF/CNPJ). Bypass `--allow-pii`. LGPD Art. 7 (minimização). Whitelist de fixtures fake. | `.claude/hooks/pii-redactor.mjs` |
| `Bash` | `commit-discipline-check` | Enforcement Skill Tier A commit-discipline via PreToolUse Bash (git commit/add). ADR 0094 §5. | `.claude/hooks/commit-discipline-check.mjs` |
| `Bash` | `block-claim-without-evidence` | Bloqueia `gh pr create`/`gh pr merge --admin`/`git push` para branches que tocam infra crítica se o body do PR não contém evidência curl/HTTP literal. Camada B pareada com CI gate `.github/workflows/infra-contract-required.yml`. Escape: `# evidence-override: <razão>`. | `.claude/hooks/block-claim-without-evidence.mjs` |
| `Bash` | `post-merge-ui-smoke-required` | Após `gh pr merge --admin` de PR com arquivos UI (.tsx/.css/.blade.php), marca flag pendente e bloqueia Claude de declarar "pronto"/"deployed" sem screenshot real. Enforcement Tier 0 smoke visual pós-merge. Também ativo em PreToolUse `mcp__computer-use__screenshot\|mcp__Claude_in_Chrome__.*`. | `.claude/hooks/post-merge-ui-smoke-required.mjs` |
| `Bash` | `block-serving-branch-switch` | Bloqueia troca de branch no checkout MAIN (`D:\oimpresso.com`) que serve `oimpresso.test` via Herd. Trabalho de feature vai em worktree isolado. Worktrees linkados (`.claude/worktrees/*`) são liberados. ADR 0233. Fail-open. | `.claude/hooks/block-serving-branch-switch.mjs` |

### Matcher: `mcp__computer-use__screenshot|mcp__Claude_in_Chrome__.*`

| Matcher | Hook | O que faz | Arquivo |
|---------|------|-----------|---------|
| `mcp__computer-use__screenshot\|mcp__Claude_in_Chrome__.*` | `post-merge-ui-smoke-required` | Mesmo hook acima — também disparado em ferramentas de screenshot para verificar contexto de smoke pós-merge pendente. | `.claude/hooks/post-merge-ui-smoke-required.mjs` |

---

## Hooks PostToolUse

Disparados após uso de ferramenta. Tipo ADR 0234: `hook_posttooluse`.

| Matcher | Hook | O que faz | Arquivo |
|---------|------|-----------|---------|
| `Bash` | `post-merge-ui-smoke-required` | PostToolUse Bash: detecta `gh pr merge --admin` em PR que tocou arquivos UI e marca timestamp em flag `$env:TEMP/oimpresso-ui-merge-pending.flag`. Trabalha em conjunto com a verificação PreToolUse do mesmo hook. | `.claude/hooks/post-merge-ui-smoke-required.mjs` |
| `Write\|Edit` | `audit-creates-tasks` | PostToolUse Write/Edit: detecta tasks órfãs em documentos de audit (`memory/sessions/*-audit-*.md` ou `memory/requisitos/*/AUDIT-*.md`) e propõe `tasks-create` MCP para cada gap identificado. Mecanismo 2 do ADR 0213 (audit-to-backlog loop fechado). Cross-platform Node.js. | `.claude/hooks/audit-creates-tasks.mjs` |

---

## Hooks Stop

Disparados quando Claude encerra a resposta. Tipo ADR 0234: `hook_sessionstart` (Stop).

| Ordem | Hook | O que faz | Arquivo |
|-------|------|-----------|---------|
| 1 | `memory-pending` | Detecta arquivos em `memory/`, `MEMORY*.md`, `*.SPEC.md` e governança raiz modificados/novos sem push, e avisa para rodar `/sync-mem` antes de encerrar o turno. Evita drift team (webhook GitHub→MCP só sincroniza após push). | `.claude/hooks/memory-pending.mjs` |
| 2 | `nudge-recommend-not-menu` | Advisory (exit 0 sempre, nunca bloqueia): detecta resposta terminando em menu de decisão técnica sem recomendação cravada. R13 / ADR 0233. | `.claude/hooks/nudge-recommend-not-menu.mjs` |
| 3 | `nudge-diagnosis-without-evidence` | Advisory (exit 0 sempre): detecta diagnóstico/causa afirmado sem evidência (grep/log/SQL/trace/curl). Origem: sessão 2026-05-29 chutou causa de HTTP 500 antes de ler o log. R1 / ADR 0233. | `.claude/hooks/nudge-diagnosis-without-evidence.mjs` |

---

## Hooks UserPromptSubmit

Disparados a cada prompt enviado pelo usuário. Tipo ADR 0234: `hook_sessionstart` (UserPromptSubmit).

| Ordem | Hook | O que faz | Arquivo |
|-------|------|-----------|---------|
| 1 | `force-r12-closing-signal` | Detecta sinal de fechamento de sessão no prompt do usuário e força protocolo R12 (encerrar-sessao). Camada 2 de ativação R12; Camada 1 é skill `encerrar-sessao` Tier B. Cross-platform Node.js (funciona Wagner + Felipe + Maiara + Eliana + Luiz em qualquer OS). | `.claude/hooks/force-r12-closing-signal.mjs` |

---

## Arquivos presentes em hooks/ mas NÃO ativos em settings.json

| Arquivo | Observação |
|---------|------------|
| `block-pr-without-approval.mjs` | Bloqueia `gh pr create`/`gh pr merge` sem aprovação humana prévia (R10). Arquivo existe mas consta no cabeçalho como "PROPOSTA — ainda NÃO registrada em settings.json". Inativo. |

---

## Crons (app/Console/Kernel.php)

Tipo ADR 0234: `cron`. Todos os schedules são ambientes `live` salvo indicação. BRT = America/Sao_Paulo.

| Command | Schedule | O que faz |
|---------|----------|-----------|
| `backup:clean` | `daily` às 01:00 | Limpa backups antigos (env=live). |
| `backup:run` | `daily` às 01:30 | Executa backup completo (env=live). |
| `pos:generateSubscriptionInvoices` | `dailyAt('23:30')` | Gera faturas recorrentes de assinatura (env=live). |
| `pos:updateRewardPoints` | `dailyAt('23:45')` | Atualiza pontos de fidelidade (env=live). |
| `pos:autoSendPaymentReminder` | `dailyAt('8:00')` | Envia lembretes automáticos de pagamento (env=live). |
| `memcofre:sync-memories` | `dailyAt('23:00')` | Sincroniza memória Claude para dentro do repo (SRS). Idempotente. env=local,live. Histórico: docvault:sync → memcofre:sync (ADR 0088). |
| `jana:cycles:auto-close-expired` | `dailyAt('23:55')` BRT | Auto-fechamento de cycles expirados (Linear-style). Cria CYCLE-N+1 em planning se não há próximo. H5 Onda 3. |
| `copiloto:metrics:apurar --business=all` | `dailyAt('23:55')` | Apura 8 métricas obrigatórias + 3 RAGAS por business. Grava 1 linha/dia em `copiloto_memoria_metricas` (upsert idempotente). MEM-MET-3 (ADRs 0050+0051). |
| `jana:weekly-digest` | segundas `09:00` BRT | Digest Reflect-style semanal: commits, PRs, US, ADRs, handoffs, cycle goals delta + audit log. gpt-4o-mini ~R$ [redacted Tier 0] H6 Onda 3. |
| `copiloto:sintese-semanal` | sextas `18:00` | Síntese semanal automática: commits + arquivos memory/ + diffs da semana. Haiku 4.5. ~R$ [redacted Tier 0]/execução. MemoriaAutonoma Fase 1. |
| `jana:health-check --notify` | `dailyAt('06:00')` BRT | 5 checks SQL: multi_tenant_isolation, brief_uptime_24h, custo_brain_b_24h, pii_leak_in_assistant_responses, profile_distiller_drift. |
| `sells:smoke-daily --notify` | `dailyAt('06:30')` BRT | 5 sinais smoke Sells/Index Cowork: schema, multi-tenant biz=1/4, Vite manifest, CSS scoped, SellController shape. US-SELL-COWORK-R6-SMOKE. |
| `module:grade-snapshot` | `dailyAt('06:05')` BRT | Snapshot diário de module grades (sparkline 7d). 1 row/módulo em `mcp_module_grades_history`. ADR 0153/0155. |
| `governance:scorecard-snapshot --alert` | `dailyAt('07:00')` BRT | Scorecard snapshot bucket-scoped + drift detection. 1 row/módulo/dia em `mcp_scorecard_runs`. Alerta drifts >=5pts em `mcp_alertas`. Wave 24. |
| `governance:initiative-sync` | `dailyAt('08:00')` BRT | Sync Initiatives ↔ scorecards (Cortex-style): abre breach, fecha recuperadas, expira deadlines. Wave 28. |
| `observability:aggregate-daily` | `dailyAt('02:00')` BRT | Rollup diário OTel spans: computa p50/p95/p99 + error rate por (module, span_name). ADR 0162. |
| `jana:system-audit --notify` | `dailyAt('06:15')` | Audit 5 dimensões: observability, evals, ADR-stale, cost-agg, test-coverage. SQL+FS only, ZERO LLM. ADR 0133. |
| `mcp:tasks:health-check` | `dailyAt('06:20')` BRT | Flagga tasks dormentes em `mcp_tasks`: stale_todo >21d, stale_blocked >30d, stale_doing >7d sem commit, stale_review >5d. |
| `NarrarSaudeEcosistemaJob` (Job) | `hourlyAt(30)` | Brain A narrador horário do Cockpit Saúde: HealthSnapshotService + HealthNarratorService (gpt-4o-mini). ~R$ [redacted Tier 0]/dia. US-COPI-100. |
| `charter:health --notify` | `dailyAt('06:30')` | Drift detector daily de Page Charters. Métrica M6 anti-hallucination ratchet. S6 F2. |
| `arquivos:health-check --alert` | `dailyAt('06:30')` BRT | 5 sinais compliance LGPD + integridade DMS: orphan_files, dedupe_inconsistent, audit_log_lag, retention_overdue, vault_encryption_ratio. ADR 0123. |
| `governance:detect-drift` | `dailyAt('06:15')` BRT | Compara `Modules/<X>/SCOPE.md.contains[]` × filesystem real de Controllers. Persiste alertas em `mcp_alertas_eventos` (tipo=module_drift). ADR 0094 Art. 7. |
| `SyncBankStatementsJob` (Job) | `dailyAt('07:00')` | Sync extrato bancário Inter D-7 (Banking API v2). Idempotente via UNIQUE. US-RB-046. |
| `customer-memory:refresh-daily` | `dailyAt('02:00')` BRT | Re-dispatcha `RebuildCustomerMemoryJob` para customers com `last_rebuilt_at > 24h` ou NULL. US-WA-VOZ-001. |
| `employee-performance:refresh-daily` | `dailyAt('02:30')` BRT | Re-dispatcha rebuild de scorecards de performance. US-WA-VOZ-003. |
| `ads:review-decisions --limit=10` | `everyFifteenMinutes()` | Review automático de decisions sem score (G-Eval T11 ADS). |
| `ads:learn-patterns --business=all --detect-drift` | `dailyAt('02:00')` | Pattern Learning ADS Wilson Score (T15). |
| `ads:auto-generate-tasks` | `cron('0 9-18 * * 1-5')` | Auto Task Generator ADS Self-Instruct (T7). Horário comercial seg–sex 9h–18h. |
| `ads:plan-decisions --limit=3` | `everyTenMinutes()` | ADS Planner (T9 PlannerAgent): decompõe decisions complexas. |
| `ads:process-brain-b --limit=5` | `everyFiveMinutes()` | ADS Brain B: processa decisions com `destination=brain_b`. ~$0.05/dia com prompt caching Sonnet. |
| `jana:retention-purge` | `dailyAt('03:00')` BRT | LGPD purge job: aplica `Modules/Jana/Config/retention.php` sobre 7 entidades PII-relevantes (estratégia default `anonymize`). Atrás de `JANA_RETENTION_ENABLED=true` (default false). ADR 0105. D7.d. |
| `copiloto:cleanup-memoria` | `weeklyOn(0, '03:00')` (dom) | MEM-FASE8: remove bloat (hits=0, >30d) + expirados (valid_until >90d) + órfãos MCP. Soft-delete padrão. |
| `mcp:sync-memory --reason=cron` | `everyFiveMinutes()` | Rede de proteção para webhook GitHub: re-indexa memory quando sha de arquivo muda. Idempotente. |
| `mcp:tasks:sync` | `everyTenMinutes()` | Rede de proteção para task sync: `TaskParserService::syncAll()` quando SPEC.md muda. Idempotente via hash do SPEC. |
| `jana:freshness-check --alert --reindex --limit=50` | `dailyAt('04:30')` BRT | Freshness pipeline ativo: classifica docs em FRESH/WARM/STALE/CRITICAL, detecta drift DB↔git, dispatcha `ReindexarDocumentoJob` pros stale (max 50/execução). GAP D7 #2. |
| `copiloto:seed-adrs --type=all` | `dailyAt('04:45')` BRT | Re-seed ADRs → `jana_memoria_facts` diário. Cobre adr+spec+reference. Idempotente (upsert por source_slug). MEM-MULTI-1. |
| `brief:generate` | `cron('0 7,11,14,17,20,23 * * *')` BRT | Gera brief 6x/dia (horário comercial PT-BR). ~$0.30/dia. ADR 0091. |
| `ProcessRemindersJob` (Job) | `hourly()` | Processa lembretes WhatsApp pendentes (`due_at<=now()`, `notified_at IS NULL`). Publica Centrifugo per-user. US-WA-076 / ADR 0142. |
| `whatsapp:health-check-all` | `cron('0 */6 * * *')` | Health check drivers WhatsApp (Z-API/Baileys) a cada 6h. Ativa fallback automático Meta Cloud se ban detectado. US-WA-014 / ADR 0096. |
| `whatsapp:auto-link-contacts --business=all --limit=500` | `weeklyOn(1, '03:00')` (seg) BRT | Backfill semanal auto-link Conversation→Contact CRM por phone match. Cobre órfãs históricas. US-WA-078. |
| `whatsapp:health-probe-channels` | `dailyAt('03:30')` BRT | Self-healing Camada 2: probe + auto-recovery canais Baileys (3 retries com backoff). |
| `whatsapp:channels-reconcile` | `everyFiveMinutes()` | Reconcilia DB.status vs daemon.state (Baileys). Auto-corrige drift leve (disconnect sem aviso, órfã). |
| `queue:work database --queue=whatsapp-history --max-time=55 ...` | `everyMinute()` | Worker fila `whatsapp-history`: processa `PersistHistorySyncBatchJob`. Workaround Hostinger shared hosting (sem supervisor). |
| `queue:work database --queue=whatsapp --max-time=55 ...` | `everyMinute()` | Worker fila `whatsapp`: processa `ProcessIncomingWebhookJob`. Sem este cron, msgs entrantes não persistem (incident 2026-05-28). |
| `whatsapp:cleanup-webhook-nonces` | `hourly()` | Cleanup nonces antigos >24h da tabela `webhook_nonces`. US-WA-082. |
| `whatsapp:jobs-cleanup-stale` | `hourly()` | Cleanup jobs presos na fila `whatsapp-history` (>6h). Evita backpressure falso-positivo. US-WA-084. |
| `whatsapp:daemon-source-drift-check` | `weeklyOn(1, '09:00')` (seg) BRT | Drift sentinel daemon CT 100: alerta se source do daemon prod ficou desatualizado vs main local. |
| `whatsapp:auth-state-drift-check` | `dailyAt('03:00')` BRT | Detecta orphans, banned/inactive residuais e stale >90d em Baileys auth_state. |
| `secrets:audit --auto-pr --notify` | `dailyAt('06:15')` BRT | Valida secrets de `memory/_INDEX-SECRETS.md`, atualiza status, alerta Centrifugo + Brief. ADR 0215 Camada 3. |
| `secrets:scan` | `weeklyOn(1, '09:00')` BRT | Discovery semanal: procura secrets em git canon sem entry no índice. ADR 0215 Camada 1. |
| `governance:audit --all --notify` | `dailyAt('06:35')` BRT | Governance Drift Framework (orchestrator): roda todos DriftCheckers (composer_audit, multi_tenant_scope, adr_link_rot, routes_zombie). ADR 0216. |
| `RetryFailedMediaDownloadsJob` (Job) | `hourly()` | Retry mídia órfã (status=pending\|downloading, attempts<5, últimos 7d). Guardião 6 camadas Camada 4. |
| `whatsapp:retry-recent-media-downloads --hours=24 --limit=200` | `hourlyAt(15)` | Retry mais permissivo de mídia recente (24h): pega estados anômalos que Camada 4 não pega. Wave 3 Agent B. |
| `whatsapp:scan-media-drift` | `dailyAt('03:30')` BRT | Loga métricas de mídia pendente/falha (sem correção). Guardião Camada 5. |
| `whatsapp:sla-scan --business=all` | `everyFiveMinutes()` | Scan SLA policies ativas cross-tenant; dispara actions (Centrifugo notify/reassign/set_status) para conversations que violam threshold. CYCLE-07 PR-2. |
| `whatsapp:metrics-aggregate` | `dailyAt('02:30')` BRT | Snapshot diário de métricas omnichannel em `whatsapp_conversation_metricas`. Dashboard `/atendimento/metricas`. US-WA-021/041. |
| `feedback:reindex` | `weeklyOn(0, '03:00')` (dom) BRT | Reindex semanal feedback: rescore + INDEX.md HOT + archive COLD. ADR 0195 Fase B. |
| `nfebrasil:dist-dfe-puxar` | `dailyAt('06:15')` | Distribuição DFe via NSU SEFAZ ambiente nacional para businesses com cert ativo. US-NFE-051 / ADR 0116. |
| `fsm:scan-drift transactions` | `dailyAt('03:00')` BRT | Detecta `current_stage_id` que bypassou `TransactionFsmObserver` via mass-update. ADR 0129 §Drift Detection. |
| `InboxAutoCleanupJob` (Job) | `dailyAt('04:00')` BRT | Auto-cleanup notifications MCP inbox: marca read todas as notificações unread com `created_at > 7d`. |
| `KbBridgeFromMcpJob` (via `schedule::call`) | `everyFifteenMinutes()` | Sincroniza `mcp_memory_documents` → `kb_nodes` (grafo KB) incrementalmente para biz=1 e biz=4. ADR 0149. |
| `financeiro:backfill-plano-conta` (via `schedule::call`) | `weeklyOn(0, '04:00')` (dom) BRT | Backfill `plano_conta_id` NULL em títulos para businesses ativos. Idempotente. |
| `rb:sync-bank-balances` | `hourly()` | Sincroniza saldo Asaas/Inter para `contas_bancarias.saldo_cached`. US-RB-045. |
| `pos:dummyBusiness` | `cron('0 */3 * * *')` | Reset completo com dados dummy (env=demo APENAS). |

---

## Rotinas

Tipo ADR 0234: `routine`. Automações orquestradas de mais alto nível que não são hooks nem crons simples.

| Nome | Gatilho | O que faz | Arquivo(s) |
|------|---------|-----------|------------|
| **Fechar o Loop** _(primeira rotina tipo routine registrada — audit 2026-05-29)_ | SessionStart, após brief-fetch | Verifica idempotentemente os 4 gaps P0 da auditoria IA-OS (RAGAS CI, drift sentinel, observability, LGPD purge) e aponta o próximo pendente; NUNCA toca Brain B/autonomia. | `.claude/hooks/loop-fechar-check.ps1` + `.claude/loop-fechar-o-loop.json` _(criados e validados 2026-05-29; já registrados no `SessionStart` do `settings.json`)_ |

---

## Como manter

- Ao **criar ou alterar hook** em `.claude/hooks/`: atualizar a seção correspondente neste doc (SessionStart / PreToolUse / PostToolUse / Stop / UserPromptSubmit) e registrar no `settings.json`.
- A coluna **Arquivo** é conferida mecanicamente pelo **Check P** do `scripts/governance/memory-health.mjs`: todo path `.claude/**` citado aqui tem que existir em disco, e a acusação vem com arquivo + linha. Porte (`.ps1`→`.mjs`), rename ou deleção **atualiza a linha no MESMO PR**. Quem decide se isso bloqueia o merge é o ruleset — fonte única: `governance/required-checks-baseline.json` (não restatear aqui). _Origem: os portes dos PRs #4028/#4035 deixaram 4 refs apontando pra arquivo morto neste doc; corrigidas à mão no #4416 — o conserto não impedia a reincidência, o check impede._
- Ao **criar ou alterar cron** em `app/Console/Kernel.php`: adicionar linha na tabela "Crons".
- Ao **criar rotina** nova: adicionar linha na tabela "Rotinas" com gatilho e arquivos.
- Futuramente: quando ADR 0234 (`automation-registry-mcp`) for implementada, rodar `AutomationRegistrySync` após cada mudança — este markdown passa a ser espelho humano da tabela `mcp_automations`.
