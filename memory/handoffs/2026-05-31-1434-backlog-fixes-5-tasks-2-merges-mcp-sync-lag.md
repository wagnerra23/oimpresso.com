---
date: 2026-05-31
hour: "14:34 BRT"
topic: "Worklist → backlog priorizado + 5 tasks US-_DESIGNSYSTEM-014..018 + 2 PRs mergeados pro main (#2035 #2036) + investigação sync MCP"
duration: "~1h (continuação do 13:54)"
authors: [wagner, claude-opus-4-8]
branch: feat/staging-ct100
prs: [2035, 2036]
---

# Worklist → backlog → 5 tasks no main (continuação do handoff 13:54)

## Estado MCP no momento
`tasks-list status:todo` = 60 tasks ativas (PCP/PG/COPI/Fiscal/etc). **US-_DESIGNSYSTEM-014..018 NÃO aparecem** apesar de estarem no `origin/main` — indexador SPEC→`mcp_tasks` é cron server-side (CT100), não webhook ~2min. Sincronizam no próximo ciclo.

## O que aconteceu (Wagner conduziu turno a turno: "ajudar?" → "melhorar?" → "ok" → "a" → "merge" → "a")
1. **"o que mais pode ajudar?"** → propus 4 opções, recomendei **(1) worklist→backlog**. Wagner: "caramba isso mesmo pode melhorar?".
2. **Construí `backlog.mjs`** (3ª peça: consolidate=placar · backlog=plano). Alavancagem das 239 telas: **17 <70 mec** (todas R1+R2, money modules) · **92 quick-wins** (1 regra) · alcance R2=141 R7=80 R1=40 R6=21 R4=18 R9=13 R3=1. **Achei e corrigi métrica degenerada** (cross70 = ceiling sempre ≥80 → reescrevi honesto). Commit `fe5ca3236`.
3. **"ok"** → propus 5 tasks (lotes por regra, close-by-evidência), Wagner aprovou. `tasks-create` ×5 → **US-_DESIGNSYSTEM-014..018** (a MCP só GEROU markdown, não gravou DB). Apendei ao SPEC.md git + commit `ea50ff4bf`.
4. **"merge"** → mergeei **#2035** (worklist scaffold+scorer) pro main via `--admin --squash` (5 checks verdes, conta única).
5. **"a"** → **#2036** (backlog.mjs + BACKLOG-FIXES.md + SPEC 5 tasks) pro main via worktree off-main + cherry-pick. **Check `SPEC` FALHOU** (`/last_updated must be string` — frontmatter com data crua, pré-existente, flagado no diff-only). Fix aspas `de64c6a30` + cherry-pick no PR → 16 checks verdes → merge `--admin` (`796bc9a22`).
6. **Investiguei sync:** esperei ~5min, `tasks-list` vazio. Confirmei (a) 5 no `origin/main`, (b) não é filtro (60 tasks, nenhuma minha) → **indexador é cron, não ~2min**. Corrigi o "live na hora".

## Artefatos (canon: `prototipo-ui/audit/` — TUDO no main)
- `backlog.mjs` + `BACKLOG-FIXES.md` (#2036) · 5 US no `_DesignSystem/SPEC.md` (#2036)
- main: `32e89b0ca` (#2035) → `796bc9a22` (#2036) · staging: `de64c6a30`

## Persistência
- **git main:** worklist + scorer + backlog + 5 tasks ✅
- **MCP DB:** 5 tasks ⏳ aguardando cron do indexador (fora do meu alcance — SSH-stdin bloqueado)

## Próximos passos pra retomar
1. Confirmar US-014..018 no `tasks-list` quando o cron rodar (re-checar).
2. **Começar US-014** (lote seguro R9+R3, sem gate) — 1º ganho concreto.
3. **Fase 2:** Cowork dispara agentes LLM pras 3 regras julgadas (R5/R8/R10).
4. Reconciliar GOLDEN-REFERENCE com as 10 regras do Cowork.

## Lições catalogadas
- **Sync MCP de SPEC→tasks é cron server-side, NÃO webhook instantâneo.** Não prometer "live na hora" — o merge torna canônico (git) na hora; a projeção MCP tem relógio próprio. `tasks-create` só gera markdown (não grava DB) — canon real exige commit do SPEC.md + cron.
- **Validador SPEC exige `last_updated` como string** (aspas). Data crua YAML vira date → `/last_updated must be string`. Diff-only flaga ao tocar o arquivo (dívida pré-existente vira sua).
- **Métrica degenerada:** "cross 70 só com mech" era tautologia (ceiling = 100−min(ds,20) ≥ 80 sempre). Conferir se um indicador discrimina antes de vendê-lo.
- **Nota mecanizada ≠ board:** conformidade-DS é necessária, não suficiente pro ≥70 do board (stubs precisam UX/Fase 2).

## Pointers detalhados
- Handoff anterior (build da worklist): [`2026-05-31-1354-worklist-auditoria-paralela-scorer-mecanizado.md`](2026-05-31-1354-worklist-auditoria-paralela-scorer-mecanizado.md)
- Backlog: [`prototipo-ui/audit/BACKLOG-FIXES.md`](../../prototipo-ui/audit/BACKLOG-FIXES.md) · tool [`backlog.mjs`](../../prototipo-ui/audit/backlog.mjs)
- 5 tasks: [`memory/requisitos/_DesignSystem/SPEC.md`](../requisitos/_DesignSystem/SPEC.md) (US-014..018)
- PRs: [#2035](https://github.com/wagnerra23/oimpresso.com/pull/2035) · [#2036](https://github.com/wagnerra23/oimpresso.com/pull/2036)
