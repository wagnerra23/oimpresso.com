---
slug: projectmgmt-index-visual-comparison
title: "ProjectMgmt — Comparativo visual Triage + Inbox (`/project-mgmt/triage` · `/project-mgmt/inbox`)"
type: visual-comparison
module: ProjectMgmt
status: draft
approved_by: null
date: 2026-05-29
canon_reference: tools MCP `triage` + `my-inbox` (CLI/MCP — sem superfície humana antes desta entrega)
inertia_target: resources/js/Pages/ProjectMgmt/Triage/Index.tsx + resources/js/Pages/ProjectMgmt/Inbox/Index.tsx
controller: Modules/ProjectMgmt/Http/Controllers/TriageController@index + InboxController@index
stories: [US-TR-301, US-TR-302, US-TR-303, US-TR-304, US-TR-305, US-TR-306, US-TR-307, US-TR-308]
related_adrs: [0070, 0093, 0094, 0100, 0104, 0107, 0114, 0058]
---

# Visual Comparison — ProjectMgmt Triage + Inbox

> **STATUS DO DOCUMENTO: `draft` — AGUARDANDO aprovação por SCREENSHOT do Wagner (ADR 0107/0114).**
> **Chrome MCP está OFF — as telas NÃO foram renderizadas nem vistas.** Este é o artefato de **preparação** do gate visual (F1.5), **não** a aprovação. O gate MWART continua sinalizando ⚠ (status ≠ approved) **de propósito**: o PR #1940 segue DRAFT até o Wagner ver o SCREENSHOT real e mudar `status: approved` (append-only, decisão humana). Nada aqui declara verde de smoke nem aprovação.
>
> **Tipo de tela:** duas listas operacionais teclado-first (PT-01-aware), superfícies humanas das tools MCP de governança.
> **Personas:** membro do time não-técnico (Felipe/Maiara/Eliana/Luiz) + Wagner Admin — distribuir backlog órfão (Triage) e processar a caixa da manhã (Inbox).
> **Nota gate MWART:** ambas as telas são `Index.tsx` → o gate colapsa pra `index`, então **este doc cobre as duas** (mesma decisão do RUNBOOK-index.md).

## Contexto

Antes desta entrega, `triage` e `my-inbox` só existiam como **tools MCP** (CLI / agente) — sem superfície humana. Não há Blade legacy a comparar; a "fonte canônica" é a **resposta da tool MCP**, e o critério-mestre é **paridade 1:1**: a UI não inventa query, consome o mesmo scope (`McpTask::triage()` na Triage; `WHERE user_id=me` na Inbox). O comparativo abaixo confronta **tool MCP (texto) → Inertia React (Constituição UI v2)**.

A entrega herda o padrão já vivo nas telas irmãs em prod do mesmo módulo (Board PMG-001..007, MyWork, Backlog): AppShellV2 + PageHeader + KpiGrid/KpiCard + tokens `PRIORITY_BADGE` + atalhos J/K + ⌘K global (PMG-002).

## Matriz consolidada (tool MCP → React)

| Item | Tool MCP (texto) | React (Constituição UI v2) | Peso | Score |
|---|:-:|:-:|:-:|:-:|
| KPIs no topo | ausente (texto puro) | Triage 4 KpiCards / Inbox 2 KpiCards (`Inertia::defer`) | 4 | aguardando screenshot |
| Atribuição inline (Triage) | comando `tasks-update` | selects inline owner/prio/cycle/epic + otimismo | 5 | aguardando screenshot |
| Agrupamento por tipo (Inbox) | lista linear | 7 grupos ordenados (mention→...→blocked_resolved) | 4 | aguardando screenshot |
| Deep-link pra task | n/a | Enter/click → `/board?task=ID` (DetailSheet) | 4 | aguardando screenshot |
| Tokens de prioridade | texto `p0..p3` | `PRIORITY_BADGE` canon (reuso Board) | 4 | aguardando screenshot |
| Atalhos teclado | n/a | J/K + Enter + R/Shift+R (Inbox) + ⌘K global | 4 | aguardando screenshot |
| Empty state PT-BR | n/a | "Nada pra triar" / "Caixa de entrada vazia" (sem emoji) | 2 | aguardando screenshot |
| Erro otimista + rollback | n/a | banner âmbar inline (auto-dismiss 5s) | 3 | aguardando screenshot |
| Multi-tenant Tier 0 | scope MCP | Triage por-projeto · Inbox por-user (`user_id`) | 5 | aguardando screenshot |
| Dark mode | n/a | classes `dark:` em banners/chips | 1 | aguardando screenshot |

> Coluna **Score = "aguardando screenshot"** em todas as linhas: a nota só é preenchida **depois** que o Wagner vê o SCREENSHOT renderizado (ADR 0114 — aprova screenshot, não tabela). Nenhum número é inventado aqui.

## 8 dimensões avaliadas (canon MWART — set do gate)

### 1. Layout

- **Triage:** PageHeader (título "<Projeto> — Triagem" + subtitle contadores + hint atalhos + "Ver no Board →") → `<KpiGrid cols={4}>` → `<Card>` com tabela `grid-cols-[minmax(0,1fr)_140px_150px_150px_150px]` (Task / Dono / Prioridade / Cycle / Epic). Em mobile a grid empilha (`grid-cols-1`) com labels por campo.
- **Inbox:** PageHeader (título "Caixa de entrada" + subtitle + toggle lidas + "marcar todas") → `<KpiGrid cols={2}>` → seções por tipo, cada item em card `rounded-lg border bg-card`.
- Cabe em 1280px sem scroll horizontal; herda Shell (AppShellV2) + Fundações.

**Decisão MWART:** estrutura PT-01-aware (lista operacional). **PENDENTE** confirmação por screenshot do Wagner.

### 2. Hierarquia visual

- Título via `<PageHeader>` canon (não `font-bold` cru). Subtitle em `text-muted-foreground` com contadores.
- KpiCard com tone semântico (`info`/`warning`/`success`/`default`) conforme valor > 0.
- Chips de motivo (Triage) e badges de grupo (Inbox) em `text-[10px]/[11px]` — peso visual menor que o título da task.
- Item não-lido (Inbox) com peso cheio; lido → `opacity-60`. Linha/item focado → `ring-1 ring-blue-400/60`.

**Decisão MWART:** hierarquia herda tokens das Fundações. **PENDENTE** screenshot.

### 3. Densidade informacional

- **Triage:** linha compacta (`py-3`) com id+título+chips na 1ª coluna e 4 selects `h-8`. ~12-15 linhas no fold.
- **Inbox:** items `p-3` agrupados, `gap-1.5` dentro do grupo, `gap-5` entre grupos. Respira sem gordura.
- Defer carrega listas (SPA-feel) sem bloquear o header.

**Decisão MWART:** densidade adequada à operação teclado-first. **PENDENTE** screenshot.

### 4. Iconografia

- Lucide canon: Triage usa `Inbox`/`UserX`/`HelpCircle`/`Layers`/`CheckCircle2`; Inbox mapeia tipo→ícone (`AtSign`/`UserPlus`/`Send`/`RefreshCw`/`MessageSquare`/`Clock`/`CheckCircle2`) + `BellOff`/`CheckCheck`.
- Tamanho consistente (10–28px), `text-muted-foreground` salvo destaque semântico (empty-state verde).
- Sem emoji em copy (AP corrigido — empty-states agora PT-BR limpo).

**Decisão MWART:** iconografia canon Lucide, sem emoji. **PENDENTE** screenshot.

### 5. Estados (loading / empty / error / success)

| Estado | UI | Trigger |
|---|---|---|
| Loading | KpiCards + lista resolvem após defer | `Inertia::defer` pendente |
| Empty Triage | "Nada pra triar" + CheckCircle2 + dashed box | `visible.length === 0` |
| Empty Inbox | "Caixa de entrada vazia" / "Nada na caixa." + BellOff | `inbox.length === 0` |
| Otimista | valor novo na hora (Triage) / opacity (Inbox) | ação em vôo |
| Erro | banner âmbar inline auto-dismiss 5s + rollback | PATCH !ok / rede |
| Success | task some (Triage `still_triage=false`) / contador cai (Inbox) | PATCH ok |
| Focado | `ring-blue-400/60` + `aria-current` | J/K |

**Decisão MWART:** 6 estados cobertos por tela. **PENDENTE** screenshot.

### 6. Atalhos

- **J/K** navega (mecânica inline espelhada de `Board/Index.tsx` + `MyWork/Index.tsx` — handler `keydown` com guard `isTyping`).
- **Enter** abre a linha/item focado no Board (`?task=ID`).
- **R** marca lida / **Shift+R** marca todas (Inbox — igual MyWork foco inbox).
- **⌘K / Ctrl+K** = palette global, **dono do AppShellV2** (PMG-002) — NÃO re-registrado nas telas (evita duplo-toggle). Hint visível no PageHeader.

**Decisão MWART:** atalhos canônicos PT-01 reusando o padrão do módulo (não inventa). **PENDENTE** screenshot.

### 7. Persistência

- Estado efêmero (otimismo `optimistic`/`optimisticRead`, foco `selectedId`, banner de erro) vive em React state — não persiste entre reloads por design (a fila/inbox é reconciliada pelo servidor).
- Reconciliação via `router.reload({ only:[...], preserveScroll:true })` + polling 30s + on-focus.
- **Sem `sessionStorage`** (anti-padrão). Toggle `show_read` da Inbox vive na URL (`?show_read=1`), não em storage.

**Decisão MWART:** persistência alinhada ao canon (URL pra filtro, server pra verdade). **PENDENTE** screenshot.

### 8. Componentes (shared canon)

- `<PageHeader>` + `<KpiGrid>` + `<KpiCard>` (shared) — mesma família do Board/MyWork.
- `@/Components/board/badges` (`PRIORITY_BADGE`) reusado da Board — zero hex cru.
- `@/Components/ui/{select,card,button,badge}` shadcn.
- ⌘K via `@/Components/CommandPalette` montado pelo AppShellV2.

**Decisão MWART:** 100% componentes shared canon, zero componente bespoke novo. **PENDENTE** screenshot.

## Matriz completa — 15 dimensões Constituição UI v2

> Documenta as 15 dimensões do framework (ADR UI-0013 + plugin Claude Design). As 8 acima são o subset que o gate MWART conta estruturalmente; as 7 adicionais completam o framework. Todas marcadas **PREP** (preparado) — a **nota** sai só com o SCREENSHOT do Wagner.

| # | Dimensão (Constituição UI v2) | Como a entrega trata | Status |
|---|---|---|:-:|
| 1 | Layout / grid | PT-01-aware, herda Shell; tabela grid (Triage) / grupos (Inbox) | PREP |
| 2 | Hierarquia visual | PageHeader canon + tones semânticos + peso lido/não-lido | PREP |
| 3 | Densidade | linhas/items compactos, defer SPA-feel | PREP |
| 4 | Iconografia | Lucide canon, **sem emoji** (AP corrigido) | PREP |
| 5 | Estados | loading/empty/error/success/otimista/focado | PREP |
| 6 | Atalhos | J/K + Enter + R/Shift+R + ⌘K global (reuso, não inventa) | PREP |
| 7 | Persistência | React state + URL (show_read) + reload server; sem sessionStorage | PREP |
| 8 | Componentes shared | PageHeader/KpiGrid/KpiCard/badges/CommandPalette | PREP |
| 9 | Cor / tokens | tokens Fundações + `PRIORITY_BADGE`; zero hex cru | PREP |
| 10 | Tipografia | escala da Fundação (sem font-bold cru) | PREP |
| 11 | Espaçamento | escala canon (`gap-3/5`, `py-3`, `mt-4`) | PREP |
| 12 | Acessibilidade | `role="alert"` nos banners, `aria-current` no foco, `aria-label` em ações, alvo ≥360px | PREP |
| 13 | Responsividade / mobile | grid empilha <md com labels por campo; hint atalhos `hidden md:inline` | PREP |
| 14 | Dark mode | classes `dark:` em banners/chips/badges | PREP |
| 15 | Microcopy PT-BR | labels/empty-states/erros 100% PT-BR, sem emoji | PREP |

## Multi-tenant Tier 0 (ADR 0093 + 0070)

- **Triage:** `mcp_tasks` é governança GLOBAL repo-wide (sem `business_id`). Escopo por-projeto (`resolveProject`, default COPI) — idêntico Board/Backlog/MyWork.
- **Inbox:** `mcp_inbox_notifications` é por-pessoa. Toda query/escrita escopada por `auth()->id()`; `markRead` de id alheio → **404** (não 403, evita enumeração); `markAllRead` `update` escopado. Não vaza entre usuários.

**Decisão MWART:** Tier 0 compliant por design (validado nos testes `TriageControllerTest` + `InboxControllerTest` + `MultiTenantProjectTest`).

## PII / LGPD

Sem PII de cliente nessas telas (tasks + notificações de governança interna). `actor_name` = `users.first_name` (time, não cliente). Body de notificação é texto de governança. Leitura de lista não logada; `assign` registra evento auditável via `TaskCrudService`.

## Score consolidado

**NÃO PREENCHIDO** — a nota final só é atribuída pelo Wagner **após ver o SCREENSHOT renderizado** (ADR 0114: aprova-se screenshot, não tabela). Enquanto Chrome MCP estiver off e o gate visual não rodar, este doc permanece `status: draft` e **sem nota**. Preencher nota aqui sem screenshot seria burlar o gate — não fazemos isso.

| Dimensão | Score | Peso | Contribuição |
|---|:-:|:-:|:-:|
| (todas) | aguardando screenshot | — | — |

## Gaps remanescentes (backlog)

| US futura | Gap | Prioridade |
|---|---|---|
| (futura) | Badge realtime Inbox via Centrifugo (canal `inbox.{user_id}`, ADR 0058) | P2 |
| (futura) | Bulk-ops multi-seleção na Triage (defer ADR 0070 Tier 3) | P2 |
| (futura) | Refinamento mobile < 1100px (cards) | P2 |
| US-TR-308 | Chips de ADRs/SPECs relacionados (memory-links) — vive no DetailSheet do Board | P2 |

## Próximo passo (gate visual — ADR 0107/0114)

1. Ligar Chrome MCP (ou Claude Preview) e renderizar `/project-mgmt/triage` + `/project-mgmt/inbox`.
2. Capturar SCREENSHOT das duas telas (cheia + empty + foco J/K + erro otimista).
3. Wagner revisa o SCREENSHOT (não esta tabela) — aprova ou pede ajuste.
4. **Só então** mudar `status: draft` → `status: approved` + `approved_by: wagner` + preencher a nota — e o PR sai de DRAFT.

## Refs

- [RUNBOOK-index.md](RUNBOOK-index.md) — runbook das 2 telas
- [SPEC.md](SPEC.md) — US-TR-301..308
- [ADR 0107 — gate visual F1.5/F3](../../decisions/0107-emendation-0104-visual-comparison-gate-f3.md)
- [ADR 0114 — aprovação por SCREENSHOT (Cowork loop)](../../decisions/0114-prototipo-ui-cowork-loop-formalizado.md)
- [ADR UI-0013 — Constituição UI v2](../_DesignSystem/adr/ui/0013-constituicao-ui-v2-camadas.md)
- [PT-01 Lista](../_DesignSystem/padroes-tela/PT-01-Lista.md)
- Charters: [`Triage/Index.charter.md`](../../../resources/js/Pages/ProjectMgmt/Triage/Index.charter.md) · [`Inbox/Index.charter.md`](../../../resources/js/Pages/ProjectMgmt/Inbox/Index.charter.md)
- Padrão fonte: `Board/Index.tsx` (J/K + ⌘K via AppShellV2 PMG-002) + `MyWork/Index.tsx` (J/K + R)
- PR #1940 — code-complete; segue DRAFT aguardando gate visual Wagner
