---
slug: teammcp-cc-sessions-visual-comparison
title: "TeamMcp — Comparativo visual da tela CcSessions (Atividade CC)"
type: visual-comparison
module: TeamMcp
status: approved
approved_by: wagner
approved_at: 2026-06-16
date: 2026-06-16
canon_reference: forja-cowork (forja-page.jsx — view .fj-changelog/.fj-feed)
blade_source: "N/A — tela já é Inertia (re-skin DS v6)"
inertia_target: resources/js/Pages/team-mcp/CcSessions/Index.tsx
pr_branch: feat/forja-pr2-ccsessions
---

# TeamMcp — Comparativo visual · tela **CcSessions** (Atividade CC)

> **F1.5 do MWART V4** · PR-2 da onda **Forja**. Re-skin DS v6 de `/team-mcp/cc-sessions` (`CcSessionsController` → `team-mcp/CcSessions/Index`). Pré-aprovado pelo padrão Forja (Wagner "pode seguir" 2026-06-16). **Frontend-only** — backend (`index`/`show`/`search`) preservado.

## Contexto

Hoje a tela é split-panel (lista + preview lateral) com paginator 25/pg, filtros (busca FULLTEXT summary, dev, status, projeto), KPIs (sessões/devs/custo/top tools) e thread no preview. O PR-2 conforma à gramática **Changelog/Atividade** da Forja: **feed cronológico** + **drawer lateral** pra thread, preservando 100% das features. Selo **agente vs humano**: toda sessão é atividade de **agente (Claude Code)** em nome de um **humano (dev)** — marcado explicitamente (transversal §3).

## Princípio de override (UI-0013) — canon vence

| Aspecto | Atual | Forja | Decisão |
|---|---|---|---|
| Cores das bolhas/tools | paleta crua (`bg-blue-50`, `bg-green-100`…) | hue por tipo | **tokens semânticos /opacity** (primary/info/success/warning/muted) |
| Status sessão | `Badge` bg-fill verde/cinza | dot | **dot Stripe** + label |
| Detalhe | split-panel inline | drawer lateral | **drawer** (Sheet) 640px (thread precisa largura) |
| Header | PageHeader custom | — | PageHeader shared, breadcrumb "Equipe" |

## Matriz de preservação (não perder)

| Feature | PR-2 |
|---|---|
| Paginator 25/pg + links | ✅ |
| Filtros user_id/from/to/q(FULLTEXT summary)/status/project | ✅ (Components/ui/select) |
| KPIs sessions_hoje/total/custo_hoje/30d/devs_ativos/tools_top | ✅ |
| Detalhe `show` (thread ≤500 + truncated flag) | ✅ no drawer |
| Busca debounce 350ms | ✅ |
| RBAC read_all vs próprio · curate | ✅ (props.permissions intactas) |
| Atalhos j/k/↵/Esc/`/` | ✅ |

## Restrição de dados — sem dado fantasma

Projeta só o que `index`/`show` retornam (dev, projeto, branch, cc_version, entrypoint, started/ended, msgs/tokens/custo, status, summary, thread). Nada inventado. Search profundo cross-dev (`/cc-sessions/search` em content_text) **não** entra no PR-2 (endpoint existe mas não estava na UI; fica pra PR futura).

## 15 dimensões (Atual · Forja · Decisão DS v6)

| # | Dimensão | Atual | Forja | Decisão |
|---|---|---|---|---|
| 1 | Layout | split list+preview | feed + drawer | PageHeader › KPIs › Toolbar › **feed cronológico** › Drawer thread |
| 2 | Hierarquia | tabela densa | feed item (dot+ref+resumo+meta) | feed item por sessão; sem ação primária (read-only) |
| 3 | Densidade | linhas tabela | feed item ~64px | item compacto com summary line-clamp-2 |
| 4 | Iconografia | lucide misto | dot+SVG | lucide (`Bot`/`User` selo, `FolderOpen`, `GitBranch`) |
| 5 | Estados | empty/loading/sel | idem | empty (sem ingest) / busca-vazia / loading skeleton / selected / erro |
| 6 | Atalhos | j/k/Esc// | idem | **preservar** j/k/↵/Esc/`/` |
| 7 | Persistência | filtros URL | — | filtros na URL (compartilhável) — mantém |
| 8 | Shared | PageHeader/Kpi | custom | PageHeader, KpiGrid/Card, Sheet (drawer); feed module-local |
| 9 | Tipografia num | mono custo/tokens | mono | `tabular-nums` custo/tokens/msgs; ramp `--fs` |
| 10 | Espaçamento | py-1.5 | gap feed | gap-0 entre items com divisória; drawer px-4 |
| 11 | Cores | **cru** (typeStyle/toolColor) | hue | **tokens semânticos** (bolha por tipo via /opacity; tool = chip neutro) |
| 12 | Microinterações | hover row | dot feed | hover bg-muted; drawer slide-in (--ease); focus ring |
| 13 | Ref aprovada | — | Forja Cowork | ✅ |
| 14 | Benchmark | — | Linear/Vercel activity, GitHub commits feed | feed cronológico de atividade |
| 15 | Persona | — | Wagner+time, desktop | (1) escanear quem fez o quê/quanto custou, (2) selo agente vs humano, (3) thread sob demanda |

## Decisões [W] (pré-aprovado "pode seguir")

1. **Drawer 640px** pra thread (mais largo que issue 560 — conteúdo de conversa).
2. **Feed** substitui split-panel (gramática Changelog Forja).
3. **Tool/bolha** = tokens semânticos (perde paleta por-tool; ganha DS — bolha por tipo via /opacity, tool chip neutro).
4. **Selo**: sessão = agente (Claude Code, `Bot` + cc_version) em nome do humano (`User` + dev). Agente nunca se disfarça de humano.
5. Breadcrumb "Copiloto" → "Equipe".

## Gates antes do F3
- [x] Padrão Forja aprovado ([W] "pode seguir" 2026-06-16).
- [x] PROCESSO_MEMORIA_CC §5 + NÚCLEO lidos (PR-1).
- [x] Charter `Index.charter.md` ao lado.
- [ ] CI: typecheck + eslint/lint-baseline + conformance + foundation + UI-Judge.

---
**Status:** `approved` — implementado no PR `feat/forja-pr2-ccsessions`.
