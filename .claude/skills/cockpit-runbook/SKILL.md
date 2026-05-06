---
name: cockpit-runbook
description: Generates a detailed RUNBOOK.md or audits a screen against the Chat Cockpit pattern (ADR 0039) for the oimpresso ERP. Activates when the user asks for "runbook", "playbook" or "receita" of a React/Inertia screen or module (e.g. "runbook da tela X", "playbook do módulo Y", "documenta a Inbox de Tarefas"), OR asks to "auditar/comparar tela contra Cockpit". Outputs `memory/requisitos/<Mod>/RUNBOOK-<tela>.md` with 11 mandatory sections, executable PT-BR snippets, clickable links, canonical CSS tokens. Calibrated against the 13 existing RUNBOOKs (reference: `Infra/RUNBOOK-criar-modulo.md`) and the `design:design-handoff` Anthropic spec. Skip for ADRs, SPECs, audits or session logs.
trust_level: L2
owner: wagner
parent_mission: meta-skill-roi-erp-autonomo
charter_adr: 0080
---

# Skill — Gerador de RUNBOOK de tela/módulo do Cockpit

## Quando ativa (3 modos)

| Modo | Gatilho típico | Output |
|---|---|---|
| **A. Generate** | "runbook da tela X", "playbook do módulo Y", "documenta a Inbox" | `memory/requisitos/<Mod>/RUNBOOK-<tela>.md` novo |
| **B. Audit** | "audita a tela X contra Cockpit", "compara essa tela com ADR 0039" | Relatório `file:line` com violações + fix sugerido |
| **C. Refresh** | "atualiza o RUNBOOK da tela X", "esse runbook tá desatualizado" | Re-gera mantendo seções não-stale + bumpa `Última atualização` |

**NÃO ativar:** ADR (decisão arquitetural), SPEC funcional (US-XXX-NNN), session log, ou skills `design-critique`/`accessibility-review` que cobrem outro escopo.

## Princípios não-negociáveis

1. **PT-BR no output** (idioma do projeto, CLAUDE.md §4)
2. **Imitar referência canônica** (ADR 0011) — `Infra/RUNBOOK-criar-modulo.md` é a régua de profundidade
3. **Plan → Validate → Execute** — antes de salvar arquivo final, gerar `RUNBOOK-<tela>.PLAN.md` e validar contra [CHECKLIST.md](CHECKLIST.md)
4. **Forward slashes em paths** sempre (Windows-safe)
5. **Source-of-truth git** — após salvar, lembrar Wagner do `git add + commit + push` (skill `memory-sync` cobre)

## Workflow obrigatório

Copiar este checklist no thinking e marcar conforme avança:

```
- [ ] 1. Receber tela alvo + módulo (Pages/<Mod>/<Tela>.tsx)
- [ ] 2. Read em paralelo das 8 fontes canônicas (ver §Fontes)
- [ ] 3. Read em paralelo de [TEMPLATE.md] + [EXAMPLES.md] + [GOTCHAS.md]
- [ ] 4. Read da própria tela + componentes shared importados
- [ ] 5. Preencher TEMPLATE → gerar PLAN intermediário em .PLAN.md
- [ ] 6. Validar PLAN contra [CHECKLIST.md] — se falha, corrigir e revalidar
- [ ] 7. Salvar versão final em memory/requisitos/<Mod>/RUNBOOK-<tela>.md
- [ ] 8. Apagar .PLAN.md
- [ ] 9. Lembrar Wagner do git add + commit + push (skill memory-sync)
```

## Fontes canônicas (Read em 1 rodada paralela)

Carregar TODAS antes de gerar — economiza round-trips:

1. [DESIGN.md](../../DESIGN.md) — hub visual + §6-§15 padrão técnico React
2. [ADR 0039 — Chat Cockpit](../../memory/decisions/0039-ui-chat-cockpit-padrao.md) — layout-mãe 3 colunas
3. [_DesignSystem ADR 0008](../../memory/requisitos/_DesignSystem/adr/ui/0008-cockpit-layout-mae-do-erp.md) — Cockpit como mãe ERP
4. [_DesignSystem ADR 0009](../../memory/requisitos/_DesignSystem/adr/ui/0009-cockpit-sidebar-light-padrao.md) — sidebar light padrão
5. [_DesignSystem/SPEC.md](../../memory/requisitos/_DesignSystem/SPEC.md) — regras R-DS-001..N (tokens, shadcn, lucide, dark mode)
6. [_DesignSystem/ARCHITECTURE.md](../../memory/requisitos/_DesignSystem/ARCHITECTURE.md) — visão arquitetural
7. [Infra/RUNBOOK-criar-modulo.md](../../memory/requisitos/Infra/RUNBOOK-criar-modulo.md) — régua de profundidade
8. **A própria tela** — `resources/js/Pages/<Mod>/<Tela>.tsx` (módulo em **PascalCase**: `Copiloto`, `Project`, `Repair`, não lowercase) + Components shared importados

## Estrutura da skill (progressive disclosure)

```
.claude/skills/cockpit-runbook/
├── SKILL.md         (este arquivo — overview ~140 linhas)
├── TEMPLATE.md      (template completo copy-paste com 11 seções + placeholders)
├── EXAMPLES.md      (1 input + 1 output end-to-end + dicas de profundidade)
├── CHECKLIST.md     (DoD detalhado + audit rules pra modo B)
└── GOTCHAS.md       (pegadinhas curadas append-only)
```

**Por que dividido:** Anthropic recomenda Pattern 2 (domain-specific organization) — `TEMPLATE.md` só carrega quando vai gerar, `GOTCHAS.md` só quando há suspeita de pegadinha, etc. Reduz tokens iniciais.

## Output esperado

**Caminho:** `memory/requisitos/<Mod>/RUNBOOK-<tela-kebab-case>.md`
Exemplos: `memory/requisitos/Copiloto/RUNBOOK-chat-cockpit.md`, `memory/requisitos/Project/RUNBOOK-inbox-tarefas.md`.

**Frontmatter YAML obrigatório** (espelha padrão `ADS/RUNBOOK-deploy-producao.md`):

```yaml
---
slug: <mod-lower>-runbook-<tela-kebab>
title: "<Mod> — Runbook da tela <Nome legível>"
type: runbook
module: <Mod>
status: active
date: <YYYY-MM-DD>
---
```

## 11 seções obrigatórias (estrutura do RUNBOOK gerado)

Detalhe completo + placeholders em [TEMPLATE.md](TEMPLATE.md). Sumário:

| # | Seção | Lacuna que cobre |
|---|---|---|
| 0 | Estado final esperado *(opcional)* | Tabela `verificação \| como conferir` no topo, antes de §1 |
| 1 | Objetivo | O quê / pra quem / dentro de qual layout |
| 2 | Pré-condições | Módulo instalado, permissão, rotas, seeds |
| 3 | Passo-a-passo | 5-10 passos com snippets executáveis |
| 4 | Tokens CSS | Vars do shell + tokens shadcn semânticos |
| 5 | Estados visuais | hover/focus/active/disabled/loading/empty/error ⭐ novo |
| 6 | Responsividade | Breakpoints SM/MD/LG/XL/2XL ⭐ novo |
| 7 | Atalhos | Tabela `Tecla\|Ação\|Escopo\|Listener` + snippet useEffect |
| 8 | Component contract | Props da Page Inertia (TypeScript interface) ⭐ novo |
| 9 | DoD checklist | 10-12 itens (vide CHECKLIST.md) |
| 10 | Pegadinhas | ≥5 itens (curados em GOTCHAS.md + específicos da tela) |
| 11 | ADR de origem | Lista clicável + 1 linha cada |

Seções marcadas ⭐ são o salto de qualidade vs. v1 (gap fechado com `design:design-handoff`).

## Modo B — Audit de tela existente

Quando o gatilho é "audita tela X contra Cockpit", **NÃO gerar RUNBOOK**. Em vez disso:

1. Read da tela `resources/js/Pages/<Mod>/<Tela>.tsx`
2. Aplicar audit rules de [CHECKLIST.md](CHECKLIST.md) — cada regra checa contra ADR 0039 + R-DS-001..N
3. Output em formato `file:line — regra violada — fix sugerido`:

```
resources/js/Pages/copiloto/Dashboard.tsx:42 — R-DS-002 violado (cor crua bg-blue-500) — usar bg-primary
resources/js/Pages/copiloto/Dashboard.tsx:88 — R-DS-001 violado (<button> HTML cru) — importar <Button> de @/Components/ui/button
resources/js/Pages/copiloto/Dashboard.tsx:120 — ADR 0039 §3 violado (coluna direita ausente apesar de contexto vinculado) — entregar <LinkedClient/> ou justificar em ADR
```

Esse modo NÃO salva arquivo — entrega no chat. Wagner decide se ajusta a tela ou registra exceção.

## Princípios estilísticos (calibrados pelos 13 RUNBOOKs)

| Aspecto | Regra |
|---|---|
| Idioma | **PT-BR em tudo** (texto, labels, comentário). Código em inglês ok. |
| Links | Relativos a partir de `memory/requisitos/<Mod>/` — pattern `../../<path>` |
| Snippets | Copy-pasteable. Usar `<Nome>` só onde leitor substitui. |
| Tabelas | Quando há "verificação → como conferir" ou "sintoma → causa → fix" |
| Linha final | `**Última atualização:** YYYY-MM-DD` |
| Comprimento | 200-350 linhas. Acima de 400 → quebrar em RUNBOOKs por escopo. |

## Anti-padrões (NUNCA fazer)

- ❌ Gerar runbook de tela que NÃO existe — pedir Wagner indicar `Pages/<Mod>/<Tela>.tsx` real ou recusar. Usar **PascalCase** no módulo (`Copiloto`, não `copiloto`); confirmar via `Glob "resources/js/Pages/<Mod>/<Tela>.tsx"` antes de gerar.
- ❌ Copiar conteúdo de outro RUNBOOK sem ler a tela — cada runbook é específico.
- ❌ Inventar atalho que a tela não suporta — listar `—` na coluna Ação.
- ❌ Citar ADR sem ler — confirmar slug existe via Glob `memory/decisions/NNNN-*.md`.
- ❌ Salvar runbook sem rodar mentalmente o passo-a-passo.
- ❌ Pular [CHECKLIST.md](CHECKLIST.md) na fase Validate — é o que separa runbook bom de stub.
- ❌ Usar Windows-style backslashes em paths (Anthropic anti-pattern oficial).

## Gotchas conhecidas

Curadas em [GOTCHAS.md](GOTCHAS.md). Append-only — cada incidente vira pegadinha permanente.

## Skills irmãs

- [`criar-modulo`](../criar-modulo/SKILL.md) — pra runbook de **módulo novo** (estrutura modular nWidart, não tela)
- [`memory-sync`](../memory-sync/SKILL.md) — pra propagar RUNBOOK gerado pro MCP via git push
- [`copiloto-arch`](../copiloto-arch/SKILL.md) — se a tela é do Copiloto, ativar antes
- [`multi-tenant-patterns`](../multi-tenant-patterns/SKILL.md) — se a tela toca dados com `business_id`

## Refs

- [DESIGN.md](../../DESIGN.md) + [ADR 0039](../../memory/decisions/0039-ui-chat-cockpit-padrao.md) — fontes canônicas do Cockpit
- [Infra/RUNBOOK-criar-modulo.md](../../memory/requisitos/Infra/RUNBOOK-criar-modulo.md) — régua de profundidade (validado 2026-05-04)
- [ADS/RUNBOOK-deploy-producao.md](../../memory/requisitos/ADS/RUNBOOK-deploy-producao.md) — exemplo com fases + frontmatter YAML
- [Anthropic Skill Authoring Best Practices](https://platform.claude.com/docs/en/agents-and-tools/agent-skills/best-practices) — Pattern 2, examples pattern, feedback loop
- `design:design-handoff` (Anthropic) — origem das 3 seções novas (states/responsivo/contract)
