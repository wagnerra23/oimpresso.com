# Especificação funcional — Infra (loop de governança fechado)

> **Convenção do ID:** `US-INFRA-NNN` para user stories de infra.
> **Origem:** [ADR 0105 cliente como sinal](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) + Wagner 2026-05-08 pediu fechar loop META → SINAL → DESVIO → RECÁLCULO → HITL.
> **Estimates:** recalibradas por [ADR 0106](../../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md) — fator 10x em codáveis + margem 2x; humanos mantém relógio.

## 1. Glossário

- **Loop de governança** — META → TRACKING → SINAL → DESVIO → RECÁLCULO → HITL → EXECUÇÃO → MEDIÇÃO → loop
- **Client signal** — sinal estruturado vindo do cliente (não-WhatsApp ad-hoc)
- **APM** — Application Performance Monitoring (Sentry, Bugsnag, ou self-hosted GlitchTip)
- **Feature flag system** — GrowthBook self-hosted no CT 100, percentage rollout + segmentação
- **CT 100** — container Proxmox que hospeda daemons (Centrifugo, Meilisearch, MCP, agora GrowthBook)

## 2. User stories

### US-INFRA-001 · GrowthBook self-hosted (feature flag system)

> owner: wagner · priority: p0 · estimate: 1.5h · status: todo · type: story · origin: gap-analysis-2026-05-08
> blocked_by: —

**Contexto.** Hoje feature flags são improvisadas em `pos_settings` JSON (ex: `useV2SellsCreate=true`). Funciona pra 1-2 features, vira pesadelo em 10. Sem percentage rollout (ex: "ativa pra 10% dos sells primeiro"), o canary é binary — todos ou nenhum. GrowthBook dá: percentage rollout, segmentação user/biz, audit trail completo, A/B testing nativo, SDK PHP + JS oficiais.

**Escopo:**
- [ ] Container Docker GrowthBook OSS no CT 100 via compose-managed (não Portainer Stacks — auto-mem `reference_proxmox_acesso_2026_04_29`)
- [ ] Mongo OU Postgres como backend (GrowthBook usa Mongo por default; usar postgres se simplifica)
- [ ] Traefik labels: `growthbook.oimpresso.com` (admin UI) + `growthbook-api.oimpresso.com` (SDK endpoint)
- [ ] Volumes persistentes em `/var/lib/docker/volumes/growthbook-*` com backup
- [ ] SDK PHP no Hostinger: `composer require growthbook/growthbook` (~50KB, sem deps pesadas)
- [ ] Service `App\Services\FeatureFlagService` com cache Redis 60s
- [ ] Migration: marcar features atuais (`useV2SellsCreate`) como flags GrowthBook + manter `pos_settings` JSON como fallback durante transição
- [ ] Runbook deploy em [memory/requisitos/Infra/RUNBOOK-growthbook-deploy.md](RUNBOOK-growthbook-deploy.md)

**Acceptance criteria:**
- [ ] `growthbook.oimpresso.com` carrega UI admin (auth via SSO ou local)
- [ ] Toggle "useV2SellsCreate" pode ser feito por biz ou percentage (ex: 10% sells, 100% biz=1)
- [ ] PHP service reads flag em <50ms (cache hit) / <300ms (cache miss)
- [ ] Audit trail visível: quem ligou, quando, qual segmentação
- [ ] Rollback: desativar flag em <10s sem deploy
- [ ] Docker compose vive em `~/docker/growthbook/` no CT 100, gitignored

**Refs:** [ADR 0105 §loop fechado](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md), [ADR 0106 estimate recalibrado](../../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md), [GrowthBook docs](https://docs.growthbook.io/self-host)

### US-INFRA-002 · Client Signal — entidade + canal estruturado

> owner: wagner · priority: p1 · estimate: 1h · status: todo · type: story · origin: adr-0105
> blocked_by: US-INFRA-001

**Contexto.** ADR 0105 estabelece "cliente como sinal" como princípio canônico. Hoje Larissa (ROTA LIVRE biz=4) reporta dor via WhatsApp pra Wagner — sinal não rastreável, vira backlog mental. Esta US formaliza: tabela `client_signals` no MCP, URL pública `/feedback?biz=X&token=Y` simplificada, ADS triage (mesmo manual em 2026-Q2), virar US automática se passar threshold.

**Escopo:**
- [ ] Migration `mcp_client_signals` no MCP DB: id, business_id, reporter_name, signal_text, severity_self_reported, url_seen, browser_console_dump, screenshot_url, status (pending/triaged/closed), triaged_to_us_id, created_at
- [ ] Form simples Inertia em `Pages/Feedback/Form.tsx` (sem login — token único por biz expiraja em 30d)
- [ ] Endpoint `POST /api/feedback` valida token + grava signal + dispara webhook MCP
- [ ] Tool MCP `client-signals-list` + `client-signals-triage` (mesmo workflow das tasks)
- [ ] Brief diário inclui: "client_signals 24h: N pendentes" — entra junto das outras métricas
- [ ] Quando ADS S5 chegar (ADR 0106 antecipa pra ~30/maio): triage automática vira US se confidence ≥ HIGH
- [ ] Manual hoje: Wagner/Maíra fazem `triage` no `my-inbox` semanalmente

**Acceptance criteria:**
- [ ] Larissa abre URL `oimpresso.com/feedback?token=ROTA_LIVRE_2026` → vê form simples 1 campo
- [ ] Submit → row em `mcp_client_signals` + Wagner vê em `my-inbox`
- [ ] Brief mostra contagem 24h
- [ ] Tool MCP `client-signals-triage` permite anotar + virar US-X-NNN

**Refs:** [ADR 0105 §princípio 1](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md), [skill ads-decision-flow](../../../.claude/skills/ads-decision-flow/SKILL.md)

### US-INFRA-003 · APM full-stack — captura "lento aqui" automaticamente

> owner: wagner · priority: p1 · estimate: 2h · status: todo · type: story · origin: adr-0105
> blocked_by: US-INFRA-001

**Contexto.** ADR 0105 §princípio 1 nota: "cliente sabe ONDE dói, raramente sabe POR QUÊ". OTEL gen_ai já trackea LLM. Falta APM completo capturando: erros JS no browser da Larissa, performance front-end (LCP/INP/CLS), traces Laravel (slow query, N+1), session replay opcional. Hoje vira "Larissa fala que travou" → impossível reproduzir.

**Escopo:**
- [ ] Avaliar GlitchTip OSS (Sentry-compat self-hosted) vs Sentry SaaS (~$26/mo team)
- [ ] Subir GlitchTip no CT 100 via docker compose (Postgres + Redis já existem)
- [ ] Traefik label `apm.oimpresso.com`
- [ ] SDK Laravel `sentry/sentry-laravel` (compat GlitchTip) — capture exceptions + slow query
- [ ] SDK JS `@sentry/react` em `app.tsx` Inertia — capture JS errors + performance metrics
- [ ] Source maps upload no `npm run build:inertia` (release tracking)
- [ ] Quando erro grave em biz=4 (ROTA LIVRE) → criar `client_signal` automático (handoff com US-INFRA-002)
- [ ] PII redaction: SDK config NUNCA envia request body (LGPD)

**Acceptance criteria:**
- [ ] Erro JS no browser da Larissa aparece em `apm.oimpresso.com` com stack trace + URL
- [ ] Slow query >2s no Hostinger → trace visível
- [ ] Brief diário inclui "apm errors 24h: N" com link pra investigação
- [ ] Zero PII em logs (CPF/CNPJ redacted antes de enviar)

**Refs:** [ADR 0093 multi-tenant Tier 0](../../decisions/0093-multi-tenant-isolation-tier-0.md) (PII redaction), [ADR 0094 §observabilidade](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md)

### US-INFRA-004 · Detecção automática de desvio (cron diário)

> owner: wagner · priority: p2 · estimate: 0.5h · status: todo · type: story · origin: adr-0105
> blocked_by: US-INFRA-002

**Contexto.** Loop fechado precisa de detecção sem dev olhar dashboard. Cron diário 06:00 BRT (junto com brief) compara `cycle_goal.target` vs `cycle_goal.atual`. Se delta > 20%, gera `client_signal` interno (severity `internal_drift`). Vira input do mesmo loop.

**Escopo:**
- [ ] Comando artisan `governance:detect-drift` (nome em PT-BR ou EN — Wagner decide)
- [ ] Schedule daily 06:00 BRT em `app/Console/Kernel.php` (junto com brief:generate)
- [ ] Lógica: SELECT cycle_goals ativos, calcula delta, threshold ADR-configurável (default 20%)
- [ ] Se drift detectado → cria `client_signal` row com `reporter_name='SYSTEM'` + signal_text descritivo
- [ ] Brief inclui: "drift_detections 24h: N"
- [ ] Pest test mockando cycle_goal abaixo do threshold + verifica criação de signal

**Acceptance criteria:**
- [ ] Cycle goal com drift > 20% gera signal automático em <24h
- [ ] Wagner vê em `my-inbox` filtrado por `reporter=SYSTEM`
- [ ] Pest test passa

**Refs:** [ADR 0091 brief diário](../../decisions/0091-daily-brief.md), [ADR 0094 loop fechado](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md)

### US-INFRA-005 · S5 ADS adiantado (Risk + Confidence + Policy core)

> owner: wagner · priority: p1 · estimate: 12h · status: todo · type: epic · origin: adr-0106-recalibracao
> blocked_by: US-INFRA-002

**Contexto.** ADS Universal previsto pra jul/2026 (skill `ads-decision-flow` lista S5). [ADR 0106](../../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md) recalibra: ~80h antigos → ~12h reais. Nova janela ~30 maio/2026. Adiantar pra **viabilizar US-INFRA-002 triage automática** ainda em 2026-Q2.

**Escopo (núcleo apenas — features avançadas ficam pra depois):**
- [ ] `ads:decide(domain, intent, payload)` retorna `ALLOW_BRAIN_A | REQUIRE_BRAIN_B | REQUIRE_HUMAN_REVIEW | BLOCK_ALWAYS`
- [ ] RiskEvaluator: heurística simples (biz=1 = LOW, biz=4 ROTA LIVRE = HIGH, sem auth = BLOCK)
- [ ] ConfidenceScorer: Brain A retorna 0-100; threshold 70 = passa, abaixo = escala
- [ ] PolicyEngine: matriz Risk × Confidence → outcome
- [ ] Wire em `client_signal_triage` (US-INFRA-002): signal entra → ADS decide se vira US auto ou HITL Wagner
- [ ] Pest tests cobrindo 4 outcomes
- [ ] Tela `/ads/admin/decisions` lista decisões + outcomes (já existe stub no Modules/ADS — só plugar)

**Acceptance criteria:**
- [ ] Signal em biz=1 com confidence ≥70 vira US-X-NNN automática
- [ ] Signal em biz=4 (ROTA LIVRE) sempre escala HITL Wagner (multi-tenant Tier 0)
- [ ] Tela admin mostra decisões + Wagner pode override
- [ ] Pest tests passam

**Refs:** [skill ads-decision-flow](../../../.claude/skills/ads-decision-flow/SKILL.md), [ADR 0106 antecipação](../../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md)

## 3. Sequência recomendada

```
1. US-INFRA-001 (GrowthBook ~1.5h)            ← faz hoje/amanhã
2. US-INFRA-002 (Client Signal entity ~1h)    ← faz junto
3. US-INFRA-003 (APM ~2h)                      ← próxima sessão
4. US-INFRA-004 (Drift detection ~0.5h)        ← rápido, encadeia
5. US-INFRA-005 (S5 ADS antecipado ~12h)       ← 1-2 semanas, núcleo apenas

Total: ~17h codáveis + canary 7d humano
       ≈ 2 semanas calendário
```

## 4. Como cada US fecha o loop

| US | Pedaço do loop fechado |
|---|---|
| US-INFRA-001 GrowthBook | EXECUÇÃO — atuador do canary com percentage |
| US-INFRA-002 Client Signal | SINAL — entrada estruturada do cliente |
| US-INFRA-003 APM | SINAL — entrada automática (sintoma + causa) |
| US-INFRA-004 Drift detection | DETECÇÃO DE DESVIO — automática |
| US-INFRA-005 S5 ADS | TRIAGEM + RECÁLCULO — decisão automática com HITL |

Quando os 5 fecharem, oimpresso opera com **loop de governança fechado autossustentável** — Wagner vira validador estratégico, não bottleneck operacional.

---

**Última atualização:** 2026-05-08
