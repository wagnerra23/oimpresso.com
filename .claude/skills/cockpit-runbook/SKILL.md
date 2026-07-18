---
name: cockpit-runbook
description: Generates a detailed RUNBOOK.md or audits a screen against the Chat Cockpit pattern (ADR 0039) for the oimpresso ERP. Activates when the user asks for "runbook", "playbook" or "receita" of a React/Inertia screen or module (e.g. "runbook da tela X", "playbook do módulo Y", "documenta a Inbox de Tarefas"), OR asks to "auditar/comparar tela contra Cockpit". Outputs `memory/requisitos/<Mod>/RUNBOOK-<tela>.md` with 11 mandatory sections, executable PT-BR snippets, clickable links, canonical CSS tokens. Calibrated against the 13 existing RUNBOOKs (reference: `Infra/RUNBOOK-criar-modulo.md`) and the `design:design-handoff` Anthropic spec. Skip for ADRs, SPECs, audits or session logs.
trust_level: L2
owner: wagner
parent_mission: meta-skill-roi-erp-autonomo
charter_adr: 0080
tier: C
parent_adr: 0095
---

# Skill — Gerador de RUNBOOK de tela/módulo do Cockpit

## Quando ativa (4 modos)

| Modo | Gatilho típico | Output |
|---|---|---|
| **A. Generate** | "runbook da tela X", "playbook do módulo Y", "documenta a Inbox" | `memory/requisitos/<Mod>/RUNBOOK-<tela>.md` novo |
| **B. Audit** | "audita a tela X contra Cockpit", "auditar tela contra Cockpit" | Relatório `file:line` com violações + **score 0-100** (CHECKLIST.md §G) |
| **C. Compare** | "compara tela X com tela Y", "diferença entre X e Y", "estado da arte vs atual" | Tabela cross-page por dimensão + score lado-a-lado + recomendação de refactor |
| **D. Refresh** | "atualiza o RUNBOOK da tela X", "esse runbook tá desatualizado" | Re-gera mantendo seções não-stale + bumpa `Última atualização` |

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
4. [_DesignSystem ADR UI-0023](../../memory/requisitos/_DesignSystem/adr/ui/0023-sidebar-dark-fixo-preto-definitivo-supersede-0019.md) — sidebar PRETA (dark-fixo) nos dois modos
5. [_DesignSystem/SPEC.md](../../memory/requisitos/_DesignSystem/SPEC.md) — regras R-DS-001..N (tokens, shadcn, lucide, dark mode)
6. [_DesignSystem/ARCHITECTURE.md](../../memory/requisitos/_DesignSystem/ARCHITECTURE.md) — visão arquitetural
7. [Infra/RUNBOOK-criar-modulo.md](../../memory/requisitos/Infra/RUNBOOK-criar-modulo.md) — régua de profundidade
8. **A própria tela** — `resources/js/Pages/<Mod>/<Tela>.tsx` (módulo em **PascalCase**: `Copiloto`, `Project`, `Repair`, não lowercase) + Components shared importados

## Estrutura da skill (progressive disclosure)

```
.claude/skills/cockpit-runbook/
├── SKILL.md         (este arquivo — overview ~180 linhas)
├── TEMPLATE.md      (template completo copy-paste com 11 seções + placeholders)
├── EXAMPLES.md      (1 input + 1 output end-to-end + dicas de profundidade)
├── CHECKLIST.md     (DoD + audit rules + UX heurísticas + score 0-100)
├── BENCHMARKS.md    (catálogo de SaaS de referência por categoria de tela — Modo B/C)
└── GOTCHAS.md       (pegadinhas curadas append-only)
```

**Por que dividido:** Anthropic recomenda Pattern 2 (domain-specific organization) — `TEMPLATE.md` só carrega quando vai gerar, `BENCHMARKS.md` só em Modo B/C quando precisar comparar, `GOTCHAS.md` só quando há suspeita de pegadinha. Reduz tokens iniciais.

## Output esperado

**Caminho:** `memory/requisitos/<Mod>/RUNBOOK-<tela-kebab-case>.md`
Exemplos: `memory/requisitos/Jana/RUNBOOK-chat-cockpit.md`, `memory/requisitos/Project/RUNBOOK-inbox-tarefas.md`.

**Frontmatter YAML obrigatório** (espelha padrão `ADS/RUNBOOK-deploy-producao.md`):

```yaml
---
slug: <mod-lower>-runbook-<tela-kebab>
title: "<Mod> — Runbook da tela <Nome legível>"
type: runbook
module: <Mod>
owner: W                        # obrigatório — enum W/F/M/L/E (runbook.schema.json)
status: ativo                   # enum: rascunho|ativo|arquivado|historical (NUNCA "active")
last_validated: "<YYYY-MM-DD>"  # obrigatório, STRING quoted — data crua vira Date; alerta se >30d
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
2. Aplicar audit rules de [CHECKLIST.md](CHECKLIST.md) — cada regra checa contra ADR 0039 + R-DS-001..N + **§F (UX heurísticas Nielsen)**
3. **Calcular Score 0-100** ([CHECKLIST.md §G](CHECKLIST.md)) — pondera DS (40%) + ADR (30%) + UX (30%)
4. (Opcional) Comparar contra benchmark da categoria de tela em [BENCHMARKS.md](BENCHMARKS.md)
5. Output em formato `file:line — regra violada — fix sugerido` + bloco final `## Score`:

```
[CRITICAL] resources/js/Pages/<Mod>/Dashboard.tsx:42 — R-DS-002 (bg-blue-500) — fix: bg-primary
[CRITICAL] resources/js/Pages/<Mod>/Dashboard.tsx:88 — R-DS-001 (<button>) — fix: importar <Button>
[WARN]     resources/js/Pages/<Mod>/Dashboard.tsx:120 — ADR 0039 §3 — coluna direita ausente apesar de contexto vinculado
[UX-WARN]  resources/js/Pages/<Mod>/Dashboard.tsx:45 — H8 (minimalist) — header com 5 elementos competindo
[UX-CRITICAL] resources/js/Pages/<Mod>/Dashboard.tsx:88 — Q5 — empty state sem CTA

## Score
| Categoria | Score | Detalhe                                                  |
|-----------|-------|----------------------------------------------------------|
| DS (40)   | 22/40 | 3 CRITICAL, 5 WARN, 2 INFO                               |
| ADR (30)  | 15/30 | 3 violations (§3 LinkedApps, §2 J/K, §4 localStorage)    |
| UX (30)   | 22/30 | 1 CRITICAL (Q5), 2 WARN (H8, H5)                         |
| **TOTAL** | **59/100** | 🟠 Precisa refactor — corrigir 3 CRITICAL antes de mergear |
```

Esse modo NÃO salva arquivo — entrega no chat. Wagner decide se ajusta a tela ou registra exceção.

## Modo C — Compare 2 telas

Quando o gatilho é "compara tela X com tela Y" ou "diferença entre X e Y" ou "antes vs depois refactor", **comparar 2 implementações** ao invés de auditar uma só.

**Quando faz sentido:**
- Quando 2 telas implementam pattern similar (Whatsapp/Conversations/Index vs Copiloto/Cockpit — ambas Chat Cockpit) → detectar duplicação e divergência
- "Antes vs depois" do mesmo refactor (`git show HEAD:path` vs `path`) — quantificar o salto
- Validar candidato a "estado da arte" — comparar tela proposta como referência vs outras do mesmo módulo

**Workflow:**

1. Read de ambas as Pages + componentes shared importados (em paralelo)
2. Internamente, rodar Modo B (audit + score) em cada uma
3. Identificar **dimensões cross-page** (8 canônicas):

| Dimensão | O que comparar |
|---|---|
| Tokens semânticos | Qual usa mais cores cruas? Quem consome `var(--bubble-me)`? |
| Iconografia | lucide-react vs emoji |
| Atalhos teclado | J/K/E/A registrados? `/` foca search? |
| Persistência | localStorage com prefixo `oimpresso.`? sessionStorage? URL only? |
| Componentes shared | `EmptyState`, `PageHeader`, `Badge` reusados ou duplicados? |
| Real-time | Centrifugo wired? Presence visível? |
| Acessibilidade | Focus visible (R-DS-006)? aria-label? Tab order? |
| Responsivo | Mobile usa? Drawer ou stack vertical? |

4. Output: tabela diferenças + score lado-a-lado + **recomendação de direção de refactor**:

```
## Compare: <Tela A> vs <Tela B>

| Dimensão | <A> | <B> |
|---|---|---|
| Score total | 67/100 🟡 | 82/100 🟡 |
| Tokens semânticos | 12 cores cruas | 3 cores cruas |
| Iconografia | 8 emojis | 100% lucide |
| Atalhos teclado | nenhum | J/K/E/A + ⌘K |
| Persistência | URL only | localStorage `oimpresso.<mod>.*` |
| EmptyState shared | custom inline | usa `<EmptyState/>` |
| Real-time | mock typing | Centrifugo wired |
| Focus visible | ausente em TabPill | ring shadcn em todos |
| Responsivo mobile | sidebar oculta <lg | drawer + stack |

## Recomendação
**Refator de A → B:** A tem 5 dimensões abaixo do estado de B. Priorizar:
1. Migrar emojis pra lucide (1h)
2. Adicionar atalhos J/K/E/A (2h)
3. Persistir tab+search em localStorage (30min)

**Anti-refactor (manter divergência):**
- A tem fluxo X específico — sidebar custom em vez de LinkedAppsPanel é intencional → registrar em ADR per-tela
```

Esse modo também NÃO salva arquivo — entrega no chat.

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

- [`mwart-process`](../mwart-process/SKILL.md) — **Tier A always-on**, processo MWART canônico ([ADR 0104](../../memory/decisions/0104-processo-mwart-canonico-unico-caminho.md)) em 5 fases. Esta skill (`cockpit-runbook`) executa **F1 PLAN** (Modo Generate) e **F3/F4 Audit** (Modo B/C).
- [`mwart-quality`](../mwart-quality/SKILL.md) — pré-flight checks na F3 FRONTEND (auto-ativa quando começa a codar Page Inertia)
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
