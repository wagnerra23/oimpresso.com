---
slug: infra
title: "Especificação funcional — Infra (loop de governança fechado)"
type: spec
module: Infra
status: ativo
owner: wagner
version: "1.2"
last_updated: "2026-06-04"
na_justified:
  D5: "Infra é loop de governança fechado (META → SINAL → DESVIO → RECÁLCULO — ADR 0105) servindo o projeto inteiro, NÃO módulo de features cliente. Não há biz=4 ROTA LIVRE consumindo GrowthBook/APM/MCP server diretamente — são fundações da plataforma. D5 cliente real não aplica por design."
  D4.b: "Infra não tem state machine FSM (ADR 0143). Concentra runbooks operacionais (deploy Centrifugo, Hostinger, CT 100, GrowthBook) e SPECs de infra — sem Eloquent Models com transições. D4.b FSM canônica N/A."
na_justified_v3:
  D6.a: "Infra é orquestração de runbooks + ADRs operacionais — sem Controllers Inertia::render. Inertia::defer N/A por ausência de telas geradas pelo módulo (US-INFRA-* viram features em outros módulos como Governance/Brief/Admin)."
related_adrs: ["0105-cliente-como-sinal-guiar-sem-mandar", "0106-recalibracao-velocidade-fator-10x-ia-pair", "0153-module-grade-rubrica-v1", "0154-module-grade-v2-na-justificado", "0155-module-grade-v3-sub-dimensoes-gate-ci", "0156-module-grade-v3-errata-otel-helper-na-justified"]
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

---

### US-INFRA-011 · Rotacionar senha MySQL Hostinger u906587222_oimpresso - exposicao sessao 2026-05-20

> owner: wagner · priority: p1 · estimate: 0.25h · status: todo · type: story
> blocked_by: —

Senha MySQL Hostinger apareceu no contexto Claude durante sessao 2026-05-20 tarde (via tailscale ssh ct100-mcp grep MYSQL_AUTH_STATE). Tratar como comprometida per memory/reference/feedback-nunca-publicar-credenciais.md.

Etapas Wagner manual (~15min):
1. hPanel Hostinger → Bancos de Dados → resetar senha do user u906587222_oimpresso
2. Vaultwarden → atualizar item hostinger-mysql-oimpresso com nova senha
3. SSH CT 100: `tailscale ssh root@ct100-mcp` + `vim /opt/whatsapp-baileys/build/.env` → atualizar MYSQL_AUTH_STATE_PASS
4. SSH CT 100: `docker compose restart whatsapp-baileys` → recriar container
5. SSH Hostinger app: `vim .env` → atualizar DB_PASSWORD
6. Smoke pos-rotacao: `tailscale ssh ct100-mcp docker run mysql SELECT 1;` deve funcionar com nova senha

Acceptance:
- Senha rotacionada no hPanel
- Vaultwarden refletindo nova senha
- 2 .env atualizados (CT 100 + Hostinger app)
- Smoke conexao OK

Refs feedback-nunca-publicar-credenciais.md + reference/hostinger-remote-mysql.md.

---

**Última atualização (US-INFRA-011):** 2026-05-20 — adicionada rotação senha MySQL pós-exposição sessão tarde Larissa biz=4

---

### US-INFRA-012 · Resolver migration order legacy pra visual-regression.yml sair de INFRA-ONLY (ADR 0108)

> owner: wagner · priority: p2 · estimate: 8h · status: todo · type: story
> blocked_by: —

## Contexto

Workflow `.github/workflows/visual-regression.yml` (Pest 4 Browser + Playwright snapshot, ADR 0108) está em **INFRA-ONLY MODE** com `continue-on-error: true` em 3 steps (Setup Laravel, Build Inertia, Run Pest Browser). Comentário no YAML linha 102-110:

> "Setup MySQL+migrate full em CI fica pra PR separado — permite job verde mesmo se migrate quebrar por ordem de migration UltimatePOS legacy (ex: ALTER TABLE contacts ADD regime AFTER contribuinte falha porque contribuinte é adicionado por migration posterior)."

Resultado: workflow EXISTE mas **não bloqueia** regressão visual real. ADR 0108 vira teatro.

Wagner mencionou em 2026-05-25 que isso é dívida técnica conhecida ("você lembra dessa?"). Validar com ele se ainda é prioridade.

## Acceptance Criteria

- [ ] Investigar quais migrations UltimatePOS legacy estão fora de ordem (provável: `ALTER TABLE contacts ADD regime AFTER contribuinte` antes da migration que adiciona `contribuinte`)
- [ ] 2 caminhos possíveis — Wagner decide:
  - **A**: Reorganizar migrations legacy (renomeação timestamp) — IRREVERSÍVEL em prod, alto risco multi-tenant Tier 0
  - **B**: Criar migration consolidada `2026_*_fix_contacts_schema_for_ci.php` que roda antes em ambiente fresh CI
- [ ] Tirar `continue-on-error: true` de `visual-regression.yml` (3 steps)
- [ ] Estabilizar baseline screenshots (rodar `--update-snapshots` local, commit batch inicial)
- [ ] Confirmar gate bloqueia: abrir PR teste com regressão visual deliberada, deve falhar
- [ ] Apendar nota em ADR 0108 marcando saída de INFRA-ONLY (status pode virar `accepted` strict)

## Não-objetivos

- ❌ NÃO mexer em migrations já aplicadas em prod (Tier 0 IRREVOGÁVEL ADR 0093)
- ❌ NÃO tirar continue-on-error sem baseline estabilizada (vai bloquear todo PR Inertia)

## Riscos

- Flakes de Playwright em CI (rendering timing) — mitigar com `await page.waitForLoadState('networkidle')`
- Diff threshold 0.1% muito apertado pode gerar falso positivo (antialiasing) — calibrar pós-baseline

## Estimate

Codável IA-pair: 8h (investigação migration + fix + estabilizar baseline). Humano-limitado: ~3 dias real pra calibrar threshold + reduzir flakes (relógio do mundo real, ADR 0106 fator 10x não aplica).

---

**Última atualização (US-INFRA-012):** 2026-05-25 — adicionada resolução migration order legacy visual-regression.yml (batch validação design system pós-conversa Wagner com Claude do chat)

### US-INFRA-013 · Implementar contract-test-gate GH Action (ADR 0207)

> owner: — · sprint: 2026-W22 · priority: p1 · estimate: 4h · status: todo · type: story
> blocked_by: —

Implementa o gate hard descrito em [ADR-0207](../../decisions/0207-contract-test-obrigatorio-pr-tela-autosave.md) — PR que toque tela autosave deve incluir fixture contract OU label `contract-test-exempt`.

## Entregáveis

1. **`scripts/contract-test-detect.sh`** — heurística no diff:
   - `git diff origin/main --name-only` → lista arquivos modificados
   - Regex match backend autosave: `Modules/*/Http/Controllers/*Autosave*Controller.php`, `app/Http/Controllers/*Autosave*Controller.php`, `Modules/*/Http/Requests/*Request.php` com método `rules()` no diff
   - Regex match frontend autosave: `resources/js/Pages/<Mod>/<Tela>.tsx` contendo `useAutosave|onAutosave|patchJson|axios.patch|router.patch`
   - Se match → verifica se `tests/Contract/Fixtures/` OU `tests/Feature/Contract/` também tem touch no diff
   - Backend touch SEM fixture touch → exit 1 + sugere nome fixture esperado
   - Imprime payload JSON pra GH comment

2. **`.github/workflows/contract-test-gate.yml`** — workflow:
   - Trigger `pull_request`
   - Job `detect`: roda `scripts/contract-test-detect.sh`
   - Job `check-exempt-label`: se detect falha, consulta `gh pr view --json labels` → se label `contract-test-exempt` presente → pass
   - Job `comment`: se falhou e não tem exempt → comenta no PR linkando ADR 0207 + nome do fixture esperado

3. **Label setup** — `contract-test-exempt` criada no repo via `gh label create contract-test-exempt --description "ADR 0207 exempt — exige session log canônico justificando" --color FFAA00`

4. **Smoke test** — abrir PR proposital quebrando regra (mexer em Controller sem fixture) → verificar bloqueio → adicionar exempt label → verificar pass

## Acceptance criteria

- [ ] Script detect.sh funciona local (`bash scripts/contract-test-detect.sh PR_NUMBER`)
- [ ] GH Action bloqueia PR-test sem fixture
- [ ] Label exempt destranca PR-test
- [ ] Comentário PR mostra ADR 0207 link + sugestão fixture
- [ ] RUNBOOK em `memory/requisitos/Infra/RUNBOOK-contract-test-gate.md` documentando troubleshooting + falsos-positivos conhecidos
- [ ] Smoke test PR (proposital quebra + exempt unlock) catalogado em session log

## Pegadinhas conhecidas

- **Refactor puro** (rename var, mover método) NÃO deve ativar gate — heurística deve checar se rules() ou body do método mudou, não só assinatura
- **Migration sem Controller** NÃO deve ativar
- **Endpoint webhook receiver** (HMAC upstream-defined) NÃO precisa fixture — documentar exempt automático via path prefix `routes/api.php` ou comentário magic
- **PR Wagner solo modo admin** — exempt label aplicada por Wagner sem session log é OK em casos extremos (sessão produtiva), mas P2 do ADR exige idealmente. Soft-warn vs hard-block decidir em retrospectiva +1 mês

## Refs

- [ADR-0207](../../decisions/0207-contract-test-obrigatorio-pr-tela-autosave.md) — Contract test obrigatório (amends 0205)
- [ADR-0205](../../decisions/0205-contract-tests-autosave-padrao-canonico.md) — Contract tests autosave canon
- [Session 2026-05-27 rollout 4 waves](../../sessions/2026-05-27-contract-tests-rollout-4-waves-paralelas.md) — 6 padrões reusáveis P1-P6
- [Session Stock adjustment doc-only](../../sessions/2026-05-27-contract-tests-stock-adjustment-decisao.md) — padrão exempt validado
- [RUNBOOK module-grades-gate-ci](RUNBOOK-module-grades-gate-ci.md) — pattern de GH Action gate similar pra inspirar

---

## Onda prevenção bugs MWART (US-INFRA-014..030) — 2026-05-28

> 17 tasks geradas a partir do dossier `memory/sessions/2026-05-28-arte-prevencao-bugs-mwart-larissa.md` e ADRs 0208/0209/0210/0211/0212/0213 (PR #1837 propostos). Atacam classe inteira de bugs Larissa R7-R10 via enforcement passivo (PHPStan/Larastan, ESLint, Wayfinder, TanStack Query, defensive logging, audit-to-backlog).

### US-INFRA-014 · Install Larastan + phpstan.neon.dist nível 5 baseline

> owner: — · priority: p0 · estimate: 8h · status: todo · type: story
> blocked_by: —

**Onda 1 · habilita enforcement infra.** `composer require --dev larastan/larastan` v2.11+, criar `phpstan.neon.dist` na raiz com nível 5 inicial (sobe pra 7 após baseline limpa em 90d), includes `vendor/larastan/larastan/extension.neon`, paths `[app, Modules]`, exclude `vendor/storage/bootstrap/cache`. Gerar `phpstan-baseline.neon` com pre-existentes (ratchet — falha só REGRESSÃO). `composer.json` script `composer phpstan`.

**Acceptance:** `composer phpstan` roda local sem erro fatal; baseline gerado; sem mudanças em código de produção.

Refs: ADR 0208 — Larastan PHPStan baseline ratchet
Pré-req: nenhum (habilita Onda 2 inteira)

### US-INFRA-015 · Workflow phpstan-gate.yml — CI ratchet contra baseline

> owner: — · priority: p0 · estimate: 2h · status: todo · type: story
> blocked_by: US-INFRA-014

**Onda 1.** `.github/workflows/phpstan-gate.yml` espelhando `ui-lint.yml`. Dispara em PR tocando `**/*.php`. Roda `vendor/bin/phpstan analyse --memory-limit=1G --error-format=github`. Compara contra baseline; falha só em delta > 0. Annotations inline. Cache PHPStan no GH Actions cache.

**Acceptance:** PR sem regressão passa; PR com regressão falha com annotation linha:col.

Refs: ADR 0208

### US-INFRA-016 · LogContextMiddleware global — business_id/user_id/request_id em todo log

> owner: — · priority: p0 · estimate: 2h · status: todo · type: story
> blocked_by: —

**Onda 1 · defensive logging.** `app/Http/Middleware/LogContextMiddleware.php` registrado em `App\Http\Kernel.php` `'web'` group antes de `AdminSidebarMenu`. Injeta em `Log::withContext`: `business_id`, `user_id`, `request_id` (UUID), `route_name`.

**Acceptance:** qualquer `Log::warning(...)` subsequente tem esses 4 campos automaticamente.

Refs: ADR 0212 Camada 1

### US-INFRA-017 · PHPStan custom rule NoMissingTenantScope (T-AP-2 Tier 0)

> owner: — · priority: p0 · estimate: 5h · status: todo · type: story
> blocked_by: US-INFRA-014

**Onda 2.** Classes em `Modules/*/Http/Controllers/*` que executam `Model::query()` sem `->where('business_id', session('user.business_id'))` OU sem global scope → erro PHPStan. Codifica T-AP-2 + T-AP-8.

**Acceptance:** rule em `app/PhpStan/Rules/NoMissingTenantScopeRule.php`; baseline absorve violações pre-existentes; novo controller sem scope = erro.

Refs: ADR 0208 + ADR 0093 (Tier 0 multi-tenant)

### US-INFRA-018 · PHPStan custom rule NoInventedModel (T-AP-1)

> owner: — · priority: p1 · estimate: 3h · status: todo · type: story
> blocked_by: US-INFRA-014

**Onda 2.** Detecta `use App\<Model>` ou `use Modules\*\Models\<Model>` que referenciam classe inexistente. Codifica T-AP-1 (agente externo F3 inventando model `FinancialEntry`, etc).

**Acceptance:** rule + fixture Pest com model inventado = erro detectado.

Refs: ADR 0208

### US-INFRA-019 · PHPStan custom rule NoNopMutationController (T-AP-13)

> owner: — · priority: p1 · estimate: 3h · status: todo · type: story
> blocked_by: US-INFRA-014

**Onda 2.** Action public que retorna apenas `return back();` ou `return redirect()->back();` sem mutação → erro. Codifica T-AP-13.

**Acceptance:** rule + fixture no-op = erro; controllers reais que só redirecionam ficam no baseline.

Refs: ADR 0208

### US-INFRA-020 · PHPStan custom rule NoSilentFallbackRule (R9 raiz)

> owner: — · priority: p0 · estimate: 6h · status: todo · type: story
> blocked_by: US-INFRA-014

**Onda 2 · custom rule.** Detecta:
- `if (empty($var)) $assignment = <expr>;` sem `Log::warning` no mesmo bloco
- `$var = $other ?? <default>;` em controllers/services (warn)
- `if (! isset($x)) $x = <default>;`

False-positive ok no início — ratchet absorve.

**Acceptance:** rule em `app/PhpStan/Rules/NoSilentFallbackRule.php`; fixture R9 (transaction_date Carbon::now() sem log) = erro; padrão correto com Log::warning prévio = OK.

Refs: ADR 0212 Camada 3

### US-INFRA-021 · Catalogar AP-18 "Fallback default sem Log::warning" no LICOES_F3

> owner: — · priority: p2 · estimate: 1h · status: todo · type: story
> blocked_by: —

**Onda 2 · doc.** Adicionar AP-18 em `prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md` Parte 3. Exemplo R9 (PR #1830). Cross-ref ADR 0212.

**Acceptance:** PR `docs/` adiciona AP-18 com 15-25 linhas.

Refs: ADR 0212 Camada 2

### US-INFRA-022 · Install Laravel Wayfinder + Vite plugin + watch types

> owner: — · priority: p0 · estimate: 2h · status: todo · type: story
> blocked_by: —

**Onda 3 · type safety.** `composer require laravel/wayfinder` (validar maturidade beta na execução). `php artisan wayfinder:install`. `npm install @laravel/wayfinder`. Vite plugin em `vite.config.js`. Tipos gerados em watch em `resources/js/types/wayfinder/`.

**Acceptance:** `npm run dev` regenera tipos quando route/Model muda; build limpa não regenera.

Refs: ADR 0210 Fase 1
Pré-req: avaliar maturidade beta — fallback spatie/laravel-data documentado no ADR 0210

### US-INFRA-023 · Zod schemas em endpoints JSON não-Inertia

> owner: — · priority: p1 · estimate: 6h · status: todo · type: story
> blocked_by: —

**Onda 3 · gap-filler.** Endpoints que NÃO vão por Inertia. Zod schemas no client:
- `/products/list` → 13 campos
- `/contacts/customers` → 11 campos (`selling_price_group_id`, `pay_term_number/type`, `shipping_address`, etc)

`schema.parse(await fetch(...))` — drift explode no fetch.

**Acceptance:** schemas em `resources/js/types/api-schemas/`; autocompletes usam parse; cenário R8 gera erro detectável.

Refs: ADR 0210 Fase 3

### US-INFRA-024 · PHPStan custom rule NoUntypedInertiaProps (R8 gate final)

> owner: — · priority: p2 · estimate: 10h · status: todo · type: story
> blocked_by: US-INFRA-014 + US-INFRA-022

**Onda 3.** Detecta `Inertia::render('X', $data)` onde `array_keys($data)` ≠ keys do TS interface Wayfinder.

**Acceptance:** rule + fixture com drift = erro; smoke 3 telas piloto Wayfinder limpas; baseline absorve telas legacy.

Refs: ADR 0210 Fase 4

### US-INFRA-025 · Catalogar AP-17 "Inertia props não tipadas" no LICOES_F3

> owner: — · priority: p2 · estimate: 1h · status: todo · type: story
> blocked_by: —

**Onda 3 · doc.** Adicionar AP-17 com exemplo R8 (PR #1828). Cross-ref ADR 0210.

**Acceptance:** PR docs adiciona AP-17 com 15-25 linhas.

Refs: ADR 0210 Fase 5

### US-INFRA-026 · Convenção markdown TASK[owner](Px) em audits

> owner: — · priority: p0 · estimate: 1h · status: todo · type: story
> blocked_by: —

**Onda 5 · convenção.** Atualizar `memory/sessions/_TEMPLATE-audit.md` com pattern `- [ ] TASK[claude](P1): <desc>` + bullets Onde/Esforço/Impact. Referência em `.claude/rules/sessions.md`. Cross-ref ADR 0213.

**Acceptance:** template + rule rotulados; próximo audit Wagner segue.

Refs: ADR 0213 Mecanismo 1
Pré-req: fundação dos próximos 4

### US-INFRA-027 · Hook audit-creates-tasks.ps1 (PostToolUse Write)

> owner: — · priority: p0 · estimate: 4h · status: todo · type: story
> blocked_by: US-INFRA-026

**Onda 5.** `.claude/hooks/audit-creates-tasks.ps1` ativa em Write matching `memory/sessions/*-audit-*.md`. Parse `- [ ] TASK[<owner>](P\d): <desc>`. Sugere batch `tasks-create`. Wagner confirma 1×. Hook escreve `<!-- TASK_CREATED: US-MOD-NNN -->` no audit. Logger `storage/logs/audit-orphan-tracker.log`.

**Acceptance:** hook ativa só nos paths certos; parser não confunde com checklists genéricos; integration test fixture mock MCP.

Refs: ADR 0213 Mecanismo 2

### US-INFRA-028 · Skill audit-to-backlog Tier B

> owner: — · priority: p1 · estimate: 5h · status: todo · type: story
> blocked_by: US-INFRA-026

**Onda 5.** `.claude/skills/audit-to-backlog/SKILL.md` Tier B. Description: "ATIVAR quando user pedir 'transformar audit em tasks', `/audit-to-backlog <doc>`, OU PostToolUse Write em `*-audit-*.md`". Lê audit, cruza com MCP backlog (`tasks-list`), propõe batch com `parent_audit:<slug>` metadata.

Pattern análogo `comparativo-do-modulo` Tier B (ADR 0089).

**Acceptance:** skill registrada; smoke com audit Sells/Create 27/05 gera batch sem duplicar PRs já mergeados.

Refs: ADR 0213 Mecanismo 3

### US-INFRA-029 · Workflow CI audit-orphan-check.yml — warning PR órfãos

> owner: — · priority: p1 · estimate: 5h · status: todo · type: story
> blocked_by: US-INFRA-026

**Onda 5 · safety-net CI.** `.github/workflows/audit-orphan-check.yml` dispara em PR tocando `memory/sessions/*-audit-*.md`. Parse `❌`/`🟡` sem `<!-- TASK_CREATED: ... -->`. Comenta listando órfãos. Não bloqueia merge — opt-in via `<!-- TASK_IGNORED: razão -->`.

**Acceptance:** PR com `❌` órfão = comment auto; PR com TASK_CREATED ou TASK_IGNORED = sem comment.

Refs: ADR 0213 Mecanismo 4

### US-INFRA-030 · health-check audits_with_orphan_findings — auditoria periódica

> owner: — · priority: p2 · estimate: 2h · status: todo · type: story
> blocked_by: US-INFRA-029

**Onda 5 · safety-net.** `jana:health-check` ganha check novo. Roda daily 06:00 BRT. Conta `❌`/`🟡` em `memory/sessions/*-audit-*.md` (últimos 30d) sem TASK_CREATED. Primeiro hit dispara ALERT entry em `storage/logs/laravel.log`.

**Acceptance:** check rodando + métrica visível; alerta dispara em fixture audit órfão.

Refs: ADR 0213 Mecanismo 5

---

### US-INFRA-031 · Resolver colisões de const/function globais em tests/Feature (bloqueia suíte Pest completa)

> owner: — · priority: p1 · estimate: 3h · status: todo · type: story
> blocked_by: —

**Contexto:** após o fix do double-binding Pest (PR #1943, commit 073fff72e), a discovery da suíte avança e bate em colisões de símbolos globais entre arquivos de teste — PHP carrega todos os arquivos no namespace global, então helpers/consts com mesmo nome colidem. Pré-existente (não é regressão); estava mascarado atrás do crash do double-binding.

**FATAL (`Cannot redeclare function`):**
- `readEditController()` declarada SEM guard `function_exists` em DOIS arquivos: `tests/Feature/Sells/Wave1EditBaselineTest.php:22` e `tests/Feature/Purchase/Wave2EditBaselineTest.php:13`.
- `repo_path()` em 14 arquivos `Wave2*` (Bulk/Create/Edit/Index/SellingPrices/Show/StockHistory × Baseline/Inertia) — provavelmente já com guard `function_exists` (carrega antes e não deu fatal); confirmar + padronizar.

**Warnings (16 consts globais duplicadas, não-fatais):** PAGE_PATH, EDIT_CHARTER_PATH (×3), KB975_INERTIA_CSS (×3), KB975_SHOW_PAGE (×3), COMMISSION_SELL_CONTROLLER_PATH, SHOW_PAGE_PATH, EDIT_PAGE_PATH, SELLS_INDEX_PATH, SHOW_CHARTER_PATH, KB975_INDEX_PAGE, SKILLS_VALIDADAS, ROOT, EDIT_CONTROLLER_PATH, QUOTATIONS_CHARTER_PATH, SUBSCRIPTIONS_CHARTER_PATH, DRAFTS_CHARTER_PATH.

**Fix sugerido (escolher 1, consistente):** (a) `if (! function_exists('x')) { function x(){} }` nas funções + `defined('X') || define('X', ...)` nas consts colisivas; (b) melhor: mover helpers genuinamente compartilhados (repo_path, readEditController, path consts) pra um arquivo único autoloadado (ex: `tests/Pest.php` ou `Helpers.php`) e remover as declarações por-arquivo; (c) namespacear cada arquivo de teste.

**Validação:** `php vendor/bin/pest --list-tests` (em D:\oimpresso.com) deve completar discovery sem "Cannot redeclare"; depois `php artisan test` consegue enumerar a suíte inteira. Só arquivos de teste — sem business_id. Branch dedicada (ex: fix/pest-feature-global-symbol-collisions).

---

### US-INFRA-032 · Triar 11 hardcodes $businessId === N flagados pelo NoHardcodeBusinessIdInModulesTest (guard Tier 0 agora ativo)

> owner: — · priority: p2 · estimate: 2h · status: todo · type: story
> blocked_by: —

**Contexto:** o guard anti-regressão `tests/Feature/Architecture/NoHardcodeBusinessIdInModulesTest` (Tier 0, regra Wagner 2026-05-18 IRREVOGÁVEL) nunca rodava porque a suíte morria no double-binding Pest. Após PR #1943 ele roda e **FALHA com 11 hits**. Não é regressão nova — código pré-existente que o guard agora enxerga.

**Hits (`$businessId === N` / `$bizId === N`):**
- `Modules/Financeiro/Http/Controllers/CobrancaController.php:76` → `$businessId === 1`
- `Modules/NfeBrasil/Http/Controllers/CertificadoController.php:105,179` → `$businessId === 0`
- `Modules/NfeBrasil/Http/Controllers/NfeEmissaoController.php:58,120,172,206` → `$businessId === 0`
- `Modules/NfeBrasil/Http/Controllers/NfeInutilizacaoController.php:53,102` → `$businessId === 0`
- `Modules/NfeBrasil/Http/Controllers/NfeStatusController.php:66` → `$businessId === 0`
- `Modules/OficinaAuto/Http/Controllers/ProducaoOficinaController.php:222` → `$bizId === 0`

**Triagem sugerida (por hit):**
- `=== 0` (10 de 11): provavelmente **sentinela** "sem tenant / contexto sistema" → **falso-positivo** do regex. Decisão: refinar `PATTERNS_BANIDOS` no test pra excluir comparação com `0` (ex: exigir `\d+` mas tratar `=== 0` como guard de ausência, não gate per-business). Confirmar lendo cada caso.
- `=== 1` (CobrancaController:76): parece **gate per-business real** (biz=1) → avaliar refatorar pra `ModuleUtil::hasThePermissionInSubscription(...)` OU documentar justificativa.

**Objetivo:** não deixar o guard Tier 0 vermelho indefinidamente — ou ajustar o regex (falsos-positivos) ou refatorar os reais. Refs: `memory/reference/feedback-habilitar-modulo-por-business.md`, ADR 0093. Depende de US-INFRA-031 pra o guard rodar em CI de suíte completa (mas dá pra rodar isolado: `php vendor/bin/pest tests/Feature/Architecture`).

---

### US-INFRA-033 · Suíte Pest no staging falha em massa por testes fazerem Schema::create/dropIfExists cru em tabelas compartilhadas (vs clone MySQL)

> owner: — · priority: p1 · estimate: 8h · status: todo · type: story
> blocked_by: —

**Contexto:** após resolver as colisões de símbolo global (US-INFRA-031, PR #2251 makeChannel), o `php artisan test` escopado ao módulo Whatsapp finalmente roda no CT 100 staging (`oimpresso-staging`) — mas dá **~493 falhas** (318 deprecated, 1071 assertions, 249s). As falhas NÃO são regressão de feature; são incompatibilidade entre o design dos testes e o banco real do staging.

**Causa raiz (2 padrões):**
1. **Teardown FK:** muitos testes fazem `Schema::dropIfExists('contacts'|'channels'|...)` no `beforeEach` em tabelas compartilhadas. No clone MySQL anonimizado da prod (ADR 0235) existem FK constraints reais → `SQLSTATE[23000]: 1451 Cannot delete or update a parent row` (ex: `LidCrossContactIncidentP0Test:30`). Em SQLite `:memory:` isso passava.
2. **Semântica MySQL vs SQLite:** índices UNIQUE com coluna nullable (ex: `channel_user_access` UNIQUE inclui `revoked_at`). MySQL trata `NULL` como distinto → grant duplicado ativo NÃO viola UNIQUE → teste que `->toThrow(QueryException)` falha (ex: `ChannelUserAccessTest` R-WA-068-005). Mesma classe atinge schema drift (`ConversationSchemaIdentitiesTest` → `Column not found: customer_external_id in whatsapp_conversations`).

**Por que importa (sinal qualificado):** o RUNBOOK-acesso-ct100-testes-time prevê Maiara[M]/Felipe[F] rodando `sudo staging-test` no CT 100. Do jeito atual a suíte é inutilizável pra eles (mar de vermelho mascara falhas reais).

**Acceptance (a decidir na investigação):**
- [ ] Definir estratégia canônica de DB de teste no CT 100: banco de teste dedicado com migrations frescas + `RefreshDatabase`/transações, OU `:memory:` sqlite no container, OU schema de teste MySQL separado — em vez de apontar `phpunit.xml` pro `oimpresso_staging` (clone de prod).
- [ ] Tests não devem fazer `Schema::dropIfExists`/`create` cru em tabelas compartilhadas. ⚠️ **Cuidado com `RefreshDatabase`:** ele roda `migrate:fresh` (dropa todas as tabelas) — **destrutivo contra o clone compartilhado** (apagaria o `oimpresso_staging` anonimizado) e envenena lanes MySQL compartilhadas (dropa FK + limpa seed). Só é seguro com DB de teste dedicado/efêmero. Contra DB pré-seedado/clone, o padrão validado é `DatabaseTransactions` (rollback por teste, sem re-migrate) + skip-guard quando schema/biz ausente.
- [ ] Auditar asserts que dependem de semântica SQLite (UNIQUE+NULL) e torná-los DB-agnostic ou skip-on-mysql explícito.
- [ ] Meta: `sudo staging-test` (suíte cheia ou por módulo) verde ou com falhas conhecidas catalogadas — não 493 ruidosas.

**Refs:** US-INFRA-031 (colisões globais, pré-requisito já resolvido p/ Whatsapp) · ADR 0235 (staging clone anonimizado) · `memory/requisitos/Infra/RUNBOOK-acesso-ct100-testes-time.md` · descoberto durante PR #2251 · **US-FIN-053 / PR #2253** (precedente trabalhado: a lane `financeiro-pest.yml` resolveu esta mesma classe — `CaixaMovimentoFreshnessTest` migrado de `RefreshDatabase` → `DatabaseTransactions` + skip-guard contra MySQL real; o mesmo PR corrigiu 2 bugs de schema-drift `deleted_at`/`valor_baixa` no `FinanceiroHealthCommand`, instância do padrão #2 "schema drift").

### US-INFRA-035 · Item 7 ADR 0271 — fusão 4 gates de cor → 1 (executar ≥2026-06-18)

> owner: claude · priority: p2 · estimate: 4h · status: todo · type: story
> blocked_by: —

Último item da revisão de gates (ADR 0271). NÃO executar antes de 2026-06-18 — deixar os 14 required + enforce_admins=true (flip 2026-06-11) assentarem 1 semana.

Plano turnkey completo (com comandos gh api de swap e rollback) no handoff `memory/handoffs/2026-06-11-0930-gates-itens56-aplicados-item7-estruturado.md`:

- **P1 (PR aditivo):** criar `color-canon-gate.yml` (job `Cor canon · ratchet unificado vs baseline`, always-run SEM `paths:`) consolidando conformance cor-crua + ui-lint + stylelint + CockpitAccentCanonTest com 1 baseline unificada. MANTER os 4 antigos rodando em paralelo. Paridade: mesmo veredito em ≥3 PRs reais + 1 violação proposital.
- **P2 (PATCH swap):** trocar os 2 required de cor pelo unificado (14→13) — comando pronto no handoff.
- **P3 (PR subtrativo):** deletar `conformance-gate.yml` + `ui-lint.yml` + `stylelint-gate.yml` + tirar CockpitAccentCanonTest do `ui-architecture-gate.yml` (teste continua na suíte Pest) + baselines antigas + anti-drift ADR 0270 nos docs.

Cada passo é individualmente seguro — sem janela de deadlock "Expected — waiting". Rollback: re-PATCH pra lista de 14 do handoff.

---

**Última atualização (US-INFRA-035):** 2026-06-11 — criada via `tasks-create` MCP na sessão de verificação dos itens 5/6 (handoff 2026-06-11-0930). Gate temporal: ≥2026-06-18.

**Última atualização (US-INFRA-033):** 2026-06-04 — follow-up do PR #2251 (fix makeChannel): com as colisões globais resolvidas pro Whatsapp, a suíte roda no staging mas expõe ~493 falhas por testes fazerem Schema cru em tabelas compartilhadas vs clone MySQL. Item de harness de teste no CT 100.

**Última atualização (US-INFRA-031..032):** 2026-05-29 — 2 follow-ups do fix Pest double-binding (PR #1943): colisão de símbolos globais em tests/Feature (bloqueia suíte completa) + triagem dos 11 hardcodes business_id que o guard Tier 0 (agora ativo) flagou.

**Última atualização (US-INFRA-014..030):** 2026-05-28 — adicionadas 17 tasks Onda prevenção bugs MWART (ADRs 0208-0213 propostos no PR #1837). Atacam R7/R8/R9/R10-class via enforcement passivo (Larastan, Wayfinder, Zod, hooks audit-to-backlog).

**Última atualização (US-INFRA-013):** 2026-05-27 — adicionada implementação contract-test-gate GH Action ADR 0207 (entrega da decisão promovida pela sessão 4 waves paralelas)

### US-INFRA-036 · CSS hex drift fase 2 — tokenizar 61 valores hex crus restantes

> owner: — · priority: p2 · estimate: 4h · status: todo · type: story
> blocked_by: —
> parent_plan: css-hex-drift-fase2

**Iniciativa-plano perdida** recuperada pro backlog (triagem 2026-06-20 · run wf_1bfbefba).
labels: `plano-perdido`, `backlog-2026-06-20`

**Sinal (ADR 0105 · métrica em drift):** 61 valores hex crus restantes (fase 2 do drift de cor do DS).
**Dedup:** distinto de US-INFRA-035 (fusão dos 4 gates de cor) e US-SELL-043 (CSS Cowork → tokens no Sells).

**DoD:**
- Substituir os 61 hex por tokens DS canônicos.
- Gate de cor verde.

**Fonte:** memory/requisitos/_processo/BATCH-BACKLOG-34-2026-06-20.md (§Aprovação [W] 2026-06-20)

### US-INFRA-037 · Roadmap redução de CSS manual (~28k → ~20k linhas)

> owner: — · priority: p2 · estimate: 6h · status: todo · type: story
> blocked_by: —
> parent_plan: manual-css-js-roadmap

**Iniciativa-plano perdida** recuperada pro backlog (triagem 2026-06-20 · run wf_1bfbefba).
labels: `plano-perdido`, `backlog-2026-06-20`

**Sinal (ADR 0105 · métrica em drift):** CSS manual ~28k linhas, meta ~20k (redução via tokens/DS).

**DoD:**
- Roadmap de redução por área (maiores ofensores primeiro).
- Execução incremental + métrica de linhas trackada.

**Fonte:** memory/requisitos/_processo/BATCH-BACKLOG-34-2026-06-20.md (§Aprovação [W] 2026-06-20)

### US-INFRA-038 · SDD: promover os 3 steps de continue-on-error a required (gate-required)

> owner: — · priority: p2 · estimate: 3h · status: todo · type: story
> blocked_by: —
> parent_plan: sdd-gate-required

**Iniciativa-plano perdida** recuperada pro backlog (triagem 2026-06-20 · run wf_1bfbefba).
labels: `plano-perdido`, `backlog-2026-06-20`

**Sinal (ADR 0105 · métrica em drift):** 3 steps do gate SDD estão em `continue-on-error` (não mordem — "a suite mente").

**DoD:**
- Promover os 3 steps a required conforme calendário ADR 0275.
- Baseline armado antes de morder (rodar `sdd-avaliar`).

**Fonte:** memory/requisitos/_processo/BATCH-BACKLOG-34-2026-06-20.md (§Aprovação [W] 2026-06-20)

### US-INFRA-039 · SDD KL E2/E3 — aplicar os 27 renames classe A

> owner: — · priority: p2 · estimate: 4h · status: todo · type: story
> blocked_by: —
> parent_plan: sdd-kl-e2-e3

**Iniciativa-plano perdida** recuperada pro backlog (triagem 2026-06-20 · run wf_1bfbefba).
labels: `plano-perdido`, `backlog-2026-06-20`

**Sinal (ADR 0105 · métrica em drift):** stream KL do SDD — 27 renames classe A pendentes (E2/E3).

**DoD:**
- Aplicar os 27 renames classe A.
- Suíte Pest verde pós-rename.

**Fonte:** memory/requisitos/_processo/BATCH-BACKLOG-34-2026-06-20.md (§Aprovação [W] 2026-06-20)

### US-INFRA-040 · SDD — burn-down dos 237 corruptores SQLite

> owner: — · priority: p2 · estimate: 8h · status: todo · type: story
> blocked_by: —
> parent_plan: sdd-sqlite-corruptors

**Iniciativa-plano perdida** recuperada pro backlog (triagem 2026-06-20 · run wf_1bfbefba).
labels: `plano-perdido`, `backlog-2026-06-20`

**Sinal (ADR 0105 · métrica em drift):** 237 testes "corruptores" de SQLite a eliminar (burn-down do SDD).
**Relacionado:** US-INFRA-031 (colisões const/function em tests/Feature que bloqueiam a suíte Pest).

**DoD:**
- Reduzir os 237 corruptores a 0 (burn-down trackado).
- Suíte SQLite estável.

**Fonte:** memory/requisitos/_processo/BATCH-BACKLOG-34-2026-06-20.md (§Aprovação [W] 2026-06-20)
