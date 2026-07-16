---
date: "2026-07-14"
time: "16:24 BRT"
slug: distiller-briefing-frontmatter-schema
tldr: "Fix do jana:distill-module-truth — DistillerModuloVerdade::montarBriefing emitia frontmatter sem os required status/updated_at do briefing.schema.json; todo BRIEFING re-destilado divergia do memory-schema-gate (BRIEFING em grace). PR #4268 aberto, CI verde. Foundation-ratchet vermelho no PR era base stale (já arrumado em main pelo #4267) — resolvido por rebase, não novo código."
prs: [4268]
related_adrs: [0291-distiller-modulo-verdade-porta-unica, 0314-poda-gates-onda-2-lei-fusoes, 0101-tests-business-id-1-nunca-cliente]
next_steps:
  - "Wagner: mergear #4268 (R10) — CI verde, só faltam E2E/Pest-Unit non-blocking"
  - "Decidir backfill dos ~11 BRIEFINGs já carimbados sem status/updated_at: jana:distill-module-truth --all no CT100 (gate Wagner/CT100 ADR 0291) vs correção manual do status por módulo"
---

## Estado MCP no momento do fechamento

- **Cycle:** nenhum ativo em COPI.
- **my-work @wagner:** 30 tasks (8 REVIEW, 8 BLOCKED, 14 TODO) — nenhuma atrelada a este fix (bug ad-hoc reportado pelo Wagner).
- **origin/main HEAD:** `1304604878` (#4270).

## O que aconteceu

Wagner reportou que `jana:distill-module-truth` reescreve `BRIEFING.md` com frontmatter incompleto. Diagnóstico: o gerador real é [`DistillerModuloVerdade::montarBriefing()`](../../Modules/Jana/Services/Memoria/DistillerModuloVerdade.php) (não o Command), que emitia só `distilled_at`/`distilled_by`/`module` — faltando `status` e `updated_at`, ambos `required` em `scripts/memory-schemas/briefing.schema.json`. Todo BRIEFING re-destilado divergia do "Memory schema gate — BRIEFING" (hoje `grace: true` = só warning; bloqueio após backfill, ADR 0314).

**Fix:** frontmatter passa a emitir `module`+`status`+`updated_at` + aliases `distilled_*`. `status` **preserva** o valor anterior da porta se for enum válido (não re-infere a cada destilação, não rebaixa módulo em produção ao re-rodar); sem valor prévio → default `em-construcao`. `updated_at == distilled_at`. Não puxa module-grade via Process de propósito (mantém o serviço puro/testável).

**Prova (mesmo stack do CI — AJV 2020 + gray-matter contra o schema real):** ANTES `valid=false` (required ausentes) → DEPOIS `valid=true` (default e preservando). `statusPreservado`: 10/10 casos.

**Foundation-ratchet (o "arrume"):** o gate advisory acusou `n_business_first` 76→77 no PR. Investigado — NÃO era do meu diff. Era **base stale**: meu PR partia de `6e0a7bfd57`, anterior ao **#4267** (`3919ee1569`) que já tinha migrado o teste ofensor (`UnificadoContaIndefinidaGuardTest.php`) de `Business::first()` pra `test()->seededTenant()`. Arrumação = **rebase** do PR sobre `origin/main` atual (herda o #4267), sem editar teste nem abrir PR novo. Pós-rebase: contador 76 = baseline, gate verde.

## Artefatos gerados

- **PR [#4268](https://github.com/wagnerra23/oimpresso.com/pull/4268)** (branch `claude/distill-briefing-frontmatter-schema`, +70/-3): fix em `DistillerModuloVerdade.php` (novo `statusPreservado` + STATUS_ENUM) + 2 testes novos em `DistillerModuloVerdadeTest.php` (required do schema + preservação do status) + asserts no teste existente. CI: 63 pass / 0 fail (incl. `Pest (Jana)`, `Foundation ratchet`, RAGAS, Module Grades); 2 pending non-blocking (E2E, Pest Unit).

## Persistência

- **git:** PR #4268 (código) + este handoff (branch `claude/handoff-distill-briefing-frontmatter`).
- **MCP:** webhook GitHub→MCP propaga o handoff ~2min pós-push.
- **BRIEFING:** N/A (fix de infra de geração, não muda capacidade de módulo).

## Próximos passos pra retomar

Mergear #4268 (Wagner, R10) → decidir backfill dos ~11 BRIEFINGs carimbados sem status/updated_at (`--all` CT100 vs manual). Retomar via `gh pr view 4268`.

## Lições catalogadas

- **CI advisory vermelho pode ser base stale, não regressão tua.** Antes de "arrumar", cheque se o ofensor já foi corrigido em `origin/main` num commit posterior à base do PR — a arrumação pode ser só `git rebase origin/main` + force-push, zero código novo. (Guard `git-base-freshness-guard` já avisava −5201 no SessionStart.)
- **O gerador do frontmatter era o serviço, não o Command** — o Command só coleta eventos e delega. Seguir a cadeia até o `file_put_contents` real antes de propor fix.

## Pointers detalhados

- Serviço: `Modules/Jana/Services/Memoria/DistillerModuloVerdade.php` (`montarBriefing` + `statusPreservado`)
- Schema: `scripts/memory-schemas/briefing.schema.json` (required: module/status/updated_at; BRIEFING em grace no `memory-schema-gate.yml`)
- Catraca: `scripts/tests/foundation-ratchet.mjs` + baseline `scripts/tests/baselines/foundation-ratchet-baseline.json`
