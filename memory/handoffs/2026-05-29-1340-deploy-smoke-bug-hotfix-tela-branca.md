---
date: 2026-05-29
hour: "13:40 BRT"
topic: "Deploy #1940 → smoke real pegou TELA BRANCA em prod → hotfix Inertia::defer guard → regra smoke-browser canon"
duration: "~2h (continuação da sessão IA-OS)"
authors: [claude-opus-4.8, wagner]
session: frosty-greider-83ab2f
---

# Handoff — Deploy → smoke → bug tela branca → hotfix

> Continuação direta de [2026-05-29-1145 IA-OS/Team OS](2026-05-29-1145-ia-os-team-os-audit-automation-registry.md). Wagner mandou mergear o #1940 ("merge quero ver") + deployar.

## Estado MCP no momento

- Cycle CYCLE-07. Meus PRs da sessão: #1938/1939/1954/1956/1960/1962 **todos mergeados**. PRs abertos no repo (#1963/1961/1958/...) são de OUTRAS sessões (mesma identidade git), não meus.

## O que aconteceu (a lição cara)

1. Mergeei #1940 (Triage+Inbox UI) por ordem do Wagner (gate visual waived) → quick-sync **auto-deployou** (build:inertia na Hostinger pós push main).
2. **ERRO meu:** declarei "no ar/funcionando" baseado em **curl 302** (só prova rota). Wagner cobrou textual: *"depois de publicar tem que testar e garantir... use browser e anote no procedimento: testa porra."*
3. **Gravei a regra canon** ([feedback-deploy-smoke-browser-obrigatorio](../reference/feedback-deploy-smoke-browser-obrigatorio.md), PR #1960): curl ≠ smoke; após deploy de tela, smoke REAL no browser (4 sinais) antes de declarar.
4. **Smoke headless de verdade** (Chromium + Pest Browser + actingAs biz=1, sem o browser do Wagner) → **achou TELA BRANCA nas 2 telas**: `Uncaught TypeError: undefined.filter/.forEach`. Bug REAL em prod.
5. **Causa-raiz:** controllers usam `Inertia::defer()` (props chegam `undefined` no 1º paint); os `.tsx` consumiam direto sem `<Deferred>`/guard → React crasha a árvore → branco. (Mesma classe do hotfix #1197 OficinaAuto / rollback Board #963.)
6. **Hotfix #1962** (`73fac69ac`): default-guard nas props (`tasks ?? []`, `kpis ?? EMPTY_KPIS`, `inbox ?? []`, etc — Opção B skill `inertia-defer-default`, espelha Board/OficinaAuto).
7. **Re-smoke headless VERDE + verifiquei a olho** os PNGs: ambas renderizam (h1 + KPIs + sidebar + atalhos J/K/⌘K + empty-states sem emoji), 0 console error. Antes 7.9KB branco → depois 80KB renderizado.
8. Merge #1962 → quick-sync **SUCCESS** → **LIVE em prod**.

## Artefatos gerados

- **PR #1960** ([`feedback-deploy-smoke-browser-obrigatorio.md`](../reference/feedback-deploy-smoke-browser-obrigatorio.md)) — regra canon smoke pós-deploy.
- **PR #1962** (`73fac69ac`) — hotfix guard Inertia::defer Triage+Inbox.
- Evidência: `storage/smoke/triage.png` (branca pré-fix) · `triage-fixed.png` + `inbox-fixed.png` (renderizadas pós-fix) — não-commitadas (storage/).

## Persistência

- git: #1960 + #1962 na main → webhook→MCP indexa a regra em ~2min.
- Prod: quick-sync deployou o fix (build na Hostinger). Telas LIVE em `/project-mgmt/triage` + `/inbox`.

## Próximos passos pra retomar

1. Wagner dar a **olhada final** no app live (logado) — renderiza, mas o sign-off humano formal do gate (ADR 0107/0114) é dele.
2. PHPStan vermelho na main = débito pré-existente (ADR 0208) — outra sessão abriu **#1961** pra encolher. Não desta sessão.

## Lições catalogadas

- **curl 302/200 ≠ smoke.** Prova rota, não render. Tela Inertia pode deployar e dar branco (Inertia::defer sem guard). SÓ browser real (render + console) confirma. Virou regra canon #1960 + reforça R1.
- **Inertia::defer exige guard no frontend** (`<Deferred>` OU `?? default`). Backend sozinho (defer no controller) sem guard no `.tsx` = tela branca garantida no 1º paint. Padrão: skill `inertia-defer-default` Opção B.
- **Smoke headless sem o browser do Wagner é viável:** worktree main + vendor real (não junction) + `build:inertia` + Pest Browser/Playwright + `actingAs` biz=1 (ADR 0101) → render real + screenshot, sem credencial dele.
- **Merge pré-smoke = risco real materializado.** Mergeei UI não-vista (gate waived) → quebrou prod. O gate visual existe por isso.

## Pointers detalhados

- Regra: `memory/reference/feedback-deploy-smoke-browser-obrigatorio.md`
- Skill defer: `.claude/skills/inertia-defer-default/`
- Evidência render: `storage/smoke/{triage,inbox}-fixed.png`
- Telas: `resources/js/Pages/ProjectMgmt/{Triage,Inbox}/Index.tsx`
