---
title: "RUNBOOK — Observabilidade de produção (auditoria + gaps + stack mínima proposta)"
module: Infra
owner: W
status: rascunho
last_validated: "2026-11-14"
preconditions:
  - "Leitura do repo em origin/main 20040dde34 — nada foi validado contra o .env de produção"
  - "Nenhuma config de produção é aplicada por este RUNBOOK sem OK [W] por item"
related_adrs:
  - "0093-multi-tenant-isolation-tier-0"
  - "0132-langfuse-self-host-ct100"
  - "0162-otel-collector-prod-observability"
  - "0429-log-de-falhas-glitchtip-self-host-ct100"
---

# RUNBOOK — Observabilidade de produção

> **Status: rascunho / proposta.** Auditoria 2026-11-14 de `origin/main` (`20040dde34`). Só leitura de repositório — o `.env` de produção **não** foi lido, então tudo marcado "?" na §1 precisa de verificação read-only (§4).
> **Veredito:** observabilidade **NÃO está pronta** para produção multi-cliente. Há bastante sentinela caseira rodando, mas **quase todo alerta termina numa linha do `laravel.log`** que ninguém lê, e não existe nenhum olho externo ao próprio servidor.

## 1. O que existe hoje (inventário)

| Componente | Existe? | Ligado em prod? | Alerta chega a alguém? | Evidência |
|---|---|---|---|---|
| Uptime externo (Kuma/UptimeRobot/etc.) | ❌ | — | ❌ | nenhuma config; ADR 0216:52 cita "/up 404" |
| Rota `/up` / health profundo | ❌ | — | — | `routes/`, `bootstrap/app.php` (boot legado, sem `withRouting(health:)`) |
| `/api/mcp/health` | ✅ estático | ✅ CT 100 | ❌ (só roteamento Traefik) | `Modules/Forja/Http/Controllers/Mcp/HealthController.php:28-36` |
| MCP drift sentinel (GH Actions 30 min) | ✅ | ✅ | 🟡 issue + run vermelho (só alarma com `MCP_DRIFT_TOKEN`) | `.github/workflows/mcp-drift-sentinel.yml` |
| Sentry SDK (`sentry/sentry-laravel`) | ✅ pacote | ❌ nenhuma captura no `Handler` | ❌ | `app/Exceptions/Handler.php:46-72`, `config/sentry.php` |
| GlitchTip (Sentry-compatível) | ✅ `apm.oimpresso.com` | 🟡 só app das lojas; DSN do ERP ausente | ? (sem SMTP) | ADR 0429 (proposto), `docker/glitchtip/README.md`, `Infra/SPEC.md:110` |
| Telescope | ✅ pacote auto-discovered | ? sem config/gate/migration/prune | — | `composer.json:32`; sem `config/telescope.php` |
| Horizon | ✅ | ❌ (Hostinger usa fila `database` via cron) | — | `app/Providers/HorizonServiceProvider.php:40-43` |
| Workers de fila | ✅ `queue:work --max-time=55` por minuto | 🟡 fila `default` só com `QUEUE_BACKLOG_WORKER_ENABLED` (default **false**) | ❌ sem `queue:monitor` | `app/Console/Kernel.php:1257-1388`, `config/queue.php:123` |
| OpenTelemetry SDK | ✅ ~271 arquivos com `OtelHelper::span` | ❌ `OTEL_ENABLED=false` no Hostinger por design | ❌ | `app/Providers/OtelServiceProvider.php:31-58` |
| OTel Collector + Tempo | 📄 config + RUNBOOK | ❌ provavelmente não deployado (ausente nos inventários CT 100) | ❌ | `infra/ct100/otel/*`, `memory/reference/infra-proxmox-ct100.md:203-214` |
| Jaeger all-in-one | ✅ in-memory | ✅ CT 100 | ❌ | `memory/reference/observability-jaeger-ct100.md:12-17` |
| `observability:aggregate-daily` | ✅ agendado 02:00 | ⚠️ lê `mcp_observability_spans`, que **não tem escritor** | — | `Kernel.php:520` |
| Langfuse (IA) | ✅ `langfuse.oimpresso.com` | ? `LANGFUSE_ENABLED` default false; job vai pra fila `default` | 🟡 health-check `langfuse_trace_uptime_24h` → log | `RUNBOOK-langfuse-operacional.md`, `LangfuseClient.php:377-424` |
| Custo IA | 🟡 `mcp_audit_log.custo_brl` + quota por usuário + alerta Jana > R$5/dia | 🟡 `mcp_usage_diaria` **sem escritor**; `ai_usage_log` só no OCR de boleto | log / `mcp_alertas_eventos` | `McpAuthMiddleware.php:115-140`, `HealthCheckCommand.php:505-547` |
| Latência por rota | 🟡 `ContadorHitsRota` conta hits, **sem duração** | ✅ | ❌ | `app/Http/Middleware/ContadorHitsRota.php:47-68` |
| Slow query log | ❌ (app e MySQL) | — | — | grep vazio; `Infra/SPEC.md:123,131` (US-INFRA-003 todo) |
| Detector de vazamento multi-tenant | 🟡 batch: `business_id IS NULL` em 3 tabelas Jana | ✅ 06:00 + hora em hora | ❌ log | `HealthCheckCommand.php:352-379` |
| Log de acesso LGPD | 🟡 Fisio: abrir documento + exports/PDF | ✅ | — | `DocumentosPacienteController.php:120-124`; **ver prontuário/paciente não loga** |
| `jana:health-check --notify` (25 checks) | ✅ | ✅ | ❌ `Log::channel('single')->error` | `HealthCheckCommand.php:148-219` |
| Heartbeat do scheduler | ❌ zero `thenPing`/`pingOnSuccess` | — | — | se o cron parar, **todo alerta acima para junto** |
| Backup DB + `backup:monitor` | ✅ 01:30 / 09:00 | ✅ | ❌ mail com SMTP falhando (535) desde 2026-06-21 | `config/backup.php:165-189` |
| Prometheus / Alertmanager / Grafana / Loki | 📄 regras + dashboard JSON | ❌ não deployados | ❌ | `infra/prometheus/*`, `infra/grafana/*`; regras citam Baileys (daemon já é wuzapi/whatsmeow) |
| Expiração SSL / domínio | ❌ (só auto-renew Let's Encrypt) | — | ❌ | ADR 0043:58 |
| Deploy smoke + failsafe 503 | ✅ | ✅ | 🟡 run vermelho | `.github/workflows/deploy.yml:589,762,837-933` |
| Canary / rollback automático | ❌ (tar dos últimos 5, sem DB) | — | — | `deploy.yml:367-418` |
| Tool MCP `system-health-audit` | ✅ | ⚠️ devolveu **500** em 2026-11-14 | — | chamada nesta auditoria |

**Domínios:** `uniafibro.com.br` é alias no mesmo Hostinger/mesmo app (página estática, `routes/web.php:460-477`) — cai junto com `oimpresso.com`, sem monitor próprio. CT 100: `mcp.`, `realtime.` (Centrifugo), `langfuse.`, `apm.`, `vault.`, `meilisearch.` — todos atrás do Traefik, nenhum monitorado de fora.

## 2. Gaps priorizados

| # | Prio | Gap | Por que dói | Toca app? |
|---|---|---|---|---|
| G1 | **P0** | Nenhum canal de alerta push funciona (tudo → `laravel.log`; SMTP 535) | Toda a sentinela existente é inútil sem destino | não (config de webhook) |
| G2 | **P0** | Zero uptime externo de `oimpresso.com`, `uniafibro.com.br`, `mcp.`, `realtime.` | Cliente descobre a queda antes de [W] | não |
| G3 | **P0** | Sem heartbeat do scheduler (dead-man's switch) | Cron parado = silêncio total, parece "tudo verde" | 1 linha em `Kernel.php` |
| G4 | **P0** | Exceções/5xx não capturadas (Sentry instalado, não ligado; GlitchTip já de pé) | 500 em tela de cliente só aparece se alguém abrir o log | sim (`Handler.php`) |
| G5 | **P0** | Alerta de falha de backup quebrado (SMTP) + sem cópia off-site | Backup pode estar falhando há semanas sem ninguém saber | não (sink) / infra |
| G6 | P1 | Fila: sem alerta de `failed_jobs` nem idade do job mais velho; fila `default` sem worker por padrão | Langfuse, NF-e e jobs `default` podem empilhar em silêncio | check novo no health-check |
| G7 | P1 | Sem health endpoint profundo (DB, cache, disco, idade da fila, último tick) | Uptime só vê "HTML respondeu", não "app saudável" | sim (rota read-only) |
| G8 | P1 | Latência P95 inexistente (/login, /financeiro, /copiloto/chat) | Lentidão é a reclamação nº 1 antes da queda | sim (sample tracing) |
| G9 | P1 | Slow query log inexistente | Hostinger compartilhado: provável sem `slow_query_log` → precisa ser no app | sim (`DB::whenQueryingForLongerThan`) |
| G10 | P1 | Expiração SSL/domínio sem monitor | Cert/domínio vencido = site fora | não (Uptime Kuma faz) |
| G11 | P2 | Custo IA por módulo/dia: `mcp_usage_diaria` sem escritor; `ai_usage_log` só 1 caller | Sem ver custo por módulo, não dá pra cortar | sim (agregador) |
| G12 | P2 | LGPD: ver paciente/prontuário (Fisio) não gera log de acesso; Associações sem requisito | Art. 37 LGPD — registro de operações sobre dado sensível | sim (módulo Fisio — dono de outra sessão) |
| G13 | P2 | Multi-tenant leak só batch em 3 tabelas | Não pega query nova sem `business_id` em tabela de negócio | sim — **exige ADR** (risco de perf) |
| G14 | P3 | Lixo de observabilidade: regras Prometheus de Baileys, `aggregate-daily` sem escritor, OTel/Tempo não deployado, Telescope sem config | Dá falsa sensação de cobertura | limpeza |

## 3. Stack mínima proposta (self-host CT 100 + 1 olho externo)

Princípio: **reaproveitar o que já está de pé** (GlitchTip, Langfuse, `jana:health-check`, `ErrorReporter`) e adicionar só o que falta. **Não** subir Prometheus + Grafana + Loki + Alertmanager agora — ~1,5–2 GB RAM no CT 100 e manutenção que uma pessoa não sustenta; revisitar quando houver mais clientes pagantes.

| Peça | Onde | Cobre | Custo |
|---|---|---|---|
| **Uptime Kuma** | CT 100 (container, ~100 MB) | G2 ping 60s de todas as URLs + G10 SSL + *push monitors* p/ G3 (scheduler) e G5 (backup) + status page `status.oimpresso.com` (dashboard Health) | R$ 0 |
| **Olho externo** (UptimeRobot free ou Healthchecks.io free) | SaaS | Vigia o próprio Kuma + `oimpresso.com` a cada 5 min — cobre "CT 100/link caiu e ninguém avisou" | R$ 0 |
| **GlitchTip** (já existe) | CT 100 `apm.` | G4 exceções + alerta de taxa de erro; G8 performance com `traces_sample_rate` baixo (0,05; 1,0 nas 3 rotas críticas) | R$ 0 |
| **Langfuse** (já existe) | CT 100 | custo/tokens por trace IA (G11 parcial) | R$ 0 |
| **Canal de alerta único** | **Telegram bot** — decidido por [W] em 2026-11-14 ("1 ok") | G1: Kuma, GlitchTip, `ERROR_S0_WEBHOOK`, `jana:health-check --notify`, `backup` | R$ 0 |

> **Não usar o WhatsApp do próprio sistema como canal de alerta** — dependência circular (o daemon é uma das coisas que precisa ser vigiada).

## 4. Verificação read-only antes de qualquer mudança

Rodar no Hostinger (somente leitura — `config:show` não altera nada):

```bash
php artisan config:show sentry.dsn telescope.enabled otel.enabled queue.default logging.default
```

```bash
php artisan tinker --execute="dump(DB::table('failed_jobs')->count(), DB::table('jobs')->min('created_at'));"
```

Responder: `TELESCOPE_ENABLED` está `false`? `LANGFUSE_ENABLED` / `QUEUE_BACKLOG_WORKER_ENABLED` / `ERROR_S0_WEBHOOK` estão setados? Quantos `failed_jobs`? Idade do job mais velho?

## 5. Regras de segurança para mudanças no app ("não estragar oimpresso.com")

Toda mudança que injeta middleware / listener / handler segue:

1. **Flag de env com default OFF** (ex.: `OBS_SLOW_QUERY_ENABLED`, DSN vazio = no-op). Deploy chega desligado.
2. **Fail-open:** código de observabilidade em `try/catch` — nunca derruba request.
3. **PII:** `send_default_pii=false`, `before_send` que remove CPF/CNPJ/e-mail/bindings SQL; `business_id` vira hash (padrão ADR 0162).
4. **Canary:** ligar a flag [W] → observar 24h (erro, latência, disco) → só então seguinte item.
5. **Rollback = desligar a flag no `.env`** + `php artisan config:clear`. Sem reverter deploy.
6. 1 PR por item, ≤300 linhas, com Pest cobrindo "flag off = comportamento idêntico".

## 6. Plano de execução (aguarda aprovação [W] item a item)

| # | Ação | Gap | Toca prod? | Quem |
|---|---|---|---|---|
| 1 | Verificação read-only da §4 | — | leitura | [W] ou agente com acesso |
| 2 | Criar bot Telegram (ou canal Slack) e guardar o token no Vaultwarden | G1 | não | [W] |
| 3 | Subir Uptime Kuma no CT 100 atrás do Traefik + monitores: `oimpresso.com/login`, `uniafibro.com.br`, `mcp./api/mcp/health`, `realtime./health`, `langfuse.`, `apm.`, `vault.` + SSL | G2, G10 | CT 100 | agente, OK [W] |
| 4 | Olho externo (free) vigiando Kuma + `oimpresso.com` | G2 | não | [W] cria conta |
| 5 | `ERROR_S0_WEBHOOK` e `LOG_SLACK_WEBHOOK_URL` apontando pro canal; `backup.php` com sink Slack/Discord-compatível | G1, G5 | `.env` prod | [W] flip |
| 6 | Heartbeat: `schedule->call(fn)->everyFiveMinutes()->thenPing(env('SCHEDULER_HEARTBEAT_URL'))` → push monitor Kuma | G3 | app (1 linha) | PR + [W] |
| 7 | `backup:run` → `thenPing` do push monitor de backup | G5 | app (1 linha) | PR + [W] |
| 8 | Ligar `sentry-laravel` → GlitchTip (`Integration::captureUnhandledException` no `Handler`, `before_send` anti-PII, DSN vazio = no-op) | G4 | app + `.env` | PR + canary 24h |
| 9 | `jana:health-check --notify` mandar pro canal (além do log) | G1 | app | PR |
| 10 | Checks novos: `failed_jobs_24h`, `oldest_pending_job_age`, `default_queue_worker_off_with_backlog` | G6 | app | PR |
| 11 | Rota `/up` read-only (DB ping, cache, disco, idade da fila, último heartbeat) | G7 | app | PR + canary |
| 12 | GlitchTip performance com sample baixo nas rotas críticas → P95 por transação | G8 | `.env` | [W] flip |
| 13 | Slow query no app: `DB::whenQueryingForLongerThan(500)` → canal `slow-query` (SQL sem bindings) | G9 | app | PR + canary |
| 14 | Escritor de `mcp_usage_diaria` + tag `module` no driver de IA | G11 | app | PR |
| 15 | Limpeza: arquivar regras Prometheus Baileys, desligar/remover `aggregate-daily` órfão, decidir Telescope | G14 | app/infra | PR |

G12 (LGPD Fisio/Associações) e G13 (multi-tenant runtime) ficam **fora** deste plano: precisam do dono do módulo e de ADR, respectivamente.

## 7. Dashboards (proposta, não criados)

- **Health:** status page do Uptime Kuma (`status.oimpresso.com`) + painel GlitchTip (erros 24h, P95 das 3 rotas) + bloco do `jana:health-check` (25 checks). O `Modules/Governance` DashboardController já lê `failed_jobs` — reaproveitar em vez de dashboard novo.
- **Negócio:** métricas por cliente ainda a definir com [W]. Ponto de partida: biz=4 ROTA LIVRE — vendas/dia, ticket médio, títulos vencidos; Fisio — atendimentos/semana.
- **Uniafibro:** deixa de ser só site estático e vira a primeira empresa do `Modules/Associacoes` ([ADR 0451](../../decisions/0451-associacoes-rede-uniafibro-area-de-membros.md), proposta; ondas 2–6 em PR aberto). As métricas de negócio dela já nascem no **Painel da rede** (`/associacoes`, onda 5): associações ativas, associadas ativas e novas por mês por associação, consentimentos revogados, pendências de ata atrasadas (D7, 30 dias) e acessos à área da associada no app (D6). **Não** criar dashboard paralelo — reaproveitar esse painel.
- **Observabilidade que o módulo de Associações precisa** (para entrar no plano depois da onda 6): monitor de uptime da rota pública da área da associada; contador de PIN errado / tokens travados (abuso); e **log de leitura** de dado da associada (quem viu o cadastro e quando — dado de saúde, LGPD art. 11/37), que nas ondas 2–6 ainda não existe (só há filtro de alcance e rastro de revogação do token).

## 8. Referências

- Auditoria ops/DR: [AUDITORIA-OPS-DR-2026-07.md](AUDITORIA-OPS-DR-2026-07.md)
- [RUNBOOK-otel-collector-ct100.md](RUNBOOK-otel-collector-ct100.md) · [RUNBOOK-langfuse-operacional.md](RUNBOOK-langfuse-operacional.md) · [RUNBOOK-validacao-pos-deploy.md](RUNBOOK-validacao-pos-deploy.md) · [RUNBOOK-route-hits.md](RUNBOOK-route-hits.md)
- ADR 0162 (OTel), 0132 (Langfuse), 0429 (GlitchTip, proposto), 0093 (multi-tenant Tier 0)
- Session log: [2026-11-14-auditoria-observabilidade.md](../../sessions/2026-11-14-auditoria-observabilidade.md)
