---
title: Screen Review PDCA — pattern doc canon (templates + exemplo round)
description: Pattern doc canônico do ciclo PDCA aplicado a telas Inertia/React (fase C/A do MWART). Templates dos 3 artefatos por tela + exemplo round real mock. Cross-ref ADR 0104 (MWART original) + ADR 0164 (PDCA emenda).
type: pattern-doc
status: aceito
authority: [Wagner]
parent_adrs: [0164, 0104]
related_adrs: [0107, 0114, 0109, 0094, 0093, 0162]
created_at: 2026-05-17
---

# Screen Review PDCA — pattern doc canon

> **Visão geral em uma frase:** Toda tela `resources/js/Pages/<Mod>/<Tela>.tsx` evolui via ciclo PDCA explícito — Plan (charter aprovado), Do (4 agents implementam), **Check (skill `tela-smoke-pos-merge` roda smoke pós-merge prod)**, **Act (Wagner edita round em `<Tela>.review.md` decisão)**. 3 artefatos canon append-only por tela.

---

## 1. PDCA aplicado a telas oimpresso

### Mapeamento MWART ↔ PDCA

| Fase original (ADR 0104) | Fase PDCA | Artefato principal | Quem executa |
|---|---|---|---|
| F1 Charter | **P** (Plan) | `<Tela>.charter.md` (UX targets + breakpoints + estados) | Wagner aprova; Claude Design plugin opcional ([ADR 0109](../../decisions/0109-claude-design-plugin-integrado-processo-mwart.md)) |
| F2 Backend Baseline | parte do **D** (Do) | Service + Controller + migration | Agent paralelo |
| F3 Frontend | **D** (Do) | `<Tela>.tsx` + componentes + props | Agent paralelo |
| F4 QA + F5 Cutover | transição **D → C** | Pest local biz=99 + deploy canary 7d | Agent + Wagner aprova cutover |
| **C** (Check) — NOVO | **C** (Check) | `<Tela>.smoke-log.md` (log corrido máquina) | **Skill `tela-smoke-pos-merge` automática** |
| **A** (Act) — NOVO | **A** (Act) | `<Tela>.review.md` (round Wagner decisão) | **Wagner edita decisão; skill enforce mecanismo** |

### Por que PDCA agora

- Wagner palavras 2026-05-17: *"depois de cada tela criada quero rotina automática 'ver a cagada que tu fez'. eu virou gargalo."*
- Maratona WhatsApp 14-15/mai: 5 vetores de drift catalogados, todos detectados retrospectivamente quando custo era 5-10×
- Governance v4 ([ADR 0163](../../decisions/0163-governance-v4-metas-alcancadas-ondas-19-28.md)) estabilizada → libera foco eixo UX/produto-cliente

---

## 2. Os 3 artefatos canon por tela

Convivem ao lado do `<Tela>.charter.md` em `resources/js/Pages/<Mod>/`:

```
resources/js/Pages/<Mod>/
├── <Tela>.tsx                  ← código (gerado D)
├── <Tela>.charter.md           ← UX target (P — gerado F1 MWART)
├── <Tela>.review.md            ← rounds Wagner decisão (A — gerado Wagner)
├── <Tela>.smoke-log.md         ← runs máquina browser MCP (C — gerado skill)
└── UI-CATALOG.md               ← índice módulo regenerável (mais 1 por módulo)
```

### 2.1 Template `<Tela>.review.md`

```markdown
---
title: <Modulo>/<Tela> — review log Wagner
type: screen-review
authority: [Wagner]
parent_adrs: [0164, 0104]
charter: ./<Tela>.charter.md
smoke_log: ./<Tela>.smoke-log.md
status_current: <pending-wagner|live|rejected-iterating>
last_round: <N>
created_at: <ISO timestamp>
---

# <Modulo>/<Tela> — review Wagner

> **Append-only.** Rounds nunca sobrescrevem. Wagner edita o último round adicionando linha `decisão:`.

## Round 1 — <ISO timestamp BRT>

- **status:** pending-wagner
- **trigger:** pós-merge PR #<num>
- **smoke-log entry:** [link âncora]
- **resumo diff vs charter:** <auto-summary skill>
- **comentário Wagner:** _aguardando_
- **decisão:** _aguardando — edite esta linha com `approved | rejected | iterate` + comentário obrigatório se rejected/iterate_

## Round 2 — <quando aplicável>
...
```

### 2.2 Template `<Tela>.smoke-log.md`

```markdown
---
title: <Modulo>/<Tela> — smoke runs log (máquina)
type: screen-smoke-log
authority: [skill tela-smoke-pos-merge]
parent_adrs: [0164]
review: ./<Tela>.review.md
created_at: <ISO timestamp>
---

# <Modulo>/<Tela> — smoke runs

> **Append-only.** Runs nunca sobrescrevem. Skill `tela-smoke-pos-merge` apenda cada execução.

## Run #1 — <ISO timestamp BRT>

- **trigger:** pós-merge PR #<num>
- **viewport 1440:** ![1440](./screenshots/<ts>-1440.png) (PII auto-masked)
- **viewport 1280:** ![1280](./screenshots/<ts>-1280.png) (PII auto-masked)
- **console errors:** <count>
  - <detalhes inline se ≤5; link de log se >5>
- **perf:** FCP <Nms> · LCP <Nms> · TTI <Nms> · TBT <Nms>
- **vs charter targets:**
  - LCP target ≤2500ms — <ok|degraded (X)>
  - breakpoint 1280px renderiza — <ok|degraded>
- **status run:** ok | failed-load | failed-auth | failed-screenshot
- **biz testado:** 99 (fake) | 4 (ROTA LIVRE justificado por <razão>)

## Run #2 — <quando aplicável>
...
```

### 2.3 Template `<Modulo>/UI-CATALOG.md`

```markdown
---
title: <Modulo> — catálogo telas + status PDCA
type: ui-catalog
authority: [skill tela-smoke-pos-merge]
parent_adrs: [0164]
created_at: <ISO timestamp>
regenerated_at: <ISO timestamp última regeneração>
---

# <Modulo> — UI Catalog

> **Regenerável** (não append-only). Skill regenera ao final de cada execução tocando este módulo.

## Telas

| Tela | Rota | Status | Último round | Charter | Review | Smoke-log |
|---|---|---|---|---|---|---|
| ListaPedidos | /<mod>/pedidos | live | 3 (approved 2026-05-16) | [link] | [link] | [link] |
| NovoPedido | /<mod>/pedidos/novo | pending-wagner | 2 (aguardando ≥48h ⚠️) | [link] | [link] | [link] |
| DetalhePedido | /<mod>/pedidos/{id} | rejected-iterating | 4 (rejected — agent round 5 em curso) | [link] | [link] | [link] |

## Sumário

- **Total telas:** 3
- **Live:** 1 (33%)
- **Pending-wagner:** 1 (33%)
- **Rejected-iterating:** 1 (33%)
- **Round médio até approved:** 3.0 (target: ≤2.0)

## Alertas ativos

- ⚠️ `NovoPedido` round 2 pending-wagner há >48h — escalar via `mcp_alertas` severidade `warning`
- ⛔ `DetalhePedido` round 4 rejected — próximo round 5 é último antes de escalar pra ADR feature-wish ([ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md))
```

---

## 3. Exemplo round real (mock — sem dados reais)

### Cenário

Módulo `Vestuario`, tela `ListaPedidos`. PR #1234 mergeado adiciona filtro "status do pedido". Workflow Actions dispara skill.

### Run da skill — append `ListaPedidos.smoke-log.md`

```markdown
## Run #1 — 2026-05-17T14:30:22-03:00

- **trigger:** pós-merge PR #1234
- **viewport 1440:** ![1440](./screenshots/20260517T143022-1440.png) (PII auto-masked: 3 CPF, 1 email)
- **viewport 1280:** ![1280](./screenshots/20260517T143022-1280.png) (PII auto-masked: 3 CPF, 1 email)
- **console errors:** 1
  - `Warning: Each child in a list should have a unique "key" prop. Check render of FiltrosStatus.` (NOVO vs baseline)
- **perf:** FCP 420ms · LCP 1850ms · TTI 2100ms · TBT 180ms
- **vs charter targets:**
  - LCP target ≤2500ms — ok (1850ms, margem 26%)
  - breakpoint 1280px (Larissa) — ok
  - filtro status visível e funcional — ok
- **status run:** ok
- **biz testado:** 99 (fake)
```

### Skill cria round 1 em `ListaPedidos.review.md`

```markdown
## Round 1 — 2026-05-17T14:30:22-03:00

- **status:** pending-wagner
- **trigger:** pós-merge PR #1234
- **smoke-log entry:** [Run #1](./ListaPedidos.smoke-log.md#run-1)
- **resumo diff vs charter:** filtro status novo adiciona warning React `unique key` em `FiltrosStatus.tsx`; perf dentro target; visual 1280px ok
- **comentário Wagner:** _aguardando_
- **decisão:** _aguardando — `approved | rejected | iterate`_
```

### Skill dispara `mcp_alertas`

Payload notifica Wagner via `my-inbox`.

### Wagner edita round 1 (fase A — Act)

Wagner abre review.md, vê warning React, edita:

```markdown
- **comentário Wagner:** filtro funcional mas warning React precisa fix. Aprovo visual + fluxo; iterar pra resolver key prop
- **decisão:** iterate — TODOs: corrigir `unique key` prop em `FiltrosStatus.tsx`
```

### Skill detecta `decisão: iterate` → cria task `mcp_tasks`

Task atribuída ao agent designado (Felipe ou agent paralelo) com referência ao charter + review round 1.

### Round 2 — após fix mergeado

Workflow Actions dispara skill novamente após PR fix mergeado. Round 2 apendado, sem warning React. Wagner aprova:

```markdown
## Round 2 — 2026-05-18T09:15:11-03:00
...
- **status:** pending-wagner
- **decisão:** approved — fix limpo, perf manteve, visual idem
```

Skill detecta `approved` → marca `ListaPedidos.charter.md` `status: live` → regenera UI-CATALOG módulo.

---

## 4. Quando criar/atualizar artefatos

| Evento | Quem | Artefato afetado |
|---|---|---|
| Tela nova criada (F1 MWART) | Agent | `<Tela>.charter.md` |
| PR mergeado tocando `<Tela>.tsx` | Workflow Actions → skill | `<Tela>.smoke-log.md` apend + `<Tela>.review.md` round N+1 + `UI-CATALOG.md` regen |
| Wagner pede smoke manual | Skill (gatilho description) | mesmo de cima |
| Cron daily 09:00 BRT | Skill | mesmo de cima pra telas live ≥7d |
| Wagner edita round (decisão) | Wagner | `<Tela>.review.md` (linha decisão); skill detecta e age |
| Round >5 sem approved | Skill | bloqueia novos rounds + cria ADR feature-wish |

---

## 5. Mecanismos anti-degradação

1. **Auto-mask PII via regex post-capture** (Passo 6 da skill) — CPF/CNPJ/email/telefone redacted ANTES de attach. Reaproveita `PiiRedactor` ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md))
2. **Round limite hard 5** — após round 5 sem `approved`, skill escala pra ADR feature-wish ([ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)) + bloqueia novos rounds. Sinal qualificado: charter mal calibrado
3. **Drift detection cron daily** — tela `status: live` que recebe deploy sem re-smoke em 48h gera alert `mcp_alertas`
4. **Append-only enforce** — review.md e smoke-log.md NUNCA sobrescrevem; PR que reescreva rounds antigos é bloqueado pelo governance gate CI

---

## 6. Cross-refs

- [ADR 0164](../../decisions/0164-screen-review-pdca-tela-smoke-pos-merge.md) — ADR mãe (PDCA fase C/A emenda ao MWART)
- [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) — MWART original (fases P + D)
- [ADR 0107](../../decisions/0107-emendation-0104-visual-comparison-gate-f3.md) — gate visual F3 (pré-merge)
- [ADR 0114](../../decisions/0114-prototipo-ui-cowork-loop-formalizado.md) — loop Cowork ↔ Claude Code (fase P — Plan)
- [ADR 0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md) — Constituição §4 loop fechado por métrica
- Skill canon: `.claude/skills/tela-smoke-pos-merge/SKILL.md`
