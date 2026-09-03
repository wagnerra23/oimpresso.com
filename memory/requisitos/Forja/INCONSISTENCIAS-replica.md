# Forja — inconsistências pós-réplica (ADR 0388)

> **O que é.** A lista que a máquina gera DEPOIS de aplicar o protótipo: cada linha é uma regra de
> conformidade do DS que a cópia fiel viola, com o dono da regra e a contagem. Nada aqui bloqueia a
> aplicação — é o que o Code resolve depois, ou o que [W] aceita como decisão (`status: aceita`).
> **Gerado por máquina** — não edite contagem; mude só `status`/`nota` no JSON em
> `governance/replica-inconsistencias/forja.json` e regenere.
>
> Gerado em 2026-09-02 · comando: `node scripts/governance/replica-inconsistencias.mjs --modulo Forja --prototipo …` · **121 aberta(s)** de 121.
> `origem = aplicado` mede o que está no repo; `origem = prototipo` mede o que VAI entrar quando a onda copiar o JSX.

| status | regra | arquivo | contagem | exemplo | dono da regra | origem |
|---|---|---|---:|---|---|---|
| 🔴 aberta | `FLEX-CRU` | `Modules/Forja/Resources/js/Pages/ads/Admin/Projects.tsx` | 4 |  | layout-primitives-guard.mjs | aplicado |
| 🔴 aberta | `R3` | `Modules/Forja/Resources/js/Pages/ads/Admin/ProjectShow.tsx` | 1 | • | UiLintCommand.php R3 | aplicado |
| 🔴 aberta | `FLEX-CRU` | `Modules/Forja/Resources/js/Pages/ads/Admin/ProjectShow.tsx` | 8 |  | layout-primitives-guard.mjs | aplicado |
| 🔴 aberta | `R3` | `Modules/Forja/Resources/js/Pages/ads/Admin/TeamScopes.tsx` | 2 | → | UiLintCommand.php R3 | aplicado |
| 🔴 aberta | `FLEX-CRU` | `Modules/Forja/Resources/js/Pages/ads/Admin/TeamScopes.tsx` | 1 |  | layout-primitives-guard.mjs | aplicado |
| 🔴 aberta | `R3` | `Modules/Forja/Resources/js/Pages/ads/Admin/Tools.tsx` | 1 | ⚠ | UiLintCommand.php R3 | aplicado |
| 🔴 aberta | `FLEX-CRU` | `Modules/Forja/Resources/js/Pages/ads/Admin/Tools.tsx` | 8 |  | layout-primitives-guard.mjs | aplicado |
| 🔴 aberta | `R3` | `Modules/Forja/Resources/js/Pages/Forja/Activity/Index.tsx` | 1 | → | UiLintCommand.php R3 | aplicado |
| 🔴 aberta | `R4` | `Modules/Forja/Resources/js/Pages/Forja/Activity/Index.tsx` | 1 | PageHeader=sim · DataTable=não | UiLintCommand.php R4 | aplicado |
| 🔴 aberta | `FLEX-CRU` | `Modules/Forja/Resources/js/Pages/Forja/Activity/Index.tsx` | 6 |  | layout-primitives-guard.mjs | aplicado |
| 🔴 aberta | `R1` | `Modules/Forja/Resources/js/Pages/Forja/Aprovacoes/Index.tsx` | 3 | #5283 · #5288 | UiLintCommand.php R1 · conformance-gate | aplicado |
| 🔴 aberta | `R3` | `Modules/Forja/Resources/js/Pages/Forja/Aprovacoes/Index.tsx` | 3 | ⚠ → | UiLintCommand.php R3 | aplicado |
| 🔴 aberta | `R4` | `Modules/Forja/Resources/js/Pages/Forja/Aprovacoes/Index.tsx` | 1 | PageHeader=sim · DataTable=não | UiLintCommand.php R4 | aplicado |
| 🔴 aberta | `FLEX-CRU` | `Modules/Forja/Resources/js/Pages/Forja/Aprovacoes/Index.tsx` | 1 |  | layout-primitives-guard.mjs | aplicado |
| 🔴 aberta | `R3` | `Modules/Forja/Resources/js/Pages/Forja/Backlog/Index.tsx` | 3 | → | UiLintCommand.php R3 | aplicado |
| 🔴 aberta | `R4` | `Modules/Forja/Resources/js/Pages/Forja/Backlog/Index.tsx` | 1 | PageHeader=sim · DataTable=não | UiLintCommand.php R4 | aplicado |
| 🔴 aberta | `FLEX-CRU` | `Modules/Forja/Resources/js/Pages/Forja/Backlog/Index.tsx` | 2 |  | layout-primitives-guard.mjs | aplicado |
| 🔴 aberta | `R3` | `Modules/Forja/Resources/js/Pages/Forja/Board/DetailSheet.tsx` | 2 | ↔ ✓ | UiLintCommand.php R3 | aplicado |
| 🔴 aberta | `FLEX-CRU` | `Modules/Forja/Resources/js/Pages/Forja/Board/DetailSheet.tsx` | 17 |  | layout-primitives-guard.mjs | aplicado |
| 🔴 aberta | `R1` | `Modules/Forja/Resources/js/Pages/Forja/Board/Index.tsx` | 1 | #963 | UiLintCommand.php R1 · conformance-gate | aplicado |
| 🔴 aberta | `R3` | `Modules/Forja/Resources/js/Pages/Forja/Board/Index.tsx` | 6 | → | UiLintCommand.php R3 | aplicado |
| 🔴 aberta | `R4` | `Modules/Forja/Resources/js/Pages/Forja/Board/Index.tsx` | 1 | PageHeader=sim · DataTable=não | UiLintCommand.php R4 | aplicado |
| 🔴 aberta | `FLEX-CRU` | `Modules/Forja/Resources/js/Pages/Forja/Board/Index.tsx` | 4 |  | layout-primitives-guard.mjs | aplicado |
| 🔴 aberta | `R3` | `Modules/Forja/Resources/js/Pages/Forja/Board/_components/ShortcutsOverlay.tsx` | 4 | → | UiLintCommand.php R3 | aplicado |
| 🔴 aberta | `FLEX-CRU` | `Modules/Forja/Resources/js/Pages/Forja/Board/_components/ShortcutsOverlay.tsx` | 1 |  | layout-primitives-guard.mjs | aplicado |
| 🔴 aberta | `R3` | `Modules/Forja/Resources/js/Pages/Forja/Burndown/Index.tsx` | 3 | → | UiLintCommand.php R3 | aplicado |
| 🔴 aberta | `R4` | `Modules/Forja/Resources/js/Pages/Forja/Burndown/Index.tsx` | 1 | PageHeader=sim · DataTable=não | UiLintCommand.php R4 | aplicado |
| 🔴 aberta | `FLEX-CRU` | `Modules/Forja/Resources/js/Pages/Forja/Burndown/Index.tsx` | 3 |  | layout-primitives-guard.mjs | aplicado |
| 🔴 aberta | `R1` | `Modules/Forja/Resources/js/Pages/Forja/Inbox/Index.tsx` | 1 | #1940 | UiLintCommand.php R1 · conformance-gate | aplicado |
| 🔴 aberta | `R3` | `Modules/Forja/Resources/js/Pages/Forja/Inbox/Index.tsx` | 3 | → | UiLintCommand.php R3 | aplicado |
| 🔴 aberta | `R4` | `Modules/Forja/Resources/js/Pages/Forja/Inbox/Index.tsx` | 1 | PageHeader=sim · DataTable=não | UiLintCommand.php R4 | aplicado |
| 🔴 aberta | `FLEX-CRU` | `Modules/Forja/Resources/js/Pages/Forja/Inbox/Index.tsx` | 6 |  | layout-primitives-guard.mjs | aplicado |
| 🔴 aberta | `R3` | `Modules/Forja/Resources/js/Pages/Forja/MyWork/Index.tsx` | 6 | ↔ → | UiLintCommand.php R3 | aplicado |
| 🔴 aberta | `R4` | `Modules/Forja/Resources/js/Pages/Forja/MyWork/Index.tsx` | 1 | PageHeader=sim · DataTable=não | UiLintCommand.php R4 | aplicado |
| 🔴 aberta | `FLEX-CRU` | `Modules/Forja/Resources/js/Pages/Forja/MyWork/Index.tsx` | 11 |  | layout-primitives-guard.mjs | aplicado |
| 🔴 aberta | `R3` | `Modules/Forja/Resources/js/Pages/Forja/Roadmap/Gantt.tsx` | 7 | → ⚠ | UiLintCommand.php R3 | aplicado |
| 🔴 aberta | `R1` | `Modules/Forja/Resources/js/Pages/Forja/Roadmap/Index.tsx` | 1 | #3b82f6 | UiLintCommand.php R1 · conformance-gate | aplicado |
| 🔴 aberta | `R3` | `Modules/Forja/Resources/js/Pages/Forja/Roadmap/Index.tsx` | 2 | → | UiLintCommand.php R3 | aplicado |
| 🔴 aberta | `R4` | `Modules/Forja/Resources/js/Pages/Forja/Roadmap/Index.tsx` | 1 | PageHeader=sim · DataTable=não | UiLintCommand.php R4 | aplicado |
| 🔴 aberta | `FLEX-CRU` | `Modules/Forja/Resources/js/Pages/Forja/Roadmap/Index.tsx` | 6 |  | layout-primitives-guard.mjs | aplicado |
| 🔴 aberta | `R3` | `Modules/Forja/Resources/js/Pages/Forja/Trabalho/Index.tsx` | 14 | → ✦ ↵ ↗ ✕ | UiLintCommand.php R3 | aplicado |
| 🔴 aberta | `R4` | `Modules/Forja/Resources/js/Pages/Forja/Trabalho/Index.tsx` | 1 | PageHeader=não · DataTable=não | UiLintCommand.php R4 | aplicado |
| 🔴 aberta | `R1` | `Modules/Forja/Resources/js/Pages/Forja/Trabalho/_components/trabalhoAtomos.tsx` | 5 | oklch() · oklch(0.6 0.18 <hue>) | UiLintCommand.php R1 · conformance-gate | aplicado |
| 🔴 aberta | `R3` | `Modules/Forja/Resources/js/Pages/Forja/Trabalho/_components/trabalhoAtomos.tsx` | 1 | ⚠ | UiLintCommand.php R3 | aplicado |
| 🔴 aberta | `R3` | `Modules/Forja/Resources/js/Pages/Forja/Trabalho/_components/TrabalhoLista.tsx` | 4 | → ★ | UiLintCommand.php R3 | aplicado |
| 🔴 aberta | `R3` | `Modules/Forja/Resources/js/Pages/Forja/Trabalho/_components/TrabalhoQuadro.tsx` | 4 | → ⚠ | UiLintCommand.php R3 | aplicado |
| 🔴 aberta | `R1` | `Modules/Forja/Resources/js/Pages/Forja/Triage/Index.tsx` | 1 | #1940 | UiLintCommand.php R1 · conformance-gate | aplicado |
| 🔴 aberta | `R3` | `Modules/Forja/Resources/js/Pages/Forja/Triage/Index.tsx` | 4 | → | UiLintCommand.php R3 | aplicado |
| 🔴 aberta | `R4` | `Modules/Forja/Resources/js/Pages/Forja/Triage/Index.tsx` | 1 | PageHeader=sim · DataTable=não | UiLintCommand.php R4 | aplicado |
| 🔴 aberta | `FLEX-CRU` | `Modules/Forja/Resources/js/Pages/Forja/Triage/Index.tsx` | 9 |  | layout-primitives-guard.mjs | aplicado |
| 🔴 aberta | `R3` | `Modules/Forja/Resources/js/Pages/Forja/Triage/_components/TriageDossier.tsx` | 6 | → | UiLintCommand.php R3 | aplicado |
| 🔴 aberta | `FLEX-CRU` | `Modules/Forja/Resources/js/Pages/Forja/Triage/_components/TriageDossier.tsx` | 12 |  | layout-primitives-guard.mjs | aplicado |
| 🔴 aberta | `R3` | `Modules/Forja/Resources/js/Pages/team-mcp/CcSessions/Index.tsx` | 3 | → ↵ | UiLintCommand.php R3 | aplicado |
| 🔴 aberta | `R4` | `Modules/Forja/Resources/js/Pages/team-mcp/CcSessions/Index.tsx` | 1 | PageHeader=sim · DataTable=não | UiLintCommand.php R4 | aplicado |
| 🔴 aberta | `FLEX-CRU` | `Modules/Forja/Resources/js/Pages/team-mcp/CcSessions/Index.tsx` | 9 |  | layout-primitives-guard.mjs | aplicado |
| 🔴 aberta | `R3` | `Modules/Forja/Resources/js/Pages/team-mcp/CcSessions/_components/SessionDrawer.tsx` | 3 | ▲ ▼ → | UiLintCommand.php R3 | aplicado |
| 🔴 aberta | `FLEX-CRU` | `Modules/Forja/Resources/js/Pages/team-mcp/CcSessions/_components/SessionDrawer.tsx` | 9 |  | layout-primitives-guard.mjs | aplicado |
| 🔴 aberta | `FLEX-CRU` | `Modules/Forja/Resources/js/Pages/team-mcp/Forja/Cockpit.tsx` | 1 |  | layout-primitives-guard.mjs | aplicado |
| 🔴 aberta | `FLEX-CRU` | `Modules/Forja/Resources/js/Pages/team-mcp/Forja/_components/ForjaBacklog.tsx` | 7 |  | layout-primitives-guard.mjs | aplicado |
| 🔴 aberta | `R3` | `Modules/Forja/Resources/js/Pages/team-mcp/Forja/_components/ForjaChangelog.tsx` | 1 | → | UiLintCommand.php R3 | aplicado |
| 🔴 aberta | `FLEX-CRU` | `Modules/Forja/Resources/js/Pages/team-mcp/Forja/_components/ForjaChangelog.tsx` | 3 |  | layout-primitives-guard.mjs | aplicado |
| 🔴 aberta | `R3` | `Modules/Forja/Resources/js/Pages/team-mcp/Forja/_components/ForjaDossier.tsx` | 7 | → | UiLintCommand.php R3 | aplicado |
| 🔴 aberta | `FLEX-CRU` | `Modules/Forja/Resources/js/Pages/team-mcp/Forja/_components/ForjaDossier.tsx` | 12 |  | layout-primitives-guard.mjs | aplicado |
| 🔴 aberta | `R1` | `Modules/Forja/Resources/js/Pages/team-mcp/Forja/_components/ForjaHandoffs.tsx` | 1 | #2924 | UiLintCommand.php R1 · conformance-gate | aplicado |
| 🔴 aberta | `R3` | `Modules/Forja/Resources/js/Pages/team-mcp/Forja/_components/ForjaHandoffs.tsx` | 11 | → | UiLintCommand.php R3 | aplicado |
| 🔴 aberta | `FLEX-CRU` | `Modules/Forja/Resources/js/Pages/team-mcp/Forja/_components/ForjaHandoffs.tsx` | 14 |  | layout-primitives-guard.mjs | aplicado |
| 🔴 aberta | `R3` | `Modules/Forja/Resources/js/Pages/team-mcp/Forja/_components/ForjaHub.tsx` | 5 | ✦ ⚠ → ↔ | UiLintCommand.php R3 | aplicado |
| 🔴 aberta | `R3` | `Modules/Forja/Resources/js/Pages/team-mcp/Forja/_components/ForjaIntegrador.tsx` | 4 | ✓ → ↔ | UiLintCommand.php R3 | aplicado |
| 🔴 aberta | `R3` | `Modules/Forja/Resources/js/Pages/team-mcp/Forja/_components/ForjaMcp.tsx` | 6 | → | UiLintCommand.php R3 | aplicado |
| 🔴 aberta | `FLEX-CRU` | `Modules/Forja/Resources/js/Pages/team-mcp/Forja/_components/ForjaMcp.tsx` | 17 |  | layout-primitives-guard.mjs | aplicado |
| 🔴 aberta | `R3` | `Modules/Forja/Resources/js/Pages/team-mcp/Forja/_components/ForjaQuadro.tsx` | 4 | → | UiLintCommand.php R3 | aplicado |
| 🔴 aberta | `FLEX-CRU` | `Modules/Forja/Resources/js/Pages/team-mcp/Forja/_components/ForjaQuadro.tsx` | 7 |  | layout-primitives-guard.mjs | aplicado |
| 🔴 aberta | `R1` | `Modules/Forja/Resources/js/Pages/team-mcp/Forja/_components/ForjaSaude.tsx` | 2 | oklch(0.55 0.13 ${hue}) · oklch(0.58 0.13 ${w.hue}) | UiLintCommand.php R1 · conformance-gate | aplicado |
| 🔴 aberta | `R3` | `Modules/Forja/Resources/js/Pages/team-mcp/Forja/_components/ForjaSaude.tsx` | 4 | → ⇒ | UiLintCommand.php R3 | aplicado |
| 🔴 aberta | `FLEX-CRU` | `Modules/Forja/Resources/js/Pages/team-mcp/Forja/_components/ForjaSaude.tsx` | 1 |  | layout-primitives-guard.mjs | aplicado |
| 🔴 aberta | `R3` | `Modules/Forja/Resources/js/Pages/team-mcp/Forja/_components/ForjaTriage.tsx` | 3 | → | UiLintCommand.php R3 | aplicado |
| 🔴 aberta | `FLEX-CRU` | `Modules/Forja/Resources/js/Pages/team-mcp/Forja/_components/ForjaTriage.tsx` | 3 |  | layout-primitives-guard.mjs | aplicado |
| 🔴 aberta | `R3` | `Modules/Forja/Resources/js/Pages/team-mcp/Scorecard/Index.tsx` | 1 | → | UiLintCommand.php R3 | aplicado |
| 🔴 aberta | `R4` | `Modules/Forja/Resources/js/Pages/team-mcp/Scorecard/Index.tsx` | 1 | PageHeader=sim · DataTable=não | UiLintCommand.php R4 | aplicado |
| 🔴 aberta | `FLEX-CRU` | `Modules/Forja/Resources/js/Pages/team-mcp/Scorecard/Index.tsx` | 3 |  | layout-primitives-guard.mjs | aplicado |
| 🔴 aberta | `R3` | `Modules/Forja/Resources/js/Pages/team-mcp/Tasks/Index.tsx` | 2 | → ↵ | UiLintCommand.php R3 | aplicado |
| 🔴 aberta | `R4` | `Modules/Forja/Resources/js/Pages/team-mcp/Tasks/Index.tsx` | 1 | PageHeader=sim · DataTable=não | UiLintCommand.php R4 | aplicado |
| 🔴 aberta | `FLEX-CRU` | `Modules/Forja/Resources/js/Pages/team-mcp/Tasks/Index.tsx` | 12 |  | layout-primitives-guard.mjs | aplicado |
| 🔴 aberta | `FLEX-CRU` | `Modules/Forja/Resources/js/Pages/team-mcp/Tasks/_components/TaskDrawer.tsx` | 6 |  | layout-primitives-guard.mjs | aplicado |
| 🔴 aberta | `R3` | `Modules/Forja/Resources/js/Pages/team-mcp/Team/Index.tsx` | 4 | → 🚫 ⚠ | UiLintCommand.php R3 | aplicado |
| 🔴 aberta | `R4` | `Modules/Forja/Resources/js/Pages/team-mcp/Team/Index.tsx` | 1 | PageHeader=sim · DataTable=não | UiLintCommand.php R4 | aplicado |
| 🔴 aberta | `FLEX-CRU` | `Modules/Forja/Resources/js/Pages/team-mcp/Team/Index.tsx` | 6 |  | layout-primitives-guard.mjs | aplicado |
| 🔴 aberta | `R1` | `resources/css/cowork-forja-bundle.css` | 335 | oklch(0.275 0.050 295) · oklch(0.52 0.10 195) | UiLintCommand.php R1 · conformance-gate | aplicado |
| 🔴 aberta | `FONTRAMP` | `resources/css/cowork-forja-bundle.css` | 293 | font-size:10.5px · font-size:12px · font-size:11px | conformance-gate (.fontramp-baseline.json) | aplicado |
| 🔴 aberta | `IMPORTANT` | `resources/css/cowork-forja-bundle.css` | 2 |  | stylelint declaration-no-important | aplicado |
| 🔴 aberta | `HEX-CSS` | `resources/css/cowork-forja-bundle.css` | 7 | #fff | stylelint color-no-hex | aplicado |
| 🔴 aberta | `PALETA` | `resources/css/cowork-forja-bundle.css` | 1 | --dev-*(4) | prototipo-ui/ds-guard.mjs | aplicado |
| 🔴 aberta | `R1` | `prototipo-ui/cowork/forja-aprova.jsx` | 3 | oklch(0.55 0.13 ${hue}) · oklch(0.58 0.18 25) | UiLintCommand.php R1 · conformance-gate | prototipo |
| 🔴 aberta | `R3` | `prototipo-ui/cowork/forja-aprova.jsx` | 4 | → ✓ | UiLintCommand.php R3 | prototipo |
| 🔴 aberta | `R1` | `prototipo-ui/cowork/forja-data.jsx` | 19 | oklch(0.57 0.16 25) · oklch(0.55 0.15 295) | UiLintCommand.php R1 · conformance-gate | prototipo |
| 🔴 aberta | `R3` | `prototipo-ui/cowork/forja-data.jsx` | 26 | → ✓ 🔴 ↔ ⇧ | UiLintCommand.php R3 | prototipo |
| 🔴 aberta | `R3` | `prototipo-ui/cowork/forja-integra.jsx` | 5 | ↔ ✓ → | UiLintCommand.php R3 | prototipo |
| 🔴 aberta | `R1` | `prototipo-ui/cowork/forja-mcp.jsx` | 1 | oklch(0.58 0.21 25) | UiLintCommand.php R1 · conformance-gate | prototipo |
| 🔴 aberta | `R3` | `prototipo-ui/cowork/forja-mcp.jsx` | 24 | ✦ → ⚿ ⚠ ↗ ✓ | UiLintCommand.php R3 | prototipo |
| 🔴 aberta | `FLEX-CRU` | `prototipo-ui/cowork/forja-mcp.jsx` | 1 |  | layout-primitives-guard.mjs | prototipo |
| 🔴 aberta | `R1` | `prototipo-ui/cowork/forja-page.jsx` | 12 | #fff · #fff | UiLintCommand.php R1 · conformance-gate | prototipo |
| 🔴 aberta | `R3` | `prototipo-ui/cowork/forja-page.jsx` | 46 | → ↓ ✓ ⚠ ▲ ↵ | UiLintCommand.php R3 | prototipo |
| 🔴 aberta | `FLEX-CRU` | `prototipo-ui/cowork/forja-page.jsx` | 1 |  | layout-primitives-guard.mjs | prototipo |
| 🔴 aberta | `R1` | `prototipo-ui/cowork/forja-tarefas.jsx` | 3 | oklch(0.6 0.14 " + st.hue + ") · oklch(0.6 0.18 " + ({ P0: 25, P1: 60, P2: 295, P3: 250 }[t.priority]) | UiLintCommand.php R1 · conformance-gate | prototipo |
| 🔴 aberta | `R3` | `prototipo-ui/cowork/forja-tarefas.jsx` | 9 | → ↵ ⇧ | UiLintCommand.php R3 | prototipo |
| 🔴 aberta | `ds/no-raw-palette-color` | `Modules/Forja/Resources/js/Pages/Forja/Board/DetailSheet.tsx` | 10 | sem cor crua do Tailwind (bg/text/border-<cor>-<n>). Use token semântico: bg-card/bg-muted, text-foreground/te | eslint.config.js no-restricted-syntax (ds/*) · ratchet config/eslint-baseline.json · placar scripts/ds-report.mjs | aplicado |
| 🔴 aberta | `ds/no-raw-palette-color` | `Modules/Forja/Resources/js/Pages/Forja/Burndown/Index.tsx` | 3 | sem cor crua do Tailwind (bg/text/border-<cor>-<n>). Use token semântico: bg-card/bg-muted, text-foreground/te | eslint.config.js no-restricted-syntax (ds/*) · ratchet config/eslint-baseline.json · placar scripts/ds-report.mjs | aplicado |
| 🔴 aberta | `ds/no-raw-palette-color` | `Modules/Forja/Resources/js/Pages/Forja/MyWork/Index.tsx` | 1 | sem cor crua do Tailwind (bg/text/border-<cor>-<n>). Use token semântico: bg-card/bg-muted, text-foreground/te | eslint.config.js no-restricted-syntax (ds/*) · ratchet config/eslint-baseline.json · placar scripts/ds-report.mjs | aplicado |
| 🔴 aberta | `ds/no-inline-raw-color` | `Modules/Forja/Resources/js/Pages/Forja/Roadmap/Index.tsx` | 1 | sem cor/borda/sombra crua em style inline. Use token dark-aware: var(--accent)/var(--border)/var(--text)/var(- | eslint.config.js no-restricted-syntax (ds/*) · ratchet config/eslint-baseline.json · placar scripts/ds-report.mjs | aplicado |
| 🔴 aberta | `ds/no-raw-palette-color` | `Modules/Forja/Resources/js/Pages/Forja/Roadmap/Index.tsx` | 1 | sem cor crua do Tailwind (bg/text/border-<cor>-<n>). Use token semântico: bg-card/bg-muted, text-foreground/te | eslint.config.js no-restricted-syntax (ds/*) · ratchet config/eslint-baseline.json · placar scripts/ds-report.mjs | aplicado |
| 🔴 aberta | `ds/no-raw-palette-color` | `Modules/Forja/Resources/js/Pages/Forja/Triage/Index.tsx` | 5 | sem cor crua do Tailwind (bg/text/border-<cor>-<n>). Use token semântico: bg-card/bg-muted, text-foreground/te | eslint.config.js no-restricted-syntax (ds/*) · ratchet config/eslint-baseline.json · placar scripts/ds-report.mjs | aplicado |
| 🔴 aberta | `ds/no-raw-palette-color` | `Modules/Forja/Resources/js/Pages/ads/Admin/ProjectShow.tsx` | 1 | sem cor crua do Tailwind (bg/text/border-<cor>-<n>). Use token semântico: bg-card/bg-muted, text-foreground/te | eslint.config.js no-restricted-syntax (ds/*) · ratchet config/eslint-baseline.json · placar scripts/ds-report.mjs | aplicado |
| 🔴 aberta | `ds/no-raw-palette-color` | `Modules/Forja/Resources/js/Pages/ads/Admin/Projects.tsx` | 1 | sem cor crua do Tailwind (bg/text/border-<cor>-<n>). Use token semântico: bg-card/bg-muted, text-foreground/te | eslint.config.js no-restricted-syntax (ds/*) · ratchet config/eslint-baseline.json · placar scripts/ds-report.mjs | aplicado |
| 🔴 aberta | `ds/no-raw-palette-color` | `Modules/Forja/Resources/js/Pages/ads/Admin/TeamScopes.tsx` | 2 | sem cor crua do Tailwind (bg/text/border-<cor>-<n>). Use token semântico: bg-card/bg-muted, text-foreground/te | eslint.config.js no-restricted-syntax (ds/*) · ratchet config/eslint-baseline.json · placar scripts/ds-report.mjs | aplicado |
| 🔴 aberta | `ds/no-raw-palette-color` | `Modules/Forja/Resources/js/Pages/ads/Admin/Tools.tsx` | 5 | sem cor crua do Tailwind (bg/text/border-<cor>-<n>). Use token semântico: bg-card/bg-muted, text-foreground/te | eslint.config.js no-restricted-syntax (ds/*) · ratchet config/eslint-baseline.json · placar scripts/ds-report.mjs | aplicado |
| 🔴 aberta | `ds/no-inline-tablist` | `Modules/Forja/Resources/js/Pages/team-mcp/Forja/_components/ForjaChangelog.tsx` | 1 | não hand-role `role="tablist"` na tela. Barra de abas de topo (navega por URL) = <PageHeaderTabs> (@/Component | eslint.config.js no-restricted-syntax (ds/*) · ratchet config/eslint-baseline.json · placar scripts/ds-report.mjs | aplicado |
| 🔴 aberta | `ds/no-os-btn` | `Modules/Forja/Resources/js/Pages/team-mcp/Forja/_components/ForjaHub.tsx` | 1 | use <Button> (@/Components/ui/button) com variant/size, não a classe de shell os-btn. | eslint.config.js no-restricted-syntax (ds/*) · ratchet config/eslint-baseline.json · placar scripts/ds-report.mjs | aplicado |
| 🔴 aberta | `ds/no-inline-tablist` | `Modules/Forja/Resources/js/Pages/team-mcp/Forja/_components/ForjaIntegrador.tsx` | 1 | não hand-role `role="tablist"` na tela. Barra de abas de topo (navega por URL) = <PageHeaderTabs> (@/Component | eslint.config.js no-restricted-syntax (ds/*) · ratchet config/eslint-baseline.json · placar scripts/ds-report.mjs | aplicado |
| 🔴 aberta | `ds/no-db-jargon-in-ui` | `Modules/Forja/Resources/js/Pages/team-mcp/Scorecard/Index.tsx` | 1 | texto visível com nome de coluna/SQL cru. Use linguagem de negócio PT (ex: "distinct contact_id" → "fornecedor | eslint.config.js no-restricted-syntax (ds/*) · ratchet config/eslint-baseline.json · placar scripts/ds-report.mjs | aplicado |
| 🔴 aberta | `ds/no-inline-tablist` | `Modules/Forja/Resources/js/Pages/team-mcp/Tasks/_components/TaskDrawer.tsx` | 1 | não hand-role `role="tablist"` na tela. Barra de abas de topo (navega por URL) = <PageHeaderTabs> (@/Component | eslint.config.js no-restricted-syntax (ds/*) · ratchet config/eslint-baseline.json · placar scripts/ds-report.mjs | aplicado |
| 🔴 aberta | `ds/no-handrolled-status-pill` | `Modules/Forja/Resources/js/Pages/team-mcp/Team/Index.tsx` | 1 | não hand-role a pílula de status. Use <Badge> (@/Components/ui/badge) com variant="" ou <StatusBadge kind valu | eslint.config.js no-restricted-syntax (ds/*) · ratchet config/eslint-baseline.json · placar scripts/ds-report.mjs | aplicado |

## Soluções por regra (a receita que [W] pediu)

| regra | onde se resolve | automatizável | como |
|---|---|---|---|
| `R1` | fonte + code | parcial | valor com token equivalente no DS → var() (ex.: oklch(0.55 0.15 295)=var(--accent); 0.58 0.21 25=var(--neg); 0.63 0.16 68=var(--warn)); cor DINÂMICA por hue (prio/fase/papel) → classe com custom property (`style={{"--h":hue}}` + `.fj-x{color:oklch(0.6 0.18 var(--h))}`), que tira o literal do JSX sem mudar 1 pixel. O que não tem token: pedir ao Cowork o token na fonte (FORJA-137), não inventar aqui. |
| `R3` | code | sim | codemod glifo→lucide com tamanho igual ao do texto: ✦ Sparkles · ⚠ AlertTriangle · ★/☆ Star · → ArrowRight · ↗ ArrowUpRight · ✓ Check · ✗ X · ⚿ KeyRound · ● Circle(fill). Um componente <Glifo> concentra o mapa; o texto ao redor não muda. |
| `R4` | missão + decisão | não | as telas /project-mgmt/* saem na onda de revogação (item some); nas telas réplica o header É o do protótipo — R4 exige PageHeader+DataTable canon que o protótipo não usa: [W] marca `aceita` OU a regra R4 passa a reconhecer o header do bundle (`.fj-page > header`) como canon. Não reescrever o header pra agradar R4. |
| `FONTRAMP` | fonte | sim (na fonte) | snap ao ramp --fs-1..9 (10.5/11.5/12.5/13.5/15/18/22/28/38) NO PROTÓTIPO, pelo [CC]; fazer aqui muda o pixel (11→11.5) e a sonda D4 acusa. Enquanto a fonte não snapa: `aceita` com nota, contagem fica visível. |
| `IMPORTANT` | fonte | sim (na fonte) | subir a especificidade do seletor em vez de !important; 2 ocorrências, pedir ao [CC]. |
| `HEX-CSS` | fonte | sim (na fonte) | #fff → var(--accent-fg) / var(--surface) conforme o papel; 6 ocorrências, pedir ao [CC]. |
| `FLEX-CRU` | missão | não precisa | as telas antigas usam Tailwind `flex`/`grid` cru; a réplica troca por classes do bundle (`.fj-row`, `.fj-toolbar`…) e o item some. Nas 8 telas /project-mgmt/* some pela revogação. NÃO refatorar pra Stack/Inline antes da onda — seria pagar 2×. |
| `SINTAXE` | fonte | sim (na fonte) | o navegador tolera `)` sobrando, o parser do Tailwind v4 no build do Vite derruba o build inteiro (medido 2026-09-02, forja-page.css:778). Consertar no protótipo; enquanto não desce, desvio de 1 byte DECLARADO no cabeçalho do bundle. |
| `ds/*` | code (a mensagem diz o alvo) | parcial | cada regra ds/* tem alvo canônico na PRÓPRIA mensagem do ESLint — a tabela "Receita ds/*" abaixo lista a mensagem literal de cada regra que apareceu nesta medição (derivada, não escrita à mão). Duas famílias: COMPONENT-SUBSTITUTE (`no-os-btn`→<Button>, `no-inline-tablist`→<PageHeaderTabs>/<SubNav>, `no-handrolled-status-pill`→<Badge>/<StatusBadge>, `no-handrolled-combobox`→<Command> em <Popover>) troca o hand-roll pelo componente do DS; EIXO DE VALOR (`no-raw-palette-color`, `no-inline-raw-color`, `no-arbitrary-color`, `no-rounded-xl`) é o MESMO eixo do R1 acima — se a cor veio copiada do protótipo, o conserto é na fonte (ADR 0374), não aqui. |
| `PALETA` | fonte (DS) | sim | promover --dev/--dev-soft/--dev-line a token do DS (`--origin-DEV*`) no SSOT `resources/css/tokens/semantic.tokens.json` + `npm run tokens:build`; o bundle passa a consumir var() e o ds-guard para de ver família própria. É token novo = decisão [W] (FORJA-137). |

## Receita `ds/*` — mensagem canônica do ESLint (traz o alvo)

> Derivada da medição: cada linha é o texto literal que o `eslint.config.js` emite. O eixo mede
> só `origem = aplicado` — o JSX do espelho (`--prototipo`) está FORA do escopo do dono
> (`Pages/**` das 2 raízes), então não é medido aqui e não vira 0 falso.

| regra | mensagem (= a receita) |
|---|---|
| `ds/no-db-jargon-in-ui` | ds/no-db-jargon-in-ui — texto visível com nome de coluna/SQL cru. Use linguagem de negócio PT (ex: "distinct contact_id" → "fornecedores no período"; "final_total" → "total"). |
| `ds/no-handrolled-status-pill` | ds/no-handrolled-status-pill — não hand-role a pílula de status. Use <Badge> (@/Components/ui/badge) com variant="" ou <StatusBadge kind value> (@/Components/shared/StatusBadge — sem entrada no registry, ver ALVO_POR_REGRA). Ver REGISTRY_DS_COMPONENTES.md §"pílula de status". |
| `ds/no-inline-raw-color` | ds/no-inline-raw-color — sem cor/borda/sombra crua em style inline. Use token dark-aware: var(--accent)/var(--border)/var(--text)/var(--surface)… ou classe utilitária semântica. Hardcode de tom claro quebra o dark sem alarme (bug tab-nav 2026-07). |
| `ds/no-inline-tablist` | ds/no-inline-tablist — não hand-role `role="tablist"` na tela. Barra de abas de topo (navega por URL) = <PageHeaderTabs> (@/Components/shared, via *SubNav do módulo); switch in-page controlado (value/onChange, sem URL) = <SubNav> (@/Components/shared). Ver REGISTRY_DS_COMPONENTES.md §"barra de abas de topo" / §"Sub-navegação contextual". |
| `ds/no-os-btn` | ds/no-os-btn — use <Button> (@/Components/ui/button) com variant/size, não a classe de shell os-btn. |
| `ds/no-raw-palette-color` | ds/no-raw-palette-color — sem cor crua do Tailwind (bg/text/border-<cor>-<n>). Use token semântico: bg-card/bg-muted, text-foreground/text-muted-foreground, border-border, text-destructive, text-primary… |

## Como fechar um item

1. **Resolver** (o Code): tokeniza / troca glifo por lucide / adota PageHeader canon **sem mudar o layout** — a próxima medição não encontra o item e ele sai.
2. **Aceitar** (só [W]): a inconsistência é decisão de design, não dívida — `status: aceita` + `nota` no JSON. Fica visível, não alarma.
3. Nunca: apagar a linha à mão, ou relaxar a regra no dono pra o item sumir.
