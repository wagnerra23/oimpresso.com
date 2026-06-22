---
page: /team-mcp/cc-sessions
component: resources/js/Pages/team-mcp/CcSessions/Index.tsx
owner: wagner
status: draft
last_validated: "2026-06-16"
parent_module: TeamMcp
related_adrs:
  - "0053-mcp-server-governanca-como-produto"
  - "0081-identity-mesh-mcp-actors"
  - "0093-multi-tenant-isolation-tier-0"
  - "0094-constituicao-v2-7-camadas-8-principios"
  - "0104-processo-mwart-canonico-unico-caminho"
  - "0114-prototipo-ui-cowork-loop-formalizado"
related_ficha: memory/requisitos/TeamMcp/cc-sessions-visual-comparison.md
tier: A
charter_version: 1
---

# Page Charter — `/team-mcp/cc-sessions` (DRAFT)

> Criado no PR **Forja PR-2** (re-skin DS v6, 2026-06-16). Persona: Wagner [W] (admin, `jana.cc.read.all`) + cada dev (vê só as próprias). Backend: `CcSessionsController` (Inertia::defer). Ref visual: [cc-sessions-visual-comparison.md](../../../../../memory/requisitos/TeamMcp/cc-sessions-visual-comparison.md).

## Mission

KB read-only de **atividade do Claude Code do time** (sessões `mcp_cc_*`): feed cronológico de sessões + drawer de thread, na gramática Changelog Forja sob DS v6. Projeção fiel — **sem dado fantasma**. Operação primária: escanear quem rodou o quê, quanto custou, e abrir a thread sob demanda. Toda sessão é **agente (Claude Code)** em nome de um **humano (dev)** — marcado explicitamente.

## Goals — Features (faz)

- **Feed cronológico** de sessões (dot de status + dev + projeto/branch + data relativa + summary + meta msgs/tokens/custo/duração).
- **Drawer** (640px) com a thread (`show`): header meta + summary + mensagens (≤500, flag truncated), bolhas por tipo (DS-clean), tool chips.
- **Filtros**: busca FULLTEXT summary (debounce 350ms), dev (se `read_all`), status, projeto. Limpar.
- **KPIs**: sessões hoje/total, devs ativos hoje, custo hoje/30d, top tools.
- **Paginator** 25/pg + links.
- **Selo agente vs humano**: `Bot` (Claude Code + cc_version) + `User` (dev).
- **Atalhos** J/K navegar · Enter/click abre drawer · `/` busca · Esc fecha.

## Non-Goals — Features (NÃO faz)

- ❌ Busca profunda cross-dev em `content_text` (`/cc-sessions/search` existe como API; UI fica pra PR futura).
- ❌ Editar/curar sessão (curate flag preservada, sem UI de mutação no PR-2).
- ❌ Mostrar PII/segredo de thread além do que `show` já retorna.
- ❌ Tocar AppShellV2/PageHeader canon.

## UX targets

- DS v6: tokens semânticos (bolha por tipo via /opacity, status Stripe-dot), **sem cor crua**, ramp `--fs`, `tabular-nums` em custo/tokens/msgs.
- Drawer 640px (thread precisa largura); slide-in `--ease`; loading/empty/erro.
- Locators `data-testid`.

## Anti-hooks (NÃO faz automaticamente)

- ❌ NÃO escreve nada (tela 100% read-only).
- ❌ NÃO loga/expõe PII além do `show`; respeita RBAC `acessivelPara` no backend.
- ❌ NÃO usa `<select>` nativo (Components/ui).

## Restrições Tier 0

- Permissão `copiloto.cc.read.team` no construtor; `jana.cc.read.all` libera ver todos, senão só próprias (backend `acessivelPara`).
- Multi-tenant: scope de acesso é do backend (não reimplementar no front).

## Métricas de sucesso (validação Wagner)

- ✅ Feed lista sessões em ordem cronológica com custo/msgs corretos.
- ✅ Click/Enter abre drawer com a thread real (≤500, aviso truncado).
- ✅ Selo distingue Claude Code (agente) do dev (humano).
- ✅ Filtros + busca + paginação preservados; RBAC intacto.
- ✅ Sem cor crua (conformance/foundation/eslint-baseline verdes).
