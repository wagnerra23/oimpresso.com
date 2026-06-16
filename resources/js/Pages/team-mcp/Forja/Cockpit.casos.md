---
casos: Forja · cockpit do cowork loop · /forja
irmaos: Cockpit.charter.md (lei) · Cockpit.tsx (tela)
tecnica: Caso de uso = narrativa + critério de aceite verificável
owner: wagner
last_run: "2026-06-16"
---

# Casos de uso — /forja (cockpit Forja · shell)

> **Status:** ✅ passa (provado por teste) · 🧪 em teste (Pest escrito, aguarda run verde) · ⬜ não verificado · ❌ quebrou.

> Onda Forja **PR-A**: SHELL navegável (sidebar + 6 abas + rotas). As abas reais entram 1 PR cada (B–G). Persona: Wagner [W] (superadmin). Read-only nesta onda. Referência: [forja-cockpit-visual-comparison.md](../../../../memory/requisitos/TeamMcp/forja-cockpit-visual-comparison.md).

## UC-FORJA-01 — As 6 rotas /forja respondem (shell no ar)
Status: ⬜ (smoke pós-merge — abrir cada rota em prod)
Antes desta PR não havia rota `/forja`. `ForjaController` serve `/forja` (Triagem), `/forja/backlog`, `/forja/quadro`, `/forja/changelog`, `/forja/mcp`, `/forja/saude`.
**Pronto quando:** cada uma das 6 rotas renderiza o shell (sem 500 / tela branca).

## UC-FORJA-02 — Topnav de 6 abas aparece e navega
Status: ⬜ (manual/visual)
O topnav vem de `config/core_topnavs.php['Forja']` via `useAutoModuleNav` (casa raiz `/forja`). 6 abas: Triagem · Backlog · Quadro · Changelog · MCP · Saúde.
**Pronto quando:** as 6 abas aparecem no header, navegam entre as rotas e destacam a ativa por URL.

## UC-FORJA-03 — Entry "Forja" na sidebar
Status: ⬜ (manual/visual)
`DataController@modifyAdminMenu` injeta o dropdown "Forja" (ícone martelo, atalho `G F`, ghosts = as 6 abas), separado do hub Equipe.
**Pronto quando:** "Forja" aparece na sidebar e leva ao cockpit; ghosts listam as 6 abas.

## UC-FORJA-04 — Sem colisão de topnav com /team-mcp
Status: 🧪 (cobertura: raiz `/forja` ≠ `/team-mcp` no `useAutoModuleNav`)
`useAutoModuleNav` casa pelo 1º segmento da URL. Forja usa raiz própria `/forja` pra não herdar o topnav do hub Equipe (`/team-mcp`).
**Pronto quando:** em `/forja/*` o topnav mostrado é o da Forja (6 abas), não o da Equipe.

## UC-FORJA-05 — Read-only (o shell não muta nada)
Status: ⬜ (manual)
Nenhuma rota desta onda escreve estado; todas são GET de render.
**Pronto quando:** não há ação na tela que escreva no banco.

## UC-FORJA-06 — DS v6 (sem cor crua · PageHeader canon)
Status: 🧪 (cobertura: eslint `ds/*` = 0 + conformance + layout + pageheader ratchets)
PageHeader canon (`@/Components/PageHeader`), layout via `inline-flex`, tokens semânticos, zero paleta crua / `rounded-xl+`.
**Pronto quando:** `conformance-gate`, `layout-primitives`, `pageheader-gate` e `eslint` verdes pro Cockpit.tsx.

## UC-FORJA-07 — Acesso (auth + permissão)
Status: ⬜ (manual — rota sob `auth` + `copiloto.mcp.usage.all`)
`ForjaController` exige login + `copiloto.mcp.usage.all` (mesma do Scorecard/Team). Repo-wide cross-business intencional (ADR 0093) pro superadmin.
**Pronto quando:** usuário sem `copiloto.mcp.usage.all` recebe 403.
