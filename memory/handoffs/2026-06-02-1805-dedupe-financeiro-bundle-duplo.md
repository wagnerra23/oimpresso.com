---
date: "2026-06-02"
hour: "18:05 BRT"
slug: dedupe-financeiro-bundle-duplo
topic: "Dedupe do bundle CSS duplo do Financeiro (loop Cowork [CC]→[CL])"
tldr: "Dedupe do bundle CSS duplo Financeiro (loop Cowork). inertia.css importava 2 bundles ~327KB ambos .fin-cowork; o antigo Onda 8 vencia o cascade. Re-validei a paridade contra origin/main: 2309 identicas, 0 seletor real so-no-antigo, 29 body-diff TODAS var(--surface)->#fff = no-op (dark-theme nunca ativa). git rm do antigo (-327KB) + rebaseline stylelint 1065->820. PR #2127 --admin verde. Nuance: o C1 de 17:16 melhorou esses 30 no bundle que apaguei -> Fase 2 portar pro CANON."
duration: "~1h"
authors: [CL]
session: frosty-greider-83ab2f
---

# Dedupe bundle CSS duplo Financeiro → main (#2127, 1 PR --admin)

> Origem: prompt Cowork `prototipo-ui-patch/PROMPT_PARA_CODE_DEDUPE-FINANCEIRO-BUNDLE.md` ([CC] propôs, [CL] validou contra `origin/main` e executou). Loop ADR 0114.

## Estado MCP no momento
- Cycle ativo: **Receita — Onda A** (CYCLE-08, 26d restantes).
- `my-work`: 30 tasks ativas — **nenhuma** mapeava este dedupe (cleanup de infra CSS, não havia US/task; é follow-up do C1 da sessão 17:16).
- ADRs 24h: 3821 (processo memória/design Cowork). Sem incidentes/escalações.

## O que aconteceu
`inertia.css` importava **dois** bundles Financeiro de ~327KB cada, ambos `.fin-cowork`: o canon (`cowork-canon-financeiro-bundle.css`, 1º) e o antigo Onda 8 (`cowork-financeiro-bundle.css`, 2º → **vencia o cascade**). O header do antigo já dizia "Will be deprecated após validar paridade canon".

**Validei a paridade programaticamente** (parser CSS brace-aware próprio — selector→body normalizado, achatando `@media`/`@keyframes` — rodado contra os 2 blobs de `origin/main`, NÃO confiei nos números do prompt):
- **2309 regras idênticas** (lixo puro duplicado).
- **0 seletor real só-no-antigo** — a "perda de 4" do prompt eram só linhas de **comentário** de header.
- **29 regras com body diferente**, e **TODAS o mesmo único delta**: `background: var(--surface)` → `#fff`.

**Prova de no-op visual:** `--surface` só vira escuro sob `.fin-cowork [data-theme="dark"]`, e **não existe nenhum toggler de dark theme em `resources/js`** (grep confirmou). Logo `--surface` resolve sempre pra `#ffffff` → os 29 diffs são pixel-idênticos em produção.

**Achado que reportei (faltava no prompt):** `.rec-paper` (recibo) está nos 29 diffs mas **faltava** na lista de 30 do Cowork. Mesmo delta/no-op, anotado pro gate ficar completo.

## Artefatos gerados
- `resources/css/inertia.css` — removido `@import "./cowork-financeiro-bundle.css"` + comentário explicando paridade (−1 import).
- `resources/css/cowork-financeiro-bundle.css` — **`git rm`** (−8658 LOC / −327KB).
- `resources/js/Pages/Financeiro/_cowork-bundle/README.md` — 2 refs do bundle morto → canon.
- `config/stylelint-baseline.json` — regenerado via `node scripts/stylelint-baseline.mjs --write` (1065→820; removeu 3 entradas do arquivo deletado).

## Persistência
- **git**: PR **[#2127](https://github.com/wagnerra23/oimpresso.com/pull/2127)** squash-merged `--admin` → `origin/main` `7dff54968`. 13/13 checks CI verde (Pest, Vite build, Stylelint ratchet, UI gates, governance, secrets). Branch + worktree dedupe removidos.
- **Cowork (§10.2)**: retorno em `prototipo-ui/CODE_NOTES.md` ([CL]→[W]/[CD]) + `SYNC_LOG.md` + `new_design_memories` (golden/conflito).
- **BRIEFING**: N/A (cleanup CSS, sem mudança de capacidade do módulo).

## Próximos passos pra retomar
- **Fase 2 hex drift (já scoped no CODE_NOTES 17:16):** portar `var(--surface)` pros 30 selectors `os-*`/`vd-*`/etc **no canon** — o C1 da sessão 17:16 melhorou esses 30 no bundle *que acabei de deletar*, então canon voltou a `#fff` hardcoded. Visualmente no-op, mas perde o ratchet de token. Restam ~158 hex semânticos no canon.

## Lições catalogadas
- **C1 foi aplicado num bundle slated-for-delete** — a sessão 17:16 ratcheou `#fff`→`var(--surface)` no `cowork-financeiro-bundle.css` (o antigo), que este dedupe apagou. Trabalho de token-discipline deve mirar o **canon**, não o bundle deprecado. Por isso a Fase 2 acima.
- **Validar números do prompt, não confiar**: re-rodei o parser e achei `.rec-paper` que faltava nos 30 + confirmei 0 perda real.
- **here-string PowerShell (`@'...'@`) NÃO funciona no tool Bash** — poluiu o commit msg com `@` (corrigido via `--amend -F arquivo`). Usar `-F` pra mensagens multi-linha no Bash.
- **Fantasmas de case-fold no Windows** (`pt-BR`→`pt-br`, `Nfe-`→`nfe-`) aparecem como ` M` em worktree fresco — nunca stage; manter PR 1-intent.

## Pointers detalhados
- Prompt origem: `prototipo-ui-patch/PROMPT_PARA_CODE_DEDUPE-FINANCEIRO-BUNDLE.md` (via Cowork serve URL).
- Retorno Cowork: `prototipo-ui/CODE_NOTES.md` (entrada 2026-06-02 18:05).
- Handoff irmão (C1 origem): `memory/handoffs/2026-06-02-1716-design-handoff-appshell-roxo-reforco.md`.
