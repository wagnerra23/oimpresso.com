---
date: "2026-07-06"
time: "10:55 BRT"
slug: defer-sweep-repo-wide-19-telas
tldr: "Varredura repo-wide da classe de crash Inertia::defer sem guarda no frontend (mesma do #3862): 19 offenders em 6 módulos corrigidos + ratchet de arquitetura + smoke real prod before/after. 8 PRs mergeados/auto-merge."
prs: [3866, 3867, 3868, 3869, 3870, 3871, 3873, 3874]
decided_by: [W]
next_steps:
  - "Confirmar #3874 (zera PENDING allowlist) auto-mergeou verde"
  - "Se quiser cobertura de smoke total: rodar browser MCP nas 15 telas não-smokadas individualmente (Essentials/ADS/TeamMcp/Admin restantes)"
---

## Estado MCP no momento do fechamento

- **Cycle:** nenhum ATIVO em COPI (off-cycle).
- **my-work:** 30 tasks (8 REVIEW · 8 BLOCKED · 14 TODO) — **nenhuma** relativa a este trabalho: a varredura foi pedido direto do Wagner, sem US rastreada. Nada a `tasks-update`.
- **decisions-search "Inertia defer frontend guard":** sem ADR específica — o padrão canônico já vive em [`RUNBOOK-inertia-defer-pattern.md`](../requisitos/_DesignSystem/RUNBOOK-inertia-defer-pattern.md). Não abri ADR nova (bug-fix + gate, não decisão arquitetural).
- **Handoffs irmãos hoje:** nenhum em `2026-07-06-*` antes deste (últimos são 2026-07-05/07-02).

## O que aconteceu

Contexto: [#3862](https://github.com/wagnerra23/oimpresso.com/pull/3862) corrigiu `Ponto/Dashboard` (props via `Inertia::defer` desreferenciadas cruas no first render → `undefined` → `TypeError` → tela branca). Pedido: varrer o repo inteiro pela mesma classe.

1. **Audit** (base `origin/main` fresca — meu worktree nascia −4820 stale): 8 agentes paralelos varreram ~90 controllers / ~130 render-sites **parseando por chamada de render** (defer pode estar em outro método) → **19 offenders reais** em 6 módulos. Resto já SAFE (muitos após "lição #2515").
2. **Coordenação** (Wagner: "confira antes, outra sessão pode estar fazendo o mesmo"): confirmei que o único trabalho irmão era o #3862 (Dashboard) — excluído. Sem colisão.
3. **Fix** (Wagner "pode fazer todos"): 4 agentes editores paralelos aplicaram o padrão canônico (#3862 + RUNBOOK §3): props opcionais + guardas `?.`/`?? []` (defesa dupla) + `<Deferred fallback={skeleton}>`. 1 PR por módulo (commit-discipline).
4. **Ratchet** (#3873): gate Pest repo-wide filesystem-puro — toda Page alvo de render-com-defer importa `<Deferred>` OU está na allowlist guard-only. Regra dos 3 (MORDE/AUTO-TESTA/VIGIA). #3874 zerou a PENDING allowlist → cobre 100% dos 81 defer-renders.

## Artefatos gerados (todos MERGED, exceto #3874 auto-merge)

| PR | Módulo | Telas |
|---|---|---|
| #3866 | Ponto | 5 (Aprovacoes/BancoHoras×2/Espelho×2) |
| #3867 | Essentials | 5 (Documents/Holidays/Messages/Knowledge/Todo) |
| #3868 | ADS | 5 (Conflicts/Decisoes/Learning/Metricas/Patterns) |
| #3869 | TeamMcp | 2 (ads/Admin TeamScopes+Tools) |
| #3870 | NfeBrasil | 1 (Tributacao/Index) |
| #3871 | Admin | 1 (FeatureFlags/Show) |
| #3873 | test/arch | `tests/Feature/Architecture/InertiaDeferredFrontendGuardTest.php` |
| #3874 | test/arch | zera PENDING allowlist (burn-down → []) |

## Persistência

- **git:** 8 PRs → main. Deploy automático (ADR 0269) rodou (`28795851911` success 13:49Z).
- **MCP:** este handoff propaga via webhook ~2min.
- **Prova prod (smoke real R1):** *before* — `/ponto/espelho` live throwing `Cannot read properties of undefined (reading 'data')`. *after* (pós-deploy, console limpo): `/ponto/espelho`, `/nfe-brasil/tributacao`, `/ponto/aprovacoes`, `/ads/admin/metricas` renderizam OK.

## Lições catalogadas

- **`origin/main` andou no meio da sessão** (+4 commits, incl. merge do #3862). Rebase limpo via `git checkout -B <branch> origin/main` (nenhum dos 19 arquivos mudou upstream). Dashboard nunca tocado.
- **Validar lógica de teste que não roda local** (Pest = CT100/CI): portei o scanner pra Node e rodei contra a árvore viva → pegou 2 bugs meus PRÉ-CI (regex do componente dropava `-` → escondia todo `team-mcp/*`; e faltava allowlistar `Auditoria/Detail` guard-only). Sem isso, CI vermelho.
- **Layout ratchet (ADR 0253):** skeletons de fallback com `<div className="grid">` regridem — usar primitivo `<Grid>` (igual 2º commit do #3862).

## Pointers detalhados

- Padrão canônico: [`RUNBOOK-inertia-defer-pattern.md`](../requisitos/_DesignSystem/RUNBOOK-inertia-defer-pattern.md) §3/§4
- Metade backend do gate: `Modules/Governance/Tests/Feature/InertiaDeferAuditTest.php`
- Gate irmão (mesmo idioma): `tests/Feature/Architecture/OrphanRenderGateTest.php`
