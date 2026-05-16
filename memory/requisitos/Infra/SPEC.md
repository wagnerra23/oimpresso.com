---
module: Infra
na_justified:
  D5: "Infra é loop de governança fechado (META → SINAL → DESVIO → RECÁLCULO — ADR 0105) servindo o projeto inteiro, NÃO módulo de features cliente. Não há biz=4 ROTA LIVRE consumindo GrowthBook/APM/MCP server diretamente — são fundações da plataforma. D5 cliente real não aplica por design."
  D4.b: "Infra não tem state machine FSM (ADR 0143). Concentra runbooks operacionais (deploy Centrifugo, Hostinger, CT 100, GrowthBook) e SPECs de infra — sem Eloquent Models com transições. D4.b FSM canônica N/A."
na_justified_v3:
  D6.a: "Infra é orquestração de runbooks + ADRs operacionais — sem Controllers Inertia::render. Inertia::defer N/A por ausência de telas geradas pelo módulo (US-INFRA-* viram features em outros módulos como Governance/Brief/Admin)."
related_adrs: [0105, 0106, 0153, 0154, 0155, 0156]
---

# Especificação funcional — Infra (loop de governança fechado)

> **N/A justificado** D5 + D4.b + D6.a — loop de governança da plataforma (sem cliente direto, sem FSM, sem Controllers Inertia). Runbooks/ADRs operacionais materializam features em outros módulos (Governance/Brief/Admin).

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

### US-INFRA-006 · Tool MCP `whats-active` — agregar sessões doing + paths tocados (Tier 1 ADR 0119)

> owner: wagner · priority: p2 · estimate: 4h · status: todo · type: story · origin: adr-0119-paralelismo-sessoes

**Contexto.** [ADR 0119](../../decisions/0119-paralelismo-sessoes-whats-active-tier-1.md) aceitou Tier 1 (`whats-active` read-only) após estudo de 13 incidentes de paralelismo. Padrão real: ofensores são Cursor (4×) e workflows GitHub Actions (3×) — ambos não consultam MCP. Caso "Claude-A vs Claude-B mesmo arquivo" não tem incidente catalogado, mas tool barata (sem estado novo) cobre a única classe não mitigada hoje.

**Escopo:**
- [ ] Endpoint MCP `whats-active` retorna JSON `[{owner, task_id, worktree_path, last_commit_at, paths_touched_24h:[...]}]`
- [ ] Filtro: só sessões com last_event nas últimas 2h
- [ ] Filtro: só tasks status:doing
- [ ] Zero estado novo no DB — agregação derivada (query JOIN `mcp_session_logs` × `tasks`)
- [ ] Pest test biz=1 cobre: 2 sessões diferentes, 1 com paths overlapping → resposta lista ambas
- [ ] Token MCP autenticado — qualquer dev do time vê todas sessões (transparência ADR 0094)

**Dependência:** watcher cc-search já em prod (auto-mem `MEM-CC-1`).

**Não-objetivos:**
- ❌ Lock formal (Tier 2 dormente — só promove se 2× incidentes Claude-A vs Claude-B no mesmo arquivo)
- ❌ Bloquear sessão (alerta passivo — cultura, não enforcement)
- ❌ Integração com Cursor (fora de escopo MCP — convenção humana)

**Acceptance criteria:**
- [ ] Tool retorna 200 com lista vazia quando ninguém ativo
- [ ] Pest test 2 sessões overlapping passa
- [ ] Doc adicionada nesta SPEC + skill `mcp-first` cita a tool

**Refs:** [ADR 0119](../../decisions/0119-paralelismo-sessoes-whats-active-tier-1.md)

### US-INFRA-007 · Skill Tier A `session-start-check` — alertar paths overlapping (ADR 0119)

> owner: wagner · priority: p2 · estimate: 2h · status: todo · type: story · origin: adr-0119-paralelismo-sessoes
> blocked_by: US-INFRA-006

**Contexto.** Companion da US-INFRA-006. Skill Tier A always-on que roda no hook SessionStart depois do `brief-first`. Chama `whats-active` e alerta passivo se outra sessão Claude tocou os mesmos paths nas últimas 2h. Sem overlap → silencioso (não polui contexto).

**Escopo:**
- [ ] `.claude/skills/session-start-check/SKILL.md` criado com tier A frontmatter
- [ ] Hook SessionStart chama `whats-active` após `brief-fetch`
- [ ] Alerta passivo (não bloqueia): "⚠️ Felipe trabalhou em `Modules/NfeBrasil/Services/` há 1h — confirmar antes de começar"
- [ ] Heurística overlap: paths_touched_24h da outra sessão ∩ tasks_em_curso minha (via `my-work`)
- [ ] Doc em `memory/sprints/s3-constituicao/03-skills-audit.md` atualizada com nova Tier A
- [ ] ADR 0119 referenciada no SKILL.md description

**Acceptance criteria:**
- [ ] 2 sessões com overlapping detectado → alerta gerado
- [ ] 2 sessões sem overlap → contexto silencioso
- [ ] Skill aparece no `tier-a` listing do `03-skills-audit.md`

**Não-objetivos:**
- ❌ Bloquear sessão (cultura, não enforcement)
- ❌ Resolver merge conflict (apenas avisar)

**Refs:** [ADR 0119](../../decisions/0119-paralelismo-sessoes-whats-active-tier-1.md), depende de US-INFRA-006

### US-INFRA-008 · Feature Flag Control (3 canais: Artisan/MCP/Painel)

> owner: wagner · priority: p1 · estimate: 4h · status: in-progress · type: story · origin: emergencia-rollback-sells-v2-2026-05-13
> blocked_by: US-INFRA-001

**Contexto.** US-INFRA-001 entregou GrowthBook self-hosted + `FeatureFlagService` (leitura). Falta escrita: toggle de regra por business_id, mata-switch de environment, limpar cache. Único caminho hoje é abrir manualmente `growthbook.oimpresso.com` e clicar no painel UI — fricção alta sem audit nosso, sem integração com Claude no chat. Disparado por emergência rollback Sells v2 biz=4 (Larissa/ROTA LIVRE) 2026-05-13 — toggle manual no painel custou ~60s de fricção que com tool MCP cai pra ~5s.

**Escopo:**
- [x] `App\Services\GrowthBookAdminService` — wrapper REST API (list/get/setBizRule/removeBizRule/setEnvEnabled)
- [x] Migration `feature_flag_audits` append-only + Model `FeatureFlagAudit`
- [x] Artisan: `flag:list` · `flag:get` · `flag:set` · `flag:env-toggle` · `flag:cache-clear`
- [x] Tools MCP: `flag-list` · `flag-get` · `flag-set` · `flag-env-toggle` · `flag-cache-clear` (registradas em `OimpressoMcpServer`)
- [x] Painel Inertia `/admin/feature-flags` (Index + Show) sob middleware `tailscale-only -> auth -> is-wagner`
- [x] Tests Pest cobrindo Service + Tools MCP + Controller
- [x] RUNBOOK em [memory/requisitos/Infra/RUNBOOK-feature-flag-control.md](RUNBOOK-feature-flag-control.md)
- [x] `.env.example` documenta `GROWTHBOOK_ADMIN_API_HOST` + `GROWTHBOOK_ADMIN_API_TOKEN`
- [ ] **Pré-req runtime:** Wagner gera Personal Access Token em `growthbook.oimpresso.com` → Settings → PAT, guarda Vaultwarden + .env Hostinger + .env CT 100

**Acceptance criteria:**
- [ ] `php artisan flag:set useV2SellsCreate --biz=4 --enabled=false --clear-cache` desliga em ≤5s
- [ ] Tool MCP `flag-set` no chat funciona idêntico
- [ ] Painel `/admin/feature-flags` lista + permite editar rule biz-{N} sem deploy
- [ ] Toda mudança grava 1 linha em `feature_flag_audits` (audit append-only)
- [ ] HTTP 401 (token inválido) gera mensagem clara em todos os 3 canais
- [ ] Sem token configurado, todos os 3 canais retornam "não configurado" — fail-safe

**Não-objetivos:**
- ❌ Substituir o painel oficial GrowthBook (mantém pra rules complexas multi-condição)
- ❌ Editar features de outros projetos GrowthBook além do `production` default
- ❌ Permissões granulares por feature (Wagner-only via `is-wagner` middleware)

**Refs:** US-INFRA-001 (GrowthBook self-hosted), [ADR 0094 §princípio 7 transparência](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md), [ADR 0122 Admin Center CT 100](../../decisions/0122-admin-center-ct100.md), [ADR 0131 tiering memória](../../decisions/0131-tiering-memoria-canonico-local-segredo.md)

### US-INFRA-009 · Artisan command `feature:activate` via GrowthBook API ⚠️ **SUPERSEDED por US-INFRA-008**

> owner: wagner · priority: p2 · estimate: 3h · status: superseded · type: story
> superseded_by: US-INFRA-008
> blocked_by: US-INFRA-001

**Status (2026-05-13):** Substituída por US-INFRA-008, que entrega **superset** (5 commands artisan + 5 tools MCP + painel Inertia, todos sobre `GrowthBookAdminService`). Mantida aqui como histórico — a entry foi adicionada via PR #811 em paralelo à implementação de US-INFRA-008 (#818). Comandos equivalentes:

| US-INFRA-009 (plano) | US-INFRA-008 (entregue) |
|---|---|
| `php artisan feature:activate {flag} {biz}` | `php artisan flag:set {flag} --biz={biz} --enabled=true` |
| `php artisan feature:deactivate {flag} {biz}` | `php artisan flag:set {flag} --biz={biz} --enabled=false` ou `--remove` |
| `GROWTHBOOK_API_TOKEN` | `GROWTHBOOK_ADMIN_API_TOKEN` (mesmo conceito, nome mais explícito) |

**Contexto original.** Hoje ativar uma feature flag para um biz_id exige 15 cliques manuais no GrowthBook UI (Add Rule → Targeted release → preencher condição → Save Draft → Review & Publish). Automatizar via artisan command.

**Refs:** US-INFRA-008 (substituta), [ADR 0106](../../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md)

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
| US-INFRA-006 whats-active | COORDENAÇÃO — sinal "quem mexe em quê AGORA" entre sessões Claude (ADR 0119 Tier 1) |
| US-INFRA-007 session-start-check | COORDENAÇÃO — alerta passivo no SessionStart se paths overlap (ADR 0119 Tier 1) |

Quando os 5 primeiros fecharem, oimpresso opera com **loop de governança fechado autossustentável** — Wagner vira validador estratégico, não bottleneck operacional. US-006/007 são complementares: cobrem coordenação entre sessões paralelas (Claude-A vs Claude-B).

---

**Última atualização:** 2026-05-09 — adicionadas US-INFRA-006/007 ([ADR 0119](../../decisions/0119-paralelismo-sessoes-whats-active-tier-1.md))
