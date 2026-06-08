---
date: 2026-05-31
hour: 08:41 BRT
topic: F0 auditoria rotinas de design (Cowork §10.4) + conserto do incidente "base stale" (guard automático)
duration: ~2h
authors: [Wagner, Claude Code]
session: frosty-greider-83ab2f (continuação do arco loop Cowork↔Code)
prs: [2032, 2033]
---

## Estado MCP no momento
- **Cycle:** CYCLE-07 "Fundações pós-4.8" (2026-05-28→06-11 · 21% · 11d restantes). Goals: Accounting deprec · Contacts absorve Pessoas · boletos multi-banco · DS v3 enforced.
- **my-work:** 30 tasks (5 REVIEW · 6 BLOCKED dormentes Gold · 19 TODO). **Nenhuma é deste trabalho** — foi reativo (sync Cowork + incidente), não US trackada.
- **origin/main HEAD ao fechar:** `eb9464b96` (#2033, o guard).

## O que aconteceu
1. **Processei a proposta [CC] §10.4 "otimizar rotinas de design" (G1–G6)** — entreguei o **F0** (auditoria-mapa, medir antes de mexer). Achado central: a medição de design fragmentou em **6 motores de score em 2 camadas** — **cara/LLM `design:*` DORMANTE** (`mwart-comparative` último 05-17 · `design-deep-analysis` **0 disparos** na história · `a11y-report.md` nunca gerado) vs **barata/estática VIVA** (`screen-grade` 222 telas · `module:grade` · ESLint `ds/*`). O `PROTOCOL.md` descreve só a camada morta. → **PR #2032**.
2. **INCIDENTE (o evento da sessão):** fiz o F0 inteiro — inclusive o **próprio gate §10.4** ("validar contra o main") — lendo um checkout **STALE** (`feat/staging-ct100`, −47 vs `origin/main`). Produziu **3 achados factualmente errados** (`ds:report` "não existe", canais "stale", G3 "gap") + edits que corromperiam os canais. **Pego POR SORTE no "merge"** (rodei `git rev-list` por acaso), **não pelo gate**. Refiz tudo contra `origin/main` → versão correta mergeada (#2032).
3. **Conserto de raiz** (Wagner: *"isso nunca pode acontecer ... não pode depender de mim"*): guard **automático** que dá choque sozinho. → **PR #2033**.

## Artefatos gerados (tudo em `main`, mergeado --admin)
- **PR #2032** (`b5de9be76`): `prototipo-ui/AUDITORIA_ROTINAS_DESIGN.md` (mapa F0, validado vs origin/main) + amendment verbatim + 3 canais §10.2 (COWORK_NOTES/SYNC_LOG/HANDOFF/CODE_NOTES).
- **PR #2033** (`eb9464b96`): `.claude/hooks/git-base-freshness-guard.mjs` (**SessionStart**, Node cross-platform: `git fetch` bounded + se HEAD atrás de `origin/main` → choque "BASE STALE" mandando validar via `git show origin/main:`; silêncio se fresco; escape valve `OIMPRESSO_BASE_GUARD_OFF=1`) + `.test.mjs` (10/10, provado live no −47) + **PROTOCOL §10.4 Passo 0** (ancorar em `origin/main` fresco — comandos mecânicos + checagem row + backstop) + wire em `settings.json`.

## Persistência (3 canais)
- **git:** PRs #2032 + #2033 em `main` (webhook→MCP ~2min — [CC] enxerga).
- **MCP:** nenhuma task tocada (trabalho reativo). Guard agora ativo pro time todo (settings.json versionado + Node = cross-platform).
- **BRIEFING:** n/a (não é módulo de produto).

## Próximos passos pra retomar
1. `brief-fetch` + ler este handoff. **Se o guard gritar "BASE STALE" no SessionStart → trabalhar a partir de `origin/main` fresco** (`git worktree add -b <branch> <path> origin/main`), nunca do scratch dir.
2. **Decisão [W] pendente (único bloqueador da proposta):** ordem de consolidação dos G **que sobraram** = **G1/G2/G6** (consolidar os 6 motores — religar `mwart-comparative` como aprofundamento sob demanda do `screen-grade`, NÃO skill nova) + **G5-`.css`** (Stylelint — única peça inexistente). G3/G4/G5-eslint já feitos/superados (loop 0-humano ADR 0241 + `ds:report` no main + ESLint `ds/*` ADR 0209).
3. (opcional, [W]) ADR formal de evolução do loop (mãe 0114/0239) — cunhar número = soberania [W] (ADR 0238).

## Lições catalogadas
- **Trap "branch stale = main".** O gate §10.4 dizia "valida contra o main" mas não definia "main" = `origin/main` pós-`fetch` nem forçava `fetch` → um checkout −47 passou silencioso e o gate validou contra disco velho. **Agora é mecânico:** hook `git-base-freshness-guard.mjs` (choque automático) + PROTOCOL §10.4 Passo 0. Não depende de [W] nem de [CL] lembrar.
- **Padrão seguro de produção:** sempre worktree fresco de `origin/main` pra produzir/mergear; o scratch dir `.claude/worktrees/frosty-*` resolve pro checkout `feat/staging-ct100` (stale).
- **Pego por sorte ≠ pego pelo processo.** Se a proteção depende de eu rodar um diagnóstico "por acaso", ela não existe — tem que ser hook/ratchet.

## Pointers detalhados (on-demand)
- [PROTOCOL.md §10.4 Passo 0](../../prototipo-ui/PROTOCOL.md) · [AUDITORIA_ROTINAS_DESIGN.md](../../prototipo-ui/AUDITORIA_ROTINAS_DESIGN.md)
- [git-base-freshness-guard.mjs](../../.claude/hooks/git-base-freshness-guard.mjs) · [ADR 0241 loop 0-humano](../decisions/0241-loop-design-cowork-code-autonomo.md) · [ADR 0239 governança DS](../decisions/0239-governanca-design-system-git-ssot-regressao-ia.md)
