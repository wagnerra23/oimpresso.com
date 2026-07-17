---
slug: 0109-claude-design-plugin-integrado-processo-mwart
number: 109
title: "Claude Design plugin (Anthropic) integrado ao processo MWART — design supervisionado estado da arte"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by:
  - W
decided_at: '2026-05-08'
quarter: 2026-Q2
related:
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0104-processo-mwart-canonico-unico-caminho
  - 0105-cliente-como-sinal-guiar-sem-mandar
  - 0107-emendation-0104-visual-comparison-gate-f3
  - 0108-regressao-visual-pest-browser-tier-2
emends:
  - '0107'
pii: false
---

# ADR 0109 — Claude Design plugin integrado ao processo MWART (design supervisionado estado-da-arte)

**Status:** ✅ Aceita
**Data:** 2026-05-08
**Decisão por:** Wagner Rocha
**Emenda:** ADR 0107 (Visual comparison gate F1.5) — adiciona orquestração com Claude Design plugin Anthropic
**Não supersede:** ADRs 0104, 0105, 0107, 0108

---

## Contexto

Sequência de refators visuais consecutivos (PRs #252 → #255 → #256) na tela `/sells/create` mostrou que skill `mwart-comparative` V1 e V2 **ainda eram insuficientes** pra produzir resultado "estado da arte". Cada iteração Wagner reportava "ficou feio ainda" até finalmente aceitar.

Wagner observou em sessão 2026-05-08:

> *"Faça isso, Objetivo é estado da arte então crie meios para fazer igual ao Claude Design. Aprenda use o necessário."*

**Diagnóstico:** o oimpresso já tem o **Claude Design plugin da Anthropic** disponível como skills (`design:design-critique`, `design:design-handoff`, `design:design-system`, `design:accessibility-review`, `design:ux-copy`, `design:research-synthesis`, `design:user-research`). Essas são as ferramentas oficiais que a Anthropic projetou pra produzir design estado-da-arte.

**Skill `mwart-comparative` V1/V2 não usava nenhuma delas.** Trabalhava com tabela própria (8→15 dimensões em texto) sem aproveitar o framework Anthropic existente. Resultado: análise rasa que Wagner classificou como "incompetente".

## Decisão

Orquestrar `mwart-comparative` V3 chamando **explicitamente** as skills Claude Design plugin como sub-tools no workflow F1.5 e F4 do processo MWART (ADR 0104).

### Workflow MWART V3 (com Claude Design integrado)

```
F1   PLAN              cockpit-runbook gera RUNBOOK + SPEC
                       ↓
F1.5 VISUAL DESIGN     mwart-comparative V3:
                       ├─ EXIGIR referência visual (Wagner cola screenshot/URL)
                       ├─ Invocar design:research-synthesis (análise persona)
                       ├─ Invocar design:design-system (audit consistency)
                       ├─ Invocar design:ux-copy (review microcopy)
                       ├─ Gerar 15 dimensões + screenshot proposta
                       ├─ PARAR — Wagner aprova screenshot
                       ↓
F2   BACKEND BASELINE  mwart-quality + multi-tenant-patterns
                       ↓
F3   FRONTEND INCREM.  mwart-quality + cockpit-runbook modo B audit
                       ├─ APÓS impl: invocar design:design-critique
                       ├─ Score audit ≥80 OU revisão Wagner
                       ├─ Pest 4 Browser snapshot baseline (ADR 0108)
                       ↓
F3.5 ACCESSIBILITY     design:accessibility-review (WCAG 2.1 AA)
                       ↓
F4   QA HARDENING      cockpit-runbook modo B comprehensive ≥80
                       ├─ Smoke biz=1
                       ├─ Canary 7d Wagner
                       ├─ Pest browser baseline locked
                       ↓
F5   CUTOVER           commit-discipline + memory-sync
```

### Como cada skill Anthropic é usada

| Skill Anthropic | Quando | O que captura |
|---|---|---|
| `design:research-synthesis` | F1.5 — antes de gerar visual-comparison | Persona context (Larissa biz=4, monitor 1280px), padrões de uso, prioridades |
| `design:design-system` | F1.5 — durante análise de consistência | Tokens, componentes shared, padrões visuais — gap vs canon Cockpit |
| `design:design-critique` ⭐ core | F3 — após implementação inicial | Crítica estruturada (5 categorias), comparação benchmarks externos, prioridades |
| `design:design-handoff` | F1.5 — pra clarificar specs antes de codar | layout exato, tokens, props contract, estados, responsividade |
| `design:ux-copy` | F1.5 — review microcopy | Labels, placeholders, error messages, empty states, CTAs |
| `design:accessibility-review` | F3.5 — pré-merge | WCAG 2.1 AA audit (contraste, keyboard, ARIA) |
| `design:user-research` | Quando Wagner pede reset estratégico | Plano de pesquisa, interview guides — gap fechado |

### Output enriquecido do `<tela>-visual-comparison.md`

```markdown
---
status: draft | approved
reference_visual_url: <link/screenshot Wagner colou>
design_critique_score: <score 0-100 do design:design-critique>
accessibility_score: <WCAG AA pass/fail>
ux_copy_review: <approved | issues>
---

## A. 15 dimensões (V2)
[tabela existente]

## B. Design Critique (Anthropic framework)
### First Impression
[2-second test]
### Usability
[tabela severity]
### Visual Hierarchy
[reading flow]
### Consistency
[gap design system]
### Accessibility
[WCAG]
### What Works Well
[positivos]
### Priority Recommendations
[3 ações concretas]

## C. Benchmarks externos comparados
[Linear / Vercel / Stripe — pergunta: "essa tela parece dessas equipes?"]

## D. Screenshot proposta vs referência
[Chrome MCP screenshot lado-a-lado]

## E. Decisão final Wagner
[checkboxes de aprovação por dimensão]
```

## Consequências

### Boas

- **Reduz "ficou feio ainda"** — design-critique força análise antes de Wagner abrir, não pós-deploy
- **Captura camadas que skill própria não captura** — research, ux-copy, accessibility, design-system são especialistas separados
- **Reduz round-trips** — orquestração interna em vez de Wagner re-pedir 5x
- **Onboarding novo dev** — usa ferramentas Anthropic conhecidas, não framework caseiro
- **Custo zero** — skills Anthropic já estão no plano, sem licenciamento adicional

### Ruins / mitigações

- **Skill mwart-comparative V3 vira orchestrator longo** — chama 4-5 sub-skills. **Mitigação:** invocações condicionais (research só se persona nova; ux-copy só se microcopy crítico)
- **Tempo F1.5 cresce de 5min pra ~15min** — múltiplas invocações de skill. **Mitigação:** vale a pena vs PRs corretivos (Sells gastou 5 PRs em refator visual)
- **Dependência de skills externas** — se Anthropic mudar contratos, skill V3 quebra. **Mitigação:** documentar versão atual + fallback pra V2 se sub-skill falhar

## Plano de aplicação

1. **Hoje (este PR):**
   - [x] ADR 0109 criado
   - [x] Skill `mwart-comparative` V3 atualizada com orquestração Claude Design
   - [x] CLAUDE.md atualizado (mwart-comparative V3 usa Claude Design plugin)
   - [x] Refator Sells/create aplicando recomendações do design:design-critique (ADR 0109 §recomendações)

2. **Próxima migração** (US-SELL-007 ou outra):
   - Skill V3 ativa automático
   - Orquestra 4-5 sub-skills Anthropic
   - Wagner aprova screenshot proposta
   - Implementa
   - design:design-critique pós-impl

3. **Backfill retroativo** (próximo trimestre):
   - Aplicar V3 nas 78 Pages existentes em ordem de tráfego (Sells → Repair → Officeimpresso → ...)
   - Cada uma gera visual-comparison.md V3 + score critique
   - Refators priorizados por menor score

## Refs

- [Claude Design plugin docs](https://docs.anthropic.com/en/docs/claude-code/plugins#design)
- [skill design:design-critique](../../.claude/skills/.../design/design-critique/SKILL.md) — Anthropic plugin
- [ADR 0094 — Constituição V2](0094-constituicao-v2-7-camadas-8-principios.md)
- [ADR 0104 — Processo MWART](0104-processo-mwart-canonico-unico-caminho.md)
- [ADR 0107 — Visual comparison gate F1.5](0107-emendation-0104-visual-comparison-gate-f3.md) — emendado
- [ADR 0108 — Pest browser snapshot Tier 2](0108-regressao-visual-pest-browser-tier-2.md)

---

**Última atualização:** 2026-05-08
