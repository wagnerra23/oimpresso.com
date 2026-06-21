---
date: "2026-06-11"
hour_brt: "12:05"
topic: "Árvore de componentes canônica — auditoria 57/100 → 3 PRs mergeados + enforcement"
duration: "~3h"
authors: [Wagner (aprovador), Claude Code]
---

# Handoff — árvore de componentes canônica (3 PRs + guard que sobrevive ao tempo)

## Estado MCP no momento

- Cycle **CYCLE-08** (Receita Onda A) · 17d restantes · goals 0/5 — esta sessão é infra/DS, **fora do cycle** (drift já flagado pelo brief: 114/114 commits 7d fora)
- my-work: 4 REVIEW (FIN-4, US-TR-305/306/307) · 6 BLOCKED dormentes Gold · 20 TODO (US-SELL-036 p0 topo)

## O que aconteceu

Wagner pediu auditoria ("quais componentes eu tenho? como deveria ser o otimizado? pontue") → nota **57/100** → aprovou execução com "que essa organização sobreviva ao tempo". Descoberta mudou o plano: F4 (pageheader-gate) e ADR 0253 (layout/) **já governavam** 2 dos 5 gaps apontados — trabalho focou no que NÃO existia.

1. **[#2539](https://github.com/wagnerra23/oimpresso.com/pull/2539)** — `Components/{clientes,ConsultaOs,jana}` → `Pages/<Mod>/_components/` (domínio single-módulo sai da global; board/cockpit/Site/NfeBrasil ficam justificados). Zero visual.
2. **[#2540](https://github.com/wagnerra23/oimpresso.com/pull/2540)** — BR inputs canônicos em `ui/`: `NumericInputPtBR` **promovido** de Sells (R-DS-001 regra-de-2) + `DocumentInput` (CPF/CNPJ mod 11) + `PhoneInput` novos + 11 testes vitest/axe + REGISTRY.
3. **[#2542](https://github.com/wagnerra23/oimpresso.com/pull/2542)** — `components-tree-guard` (allowlist top-level de Components/ + convenção `_components`) + `.claude/rules/components.md` + `@deprecated` no `shared/PageHeader` + **ADR proposal** `2026-06-11-arvore-componentes-canonica.md` (status `proposed` — **Wagner decide**).

**Decisão consciente:** `patterns/ListPage` (PT-01 as code) NÃO foi codado — Slot 1 do PT-01 referencia o header legacy enquanto F4 migra 104 telas; entrou como roadmap na ADR proposal (processo ADR 0253: ADR → piloto → gate visual).

## Artefatos gerados (tudo na main)

- `scripts/components-tree-guard.mjs` + `.github/workflows/components-tree-guard.yml` + npm `components:check`
- `.claude/rules/components.md` (+ row no rules/README)
- `ui/numeric-input-ptbr.tsx` · `ui/document-input.tsx` · `ui/phone-input.tsx` · `tests/br-inputs.test.tsx`
- `memory/decisions/proposals/2026-06-11-arvore-componentes-canonica.md` (**proposed**)
- CHANGELOG DS 0.6.9 / 0.6.10 / 0.6.11 · REGISTRY_DS_COMPONENTES +3 linhas

## Persistência

- **git:** 3 PRs squash-mergeados na main · branches e worktree temporário removidos
- **MCP:** webhook GitHub→MCP propaga (~2min pós-merge)
- **BRIEFING:** nenhum módulo de produto tocado funcionalmente (refactor DS cross-cutting)

## Próximos passos pra retomar

1. **Wagner:** aprovar/editar `memory/decisions/proposals/2026-06-11-arvore-componentes-canonica.md` → vira ADR numerada
2. Se quiser ListPage: abrir ADR própria + piloto 1 tela + screenshot gate (roadmap §3 da proposal)

## Lições catalogadas

- **Baselines path-keyed** (eslint, layout, ui-lint — este com `\/` escapado!) quebram em `git mv` — re-keyar entries no mesmo PR, nunca regenerar tudo. Documentado na rule `components.md`.
- Tocar arquivo `.md` legado dispara validação de schema/PII no arquivo INTEIRO (RUNBOOK Crm precisou de frontmatter + `pii-allowlist` em placeholder de máscara).
- Auditoria rasa erra: 2 dos 5 "gaps" da nota 57/100 já eram governados (F4, ADR 0253) — sempre cruzar com MANUAL-CSS-JS §5 antes de propor guard novo.
- `visual-regression` flakou por Docker Hub timeout (infra) — rerun resolveu.

## Pointers detalhados

- PRs: #2539 · #2540 · #2542 (descrições completas com gates e diffs)
- Proposal: `memory/decisions/proposals/2026-06-11-arvore-componentes-canonica.md`
- Rule: `.claude/rules/components.md` · Guard: `scripts/components-tree-guard.mjs`
