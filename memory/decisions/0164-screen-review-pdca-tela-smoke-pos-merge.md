---
slug: 0164-screen-review-pdca-tela-smoke-pos-merge
number: 0164
title: "Screen Review PDCA — fase C (Check) automática pós-merge via skill tela-smoke-pos-merge"
type: adr
status: accepted
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: 2026-05-17
accepted_at: 2026-05-17
review_at: 2026-08-17
module: Governance
quarter: 2026-Q2
tags: [governance, pdca, mwart, screen-review, browser-mcp, visual-validation, smoke, wagner-bottleneck, ux-loop]
supersedes: []
supersedes_partially: []
superseded_by: []
related: [0104, 0107, 0114, 0109, 0094, 0163]
pii: false
review_triggers:
  - Quando 3+ telas falham smoke 2x consecutivo → revisar charter UX targets (sinal que metas estão calibradas erradas)
  - Quando Tailscale-only restringir time MCP entrante (Felipe/Maiara/Eliana/Luiz) → criar Initiative promover rota acesso (Cloudflare Tunnel / VPN dedicada)
  - Quando custo browser MCP > R$10/mês (telemetria mcp_observability_spans) → otimizar frequency / amostragem
  - Se Wagner pular round de review 2x consecutivo (status pending-wagner > 72h) → revisar mecanismo notificação (mcp_alertas insuficiente / canal errado)
  - Quando >50% das telas oimpresso tiverem ≥3 rounds aprovados consecutivos → relaxar gate pra batch quinzenal (regime maduro)
---

# ADR 0164 — Screen Review PDCA · fase C (Check) automática pós-merge

## 1. Contexto

[ADR 0104](0104-processo-mwart-canonico-unico-caminho.md) instituiu o processo MWART (5 fases: F1 Charter → F2 Backend → F3 Frontend → F4 QA → F5 Cutover) como único caminho de migração/criação de telas Inertia/React. O processo cobre **planejamento e execução** mas tem um gap operacional crítico catalogado em 2026-05-15+:

**Sintoma observável (Wagner palavras 2026-05-17):**
> *"depois de cada tela criada quero rotina automática 'ver a cagada que tu fez'. hoje loop quebrado: eu virou gargalo de validação visual."*

Hoje o fluxo após merge de PR que toca `resources/js/Pages/<Mod>/<Tela>.tsx` é:

1. PR mergeado → CI verde → deploy automático ([ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md) §loop fechado por métrica)
2. **GAP** — ninguém valida visualmente o estado em produção
3. Wagner descobre regressão por ROTA LIVRE (Larissa reporta) ou por acaso navegando
4. Re-trabalho retrospectivo custa 5-10× vs detecção imediata pós-merge (lição maratona WhatsApp 14-15/mai)

**Wave 19-28 (governance v4 — [ADR 0163](0163-governance-v4-metas-alcancadas-ondas-19-28.md))** elevou 34 módulos a média ~92pp por bucket mas **não cobriu validação visual pós-merge** — gap permanece no eixo UX/produto-cliente.

[ADR 0114](0114-prototipo-ui-cowork-loop-formalizado.md) formalizou loop Cowork ↔ Claude Code pra protótipos UI **antes** de implementação. Falta o espelho — loop **depois** de implementação.

## 2. Decisão

**Adicionar fase C (Check) automática ao ciclo MWART (ADR 0104), implementada via skill canônica `tela-smoke-pos-merge` Tier B com trigger automático em PRs mergeados que toquem `resources/js/Pages/**/*.tsx`.**

### 2.1 Ciclo MWART evoluído — PDCA explícito

| Fase original (ADR 0104) | Fase PDCA | O que muda |
|---|---|---|
| F1 Charter | **P** (Plan) | charter aprovado Wagner ANTES de F2 (sem mudança) |
| F2 Backend + F3 Frontend | **D** (Do) | 4 agents paralelos entregam código (sem mudança) |
| F4 QA + F5 Cutover | (parte do D, parte do C) | smoke Pest local biz=99 fake (D); deploy + canary 7d (C parcial) |
| **NOVA — pós-merge automática** | **C** (Check) | **skill `tela-smoke-pos-merge` roda browser MCP automático contra prod, captura screenshots 1440+1280, console errors, perf API, append `<Tela>.smoke-log.md`** |
| **NOVA — Wagner decisão** | **A** (Act) | **Wagner edita `<Tela>.review.md` round N: `approved` / `rejected` / `iterate`. Se rejected → spawn agent iteração round N+1.** |

### 2.2 Três artefatos canon novos por tela (append-only)

Vivem ao lado do `<Tela>.charter.md` em `resources/js/Pages/<Mod>/`:

| Artefato | Função | Append-only? | Trigger criação |
|---|---|---|---|
| `<Tela>.review.md` | Histórico rounds Wagner decisão (approved/rejected/iterate + comentário) | ✅ rounds nunca sobrescrevem | Round 1 criado pela skill após primeiro smoke; rounds N+ apendados |
| `<Tela>.smoke-log.md` | Log corrido execuções browser MCP (timestamp, status, screenshots refs, console errors, perf metrics) | ✅ entries nunca sobrescrevem | Cada execução skill apenda entry |
| `<Modulo>/UI-CATALOG.md` | Índice todas telas do módulo + status agregado (live / pending-wagner / rejected-iterating) | 🟡 índice regenerável (não append-only) | Skill regenera ao final de cada execução |

### 2.3 Trigger automático — GitHub Actions

Workflow `.github/workflows/screen-smoke-after-merge.yml` (criado por agent paralelo W30-C) detecta PR mergeado em `main` tocando `resources/js/Pages/**/*.tsx` e dispara a skill `tela-smoke-pos-merge` via Claude Code remote.

Frequência adicional:
- **Cron daily 09:00 BRT** — re-smoke todas telas com `status: live` há ≥7d sem refresh (catch regressão silenciosa)
- **Pedido explícito Wagner** — "smoke a tela X" / "validar tela X visualmente" / "ver como ficou tela Y" / `/tela-smoke <rota>`

### 2.4 Mecanismo Wagner aprova/rejeita/itera

1. Skill cria/apenda round N em `<Tela>.review.md` com `status: pending-wagner`
2. Skill notifica Wagner via `mcp_alertas` (tabela canônica) — payload: link `<Tela>.review.md` + thumbnails 1440/1280 + diff console errors vs round anterior
3. Wagner edita `<Tela>.review.md` round N decisão:
   - `status: approved` → skill marca `<Tela>.charter.md` `status: live` (se ainda não estava) + fecha round
   - `status: rejected` → cria Initiative governance automática (`mcp_initiatives`) + spawn agent paralelo iteração round N+1
   - `status: iterate` → cria task `mcp_tasks` pro agente designado (humano time MCP ou agent paralelo) com TODOs do comentário Wagner

## 3. Tier 0 IRREVOGÁVEL

- ⛔ **Skill READ-ONLY no DB prod** — zero side-effect; só leitura (queries `SELECT`, browser navigation, screenshot). NUNCA execute, INSERT, UPDATE, DELETE durante smoke
- ⛔ **Wagner credentials Vaultwarden** — login via Vaultwarden API (`vault.oimpresso.com`), NUNCA hardcoded em SKILL.md / código / .env público
- ⛔ **Screenshots não armazenam PII** — auto-mask CPF/CNPJ/email/telefone via regex post-capture ANTES de attach em `<Tela>.smoke-log.md` ou upload pra qualquer storage. `PiiRedactor` reaproveitado ([ADR 0093](0093-multi-tenant-isolation-tier-0.md))
- ⛔ **Append-only `<Tela>.review.md` e `<Tela>.smoke-log.md`** — rounds e entries de log NUNCA sobrescrevem (mesma regra ADR canon). UI-CATALOG.md é regenerável (índice — não conta como append-only)
- ⛔ **Smoke contra biz=99 fake** quando possível ([ADR 0101](0101-tests-business-id-1-nunca-cliente.md)) — biz=4 ROTA LIVRE Larissa só em casos justificados onde fluxo exige dados reais (ex: revisão visual de tela que renderiza pedido real)
- ⛔ **Browser MCP isolado em CT 100** — execução via `mcp.oimpresso.com` (CT 100 Proxmox); Hostinger NUNCA roda browser MCP ([ADR 0062](0062-separacao-runtime-hostinger-ct100.md))

## 4. Consequências

### Imediatas (aplicáveis pós-merge desta ADR + W30 PRs)

1. Skill `tela-smoke-pos-merge` Tier B disponível no `.claude/skills/` — auto-trigger por description
2. Pattern doc canon `memory/requisitos/_DesignSystem/SCREEN-REVIEW-PDCA.md` documenta templates + exemplo round real
3. Wagner deixa de ser gargalo de validação visual — fluxo padrão Wagner = `pending-wagner` count no `mcp_alertas` (rotina diária)
4. Time MCP entrante (Felipe/Maiara/Eliana/Luiz) ganha visibilidade unificada via `UI-CATALOG.md` por módulo
5. Custo browser MCP estimado < R$10/mês (browser MCP `mcp__claude-in-chrome__*` é gratuito; LLM tokens ~$0.02 por smoke × ~50 telas/mês × ~2 rounds = ~$2/mês)
6. Telemetria via `mcp_observability_spans` ([ADR 0162](0162-otel-collector-prod-observability.md)) — service `tela-smoke-pos-merge` reporta latência + sucesso/falha

### Longo prazo

- 30d pós-ativação: revisar % telas que precisaram round 2+ → calibrar charters UX targets (sinal qualificado se >30% precisam rework — falta detalhe no charter)
- Q3 2026: avaliar promoção pra Tier A (always-on) se uso provar valor (similar caminho `brief-update` Wave 18)
- Possível extensão futura: smoke comparativo automatizado vs protótipo Cowork ([ADR 0114](0114-prototipo-ui-cowork-loop-formalizado.md)) — gate visual F1.5 + F3 já comparam; integrar com PDCA fase C

## 5. Trade-offs explícitos

| Escolha | Alternativa rejeitada | Por quê |
|---|---|---|
| Skill Tier B auto-trigger por description | Tier C slash command manual | Wagner palavras: "rotina automática", não "ferramenta quando eu pedir" — manual perpetua bottleneck |
| Browser MCP (`mcp__claude-in-chrome__*` + `mcp__computer-use__*`) | Playwright headless dedicado | MCP já disponível, zero infra nova; Playwright daemon viola [ADR 0062](0062-separacao-runtime-hostinger-ct100.md) Hostinger; CI overhead alto. Trade: MCP browser tokens 4× CLI mas tela única ~30s ($0.02), aceitável |
| 3 artefatos por tela (review + smoke-log + UI-CATALOG) | Único `<Tela>.review.md` consolidado | Append-only review log polui com runs CI; separar review (decisão humana) de smoke-log (run máquina) facilita scan visual. UI-CATALOG módulo-level evita N round-trips |
| GitHub Actions trigger (workflow `screen-smoke-after-merge.yml`) | Hook `post-merge` git local | Time MCP usa máquinas heterogêneas; Actions garante execução central. Hook local quebra primeira semana |
| Round-based append-only `<Tela>.review.md` | Inertia comments em PR GitHub | Comments perdem contexto após PR fechado; review.md fica versionado no canon git acessível via MCP `decisions-search` análogo futuro |
| mcp_alertas pra notificação | Email / Slack direto | Slack não existe (time pequeno); email Wagner já saturado; mcp_alertas é canal canon ([ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md) §4 loop fechado) |

## 6. Custo + Latência

| Operação | Custo | Latência |
|---|---|---|
| 1 smoke tela (browser MCP + LLM analysis) | ~$0.02 | ~30-60s |
| 50 telas/mês × 2 rounds (média) | ~$2/mês | — |
| Cron daily refresh (telas live ≥7d) | incremental ~$1/mês | bg job |
| **Total estimado** | **<R$10/mês** | aceitável |

## 7. Mecanismos anti-degradação

1. **Auto-mask PII via regex post-capture** — CPF/CNPJ/email/telefone substituídos por `[REDACTED]` ANTES de salvar screenshot
2. **Skill READ-ONLY enforce** — qualquer chamada `mcp__claude-in-chrome__*` com verb `POST/PUT/DELETE/PATCH` é abortada e logada
3. **Round limite hard 5** — após round 5 sem `approved`, skill escala pra ADR feature-wish + bloqueia novos rounds (sinal qualificado: charter mal calibrado)
4. **Drift detection cron daily** — se tela `status: live` recebe deploy sem re-smoke em 48h, alert `mcp_alertas`

## 8. Cross-refs

- [ADR 0104](0104-processo-mwart-canonico-unico-caminho.md) — MWART original (esta ADR é emenda, não substituição)
- [ADR 0107](0107-emendation-0104-visual-comparison-gate-f3.md) — gate visual F3 (complementar — F3 vs F1.5 protótipo; PDCA é pós-merge prod)
- [ADR 0114](0114-prototipo-ui-cowork-loop-formalizado.md) — loop Cowork ↔ Claude Code (Plan); PDCA é Check pós-Do
- [ADR 0109](0109-claude-design-plugin-integrado-processo-mwart.md) — Claude Design plugin integrado (subagents design-critique etc)
- [ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md) — Constituição §4 loop fechado por métrica (esta ADR materializa pro eixo UX)
- [ADR 0163](0163-governance-v4-metas-alcancadas-ondas-19-28.md) — governance v4 metas (estabilizada → libera foco produto/UX)

## 9. Pendências Wagner (ativação)

1. ✅ Mergear PR W30 (esta ADR + skill + pattern doc + workflow Actions)
2. 🔴 Provisionar credentials Wagner em Vaultwarden (item `screen-smoke/wagner-prod-readonly`)
3. 🔴 Smoke real local em 3 telas piloto: 1 Vestuário (ROTA LIVRE), 1 Repair, 1 Jana — confirmar fluxo end-to-end (skill executa → review.md criado → notificação mcp_alertas)
4. 🔴 Ativar workflow `screen-smoke-after-merge.yml` em main após smoke piloto OK
5. 🔴 Re-avaliação em 30d (2026-06-17) — métrica: % telas com round ≤2 (target ≥70%)
