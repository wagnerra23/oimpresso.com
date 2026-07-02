---
slug: 0132-langfuse-self-host-ct100
number: 132
title: "Langfuse self-host CT 100 — observabilidade GenAI canônica oimpresso"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-05-10"
module: Infra
tags: [infra, observabilidade, otel, langfuse, ct100, proxmox, jana, custo-genai]
supersedes: []
supersedes_partially: []
amends: []
superseded_by: []
related: [0035-stack-ai-canonica-wagner-2026-04-26, 0051-schema-proprio-adapter-otel-genai, 0053-mcp-server-governanca-como-produto, 0058-reverb-substituido-por-centrifugo-frankenphp, 0062-separacao-runtime-hostinger-ct100, 0067-sprint8-mcp-memory-document-searchable-retrieval, 0094-constituicao-v2-7-camadas-8-principios]
pii: false
review_triggers:
  - "Langfuse OSS v4 sair com breaking change → reavaliar self-host vs Cloud paid"
  - "Custo IA mensal Jana >R$ [redacted Tier 0]/mês → Langfuse Cloud paid pode valer (~$59/mês start) pra deixar de manter stack"
  - "CT 100 RAM atingir 80% sustentado com Langfuse + outras stacks → migrar Langfuse pra CT separado ou usar Cloud"
  - "Helicone OSS atingir paridade de feature com Langfuse + lighter footprint → reavaliar"
  - "Anthropic Console expor dashboard team-level com custo per-tenant nativo → considerar substituir Langfuse pra esse subset"
  - "≥1 incidente de span perdido por exporter HTTP fail >5% → endurecer retry (job assíncrono) ou trocar transport"
---

# ADR 0132 — Langfuse self-host CT 100 (observabilidade GenAI canônica)

## Contexto

[ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md) princípio 4 estabelece "loop fechado por métrica". O Jana IA (Modules/Jana) hoje:

- **Já emite spans OTel GenAI** semantic conventions (`gen_ai.*`) em `LaravelAiSdkDriver.php:362` via `Log::channel('otel-gen-ai')` — definido pela [ADR 0051](0051-schema-proprio-adapter-otel-genai.md)
- **Grava apenas em arquivo local** `storage/logs/otel-gen-ai.log` — sem exporter, sem dashboard, sem agregação
- **Tem schema próprio** (ADR 0051) que mapeia direto pras semantic conventions OTel — pronto pra qualquer backend OTel-compatible

Audit 2026-05-10 (sessão Wagner pós-ADRs 0130/0131) detectou que o gap real de observabilidade IA do projeto **não é construir telemetria** (já existe), e sim **escolher e provisionar o backend** que consome os spans.

Backlog COPI-44 P1 ("Langfuse self-host CT 100 + OTEL no LaravelAiSdkDriver") existia há semanas como ticket vago. Esta ADR formaliza a decisão + US-INFRA-016 substitui COPI-44 com escopo refinado.

### Por que Langfuse, não alternativas

| Opção | Veredicto | Razão |
|---|---|---|
| **Langfuse OSS self-host v3** | ✅ ESCOLHIDA | Receiver OTLP nativo; UI dashboard pronto; agnostic ao provider (Anthropic/OpenAI/Ollama); self-host preserva LGPD (PII Larissa não sai do Brasil); MIT license; ratings altos em produção 2025-26 (Anthropic Cookbook + Cursor mencionam Langfuse) |
| **Langfuse Cloud paid** | ❌ Adiada (review_trigger) | $59/mês entry + dados em US/EU. Self-host primeiro; migrar se custo manter > pagar (review_trigger se custo IA > R$ [redacted Tier 0]/mês) |
| **Helicone** | ❌ | Proxy-based (entra como middleware HTTP) — exige reescrever LaravelAiSdkDriver pra apontar pro proxy. Schema OTel ADR 0051 perdido. Perdeu tração 2025-26 vs first-party SDK approach |
| **LangSmith (LangChain)** | ❌ | Amarrado a LangChain ecosystem. oimpresso usa laravel/ai SDK ([ADR 0035](0035-stack-ai-canonica-wagner-2026-04-26.md)), não LangChain |
| **Custom dashboard** (Grafana + Loki + tempo) | ❌ | Construir + manter dashboard de zero custa 5-10x mais que adotar Langfuse. Faz sentido só >R$ [redacted Tier 0]k/mês custo IA — não é o caso (atual ~R$ [redacted Tier 0]-100/mês) |
| **Helicone OSS** | ❌ | Bom mas adicional stack (proxy) vs Langfuse (drop-in OTLP receiver) — Langfuse vence em simplicidade pro tier de uso atual |

### Por que CT 100, não Hostinger

[ADR 0062](0062-separacao-runtime-hostinger-ct100.md) já decidiu: daemons só em CT 100 Proxmox. Langfuse v3 precisa:

- **Postgres** (metadata: orgs, users, prompts, projects)
- **ClickHouse** (analytics OLAP — spans/traces)
- **Langfuse Web** (Next.js UI + API + OTLP receiver)
- **Langfuse Worker** (async processing)

4 daemons = violação clara do contrato Hostinger shared hosting. Mesma família de [ADR 0053](0053-mcp-server-governanca-como-produto.md) (MCP server em CT 100) e [ADR 0058](0058-reverb-substituido-por-centrifugo-frankenphp.md) (Centrifugo + FrankenPHP em CT 100).

## Decisão

### 1. Stack Langfuse v3 em CT 100 docker-host (192.168.0.50)

Compose stack em `docker/langfuse/docker-compose.yml` (commitada nesta PR) com 4 services:

- `postgres-langfuse` (postgres:16-alpine) — metadata
- `clickhouse-langfuse` (clickhouse:24.3-alpine) — spans storage
- `langfuse-web` (langfuse/langfuse:3) — UI + OTLP receiver
- `langfuse-worker` (langfuse/langfuse-worker:3) — async jobs

Volumes persistentes em `/opt/langfuse/data/` (postgres + clickhouse separados).

Network: `docker-host_default` (external — mesma rede do Traefik global e demais stacks).

### 2. Subdomínio canônico `langfuse.oimpresso.com`

Traefik labels no `langfuse-web` (mesmo padrão de `mcp.oimpresso.com` — [ADR 0053](0053-mcp-server-governanca-como-produto.md)):
- HTTPS via Let's Encrypt R12 (cert resolver `le`)
- HTTP → HTTPS redirect via middleware `langfuse-redirect`
- Healthcheck path `/api/public/health`

DNS: criar A record `langfuse` → `177.74.67.30` (IP público da empresa, mesmo do `mcp.oimpresso.com`).

### 3. Recursos esperados no CT 100

| Service | RAM | Disk | CPU |
|---|---|---|---|
| postgres-langfuse | ~200MB | ~5GB (cresce ~100MB/mês) | <0.1 |
| clickhouse-langfuse | ~1.5-2GB | ~20GB (retention 30d) | ~0.3 |
| langfuse-web | ~300MB | desprezível | <0.1 |
| langfuse-worker | ~200MB | desprezível | <0.1 |
| **TOTAL** | **~2.5GB** | **~25GB** | **~0.5-1 core** |

**Wagner valida capacidade CT 100 antes do PR-2 da US-INFRA-016** (`free -h && df -h /opt`). Se RAM total CT <8GB, considerar adicionar 2GB ao CT antes do deploy.

### 4. Secrets em Vaultwarden (não no git)

Item Vaultwarden `langfuse-ct100` armazena:
- `POSTGRES_PASSWORD` (openssl rand -base64 32)
- `CLICKHOUSE_PASSWORD` (openssl rand -base64 32)
- `NEXTAUTH_SECRET` (openssl rand -base64 32)
- `SALT` (openssl rand -base64 32)
- `ENCRYPTION_KEY` (openssl rand -hex 32) — **NUNCA muda depois (perdeu = perdeu keys API criptografadas)**

Após deploy, item Vaultwarden `langfuse-keys` armazena Public/Secret Keys gerados no UI Langfuse:
- `LANGFUSE_PUBLIC_KEY` (`pk-lf-*`) — vai pro Hostinger `.env`
- `LANGFUSE_SECRET_KEY` (`sk-lf-*`) — vai pro Hostinger `.env`

Conforme [ADR 0131](0131-tiering-memoria-canonico-local-segredo.md) (tiering de memória — segredos em Vaultwarden).

### 5. Wire no Laravel via PR-1 da US-INFRA-016

Custom log handler `app/Logging/OtlpHttpHandler.php` POSTs payload em `https://langfuse.oimpresso.com/api/public/otel/v1/traces` com Bearer token via env. Driver `daily` do channel `otel-gen-ai` é trocado por esse handler — **zero mudança em `emitirOtelGenAi`** (encapsulamento preservado).

Fallback: se HTTP fail, grava local + retry job assíncrono via Horizon (princípio 8 Constituição v2 — confiabilidade com fallback).

### 6. Permissões UI

- `Wagner` cria conta admin primeira vez → `AUTH_DISABLE_SIGNUP=false` (default)
- Após todos do time entrarem (Felipe, Maiara, Luiz, Eliana) → editar `.env` Langfuse `AUTH_DISABLE_SIGNUP=true` + restart
- View read-only via perm Spatie `copiloto.observability.read` (PR-4 US-INFRA-016 cria link `/copiloto/admin/observability` que abre Langfuse com SSO ou simples link out)

### 7. Backup

Volumes `/opt/langfuse/data/postgres` (~100MB compacted/semana) entram no plano de backup nightly do CT 100. ClickHouse data NÃO precisa backup obrigatório (perda = perda de histórico de spans, tolerável vs reconstruir — princípio 8).

## Não-decidido (fora de escopo)

- **Redis pra Langfuse queue** — Langfuse suporta opcionalmente. MVP usa in-memory; adicionar Redis só se workload exceder. Review trigger: latência ingestion >5s sustentado.
- **Cluster multi-node Langfuse** — desnecessário pro tier atual (<10k spans/dia). Single-node v3 cabe folgado.
- **Authentication OAuth/SAML** — Langfuse suporta mas overkill pro time 5 pessoas. Email/senha simples + AUTH_DISABLE_SIGNUP=true após onboarding.
- **Integração custom Slack/Discord** pra alertas — vem depois. Langfuse v3 tem alertas no UI; integração externa é P3.

## Consequências

### Positivas

- **Loop fechado por métrica ativado** (princípio 4 Constituição v2) — Jana finalmente tem dashboard de custo + latência + erro
- **OTel ADR 0051 desbloqueada** — schema próprio já mapeado pra OTel pode finalmente ir pra backend real
- **LGPD preservado** — dados Larissa não saem do Brasil (CT 100 BR-located)
- **Custo trivial** — só RAM/disk do CT 100 que já é pago. Cloud paid Langfuse custaria $59-200/mês conforme uso
- **Substitui task vaga (COPI-44)** por US-INFRA-016 com PR scope claro
- **Pavimenta evals automáticos (US-COPI-105)** — RAGAS gate em CI pode postar resultado pro Langfuse pra trend histórico
- **Anthropic-aligned** — Anthropic Cookbook + early adopters Claude API maduras (Cursor, Replit, Vercel AI) convergiram em Langfuse + OTel GenAI semantic conventions

### Negativas

- **2.5GB RAM permanente CT 100** — não trivial. Se CT 100 tá com <4GB livre, adicionar antes
- **25GB disk reservado** — gerenciável. Retention 30d default cobre auditoria razoável; reduzir pra 14d se apertar
- **Stack a manter** — 4 containers, releases Langfuse mensais, migration ClickHouse ocasional. Mitigado por `docker compose pull && up -d` simples
- **Single point of failure** — se CT 100 cai, perde observabilidade. Aceitável: fallback é arquivo local (princípio 8). Spans recuperados após CT volta

### Neutras

- **Convergência com tendência 2026** — Langfuse + OTel GenAI virou de facto pro tier Claude API self-host startup. Não é aposta, é seguir consenso

## Plano de implementação

PRs da US-INFRA-016 (não nesta PR de ADR):

1. **PR-1** — `app/Logging/OtlpHttpHandler.php` exporter HTTP + 3 Pest (success/retry/fallback)
2. **PR-2** — Provisionar Langfuse no CT 100 + DNS subdomain + Traefik route (escopo `docker/langfuse/` desta PR + deploy real Wagner-approved)
3. **PR-3** — Atributos extras no span (`cache.hit`, `cost.usd`, `memoria.recall_hit_count`)
4. **PR-4** — Dashboard saved view `/copiloto/admin/observability` + perm Spatie

Estimate total: 1.5 sprint (PR-1+PR-2 paralelos, PR-3+PR-4 sequencial).

## Referências

- [ADR 0035](0035-stack-ai-canonica-wagner-2026-04-26.md) — Stack IA canônica (laravel/ai SDK)
- [ADR 0051](0051-schema-proprio-adapter-otel-genai.md) — Schema próprio OTel GenAI (já emitindo)
- [ADR 0053](0053-mcp-server-governanca-como-produto.md) — MCP server CT 100 (mesmo padrão de stack)
- [ADR 0058](0058-reverb-substituido-por-centrifugo-frankenphp.md) — Centrifugo + FrankenPHP CT 100
- [ADR 0062](0062-separacao-runtime-hostinger-ct100.md) — Separação Hostinger ≠ CT 100 (proibição daemons Hostinger)
- [ADR 0067](0067-ai-adoption-roadmap-sprints-7-9.md) — Roadmap IA Sprints 7-9 (referencia Langfuse no horizonte)
- [ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2 (princípio 4 + princípio 8)
- [ADR 0131](0131-tiering-memoria-canonico-local-segredo.md) — Tiering memória (secrets em Vaultwarden)
- US-INFRA-016 — task tracking PR-1..PR-4
- [docker/langfuse/](../../docker/langfuse/) — stack proposal commitada
- [Langfuse docs](https://langfuse.com/docs/deployment/self-host) — referência externa
