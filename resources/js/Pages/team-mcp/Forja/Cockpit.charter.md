---
page: /forja
component: resources/js/Pages/team-mcp/Forja/Cockpit.tsx
owner: wagner
status: draft
last_validated: "2026-06-16"
parent_module: TeamMcp
related_adrs:
  - "0114-prototipo-ui-cowork-loop-formalizado"
  - "0081-identity-mesh-mcp-actors"
  - "0093-multi-tenant-isolation-tier-0"
  - "0094-constituicao-v2-7-camadas-8-principios"
related_ficha: memory/requisitos/TeamMcp/forja-cockpit-visual-comparison.md
tier: A
charter_version: 1
---

# Page Charter — `/forja` cockpit (DRAFT · Onda Forja PR-A · shell)

> Criado no PR **Forja PR-A** (2026-06-16). Cockpit do cowork loop (humano ↔ agente). **Absorção em TeamMcp** (não é módulo novo). Esta PR entrega o **shell navegável** — sidebar "Forja" + topnav de 6 abas + rotas `/forja/*` + landing. As 6 abas reais entram 1 PR cada (B–G). Persona: Wagner [W] (superadmin, `copiloto.mcp.usage.all`). Backend: `ForjaController` (TeamMcp). Ref: [forja-cockpit-visual-comparison.md](../../../../memory/requisitos/TeamMcp/forja-cockpit-visual-comparison.md).

## Mission

Cockpit **read-only** de observabilidade/governança do próprio loop de desenvolvimento. **Projeta** estado que já existe (`mcp_tasks` + git/PR/ADR/sessão + gates/memory-health) — **sem dado fantasma**. Header fixo (Forja + subtítulo do loop) + 6 abas: Triagem · Backlog · Quadro (F0→F4) · Changelog · MCP · Saúde.

## Goals — Features (faz)

- **Shell navegável** (PR-A): entry "Forja" na sidebar + topnav de 6 abas + rotas `/forja`, `/forja/{backlog,quadro,changelog,mcp,saude}` + landing (Triagem).
- Cada rota renderiza o mesmo shell `Cockpit.tsx` com a aba ativa via prop `tab` (topnav highlight por URL).
- Abas reais entregues incrementalmente (B Saúde · C Changelog · D Backlog · E Quadro · F Triagem+dossiê · G MCP).

## Non-Goals — Features (NÃO faz)

- ❌ Tabela/entidade nova — issues = projeção sobre `mcp_tasks` (Tier 0: sem schema sem ADR mãe).
- ❌ Enforce de permissão de tool — a aba MCP é **design/MOCKADO**; o enforce real é do servidor TeamMcp.
- ❌ Merge ou `constituicao.edit` pela UI — soberania: merge só `[W2]`, ADR/PROTOCOL/BRIEFING só `[W]`.
- ❌ Filtro business_id — cockpit é repo-wide intencional (ADR 0093).

## UX targets

- DS v6: roxo canon na aba ativa / primárias, status Stripe-dot, `tabular-nums`, ramp `--fs`, **sem cor crua**.
- Topnav auto via `config/core_topnavs.php['Forja']` + `useAutoModuleNav` (raiz `/forja`, segmento próprio pra não colidir com `/team-mcp`).
- Layout via `inline-flex`/primitivos; PageHeader **canon** (`@/Components/PageHeader`). Locators `data-testid`.

## Anti-hooks (NÃO faz automaticamente)

- ❌ NÃO escreve nada nesta onda (read-only). ❌ NÃO inventa métrica/issue. ❌ NÃO persiste/loga token raw (Tier 0 ADR 0081).

## Restrições Tier 0

- Permissão `copiloto.mcp.usage.all` no construtor do `ForjaController`.
- Repo-wide cross-business INTENCIONAL (ADR 0093) — governança da plataforma.
- `mcp_*` sem `business_id` por design.

## Métricas de sucesso (validação Wagner)

- ✅ As 6 rotas `/forja/*` respondem (sem 500 / tela branca).
- ✅ Entry "Forja" aparece na sidebar e o topnav de 6 abas navega + destaca a ativa.
- ✅ Sem cor crua / PageHeader canon (conformance/foundation/layout/pageheader verdes).
- ✅ Acesso negado (403) sem `copiloto.mcp.usage.all`.
