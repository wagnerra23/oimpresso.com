---
date: "2026-07-27"
time: "20:56 BRT"
slug: uc-parser-fonte-unica
tldr: "Os consumidores de ucHeadRe migraram pra fonte única — mas a premissa do pedido caiu na medição: 4 dos 5 usos querem o BLOCO, não o id, então a lib ganhou ucBlocksInCasos antes de migrar ninguém. 4 PRs mergeados, equivalência provada byte a byte, zero baseline regravado. Fica aberto um drift REAL entre dois gates required (screen-coverage-map perde 3 UC com sufixo letra) que o DoD por grep não alcança."
decided_by: ["W"]
cycle: null
prs: [4871, 4875, 4888, 4889]
related_adrs: ["0264-governanca-executavel-trio-dominio-e2e", "0256-knowledge-survival-meia-vida-catraca-sentinela"]
next_steps:
  - "screen-coverage-map.mjs::ucsFromCasos perde UC-DSR-08b/01b/04b (regex sem [a-zA-Z]? de sufixo) — dois gates required discordam: casos-gate cobra os 3, screen-coverage-gate não os vê. Task já iniciada por [W] em sessão paralela"
  - "Ao migrar o ucsFromCasos: é mudança de comportamento (178→181) em gate required — medir impacto no baseline e decidir com [W]; NÃO regravar baseline pra passar"
  - "Estender o selftest de screen-coverage-map.mjs com o caso de sufixo letra (hoje só cobre ~~UC~~ tachado — por isso o drift passou)"
---

# Parser de UC — fonte única fecha o ciclo

## Estado MCP no momento do fechamento

- `cycles-active`: **nenhum cycle ATIVO** em COPI
- `my-work` (@wagner): 8 tasks em REVIEW — US-TR-309/310/305/306/311, US-PG-008, US-PROD-027/025. **Nenhuma relacionada** a esta sessão (refactor de governança, sem US)
- `decisions-search`: nada novo desde o último handoff que toque o tema; trabalho segue [ADR 0264](../decisions/0264-governanca-executavel-trio-dominio-e2e.md) — **sem ADR nova** (refactor mecânico, zero decisão arquitetural)
- Handoffs irmãos de hoje (7): nenhum cobre parser/uc-regex — sem duplicação

## O que aconteceu

O [#4843](https://github.com/wagnerra23/oimpresso.com/pull/4843) matou o regex duplicado, mas o **uso** dele (o `split(/^##\s+/m).slice(1)` antes do match) seguiu copiado em 6 lugares. Pedido: migrar os restantes.

**A premissa caiu na medição.** Dos 5 usos, **4 consomem o BLOCO** (G-5 lê `/Status:/`, G-7 chama `declaredStatus`, `screen-grade` chama `firstStatusGlyph`, `uc-derive` extrai glyph+teste). `ucsDeclaredInCasos` devolve só ids → **não era migrável por eles**, e a cópia do split ficaria de pé. Daí `ucBlocksInCasos()` (gerador `{uc, block}`) antes de migrar ninguém. Bônus: `criar-tela.mjs` nunca importou a lib — eram 3 consumidores de código, não 5.

**Duas falhas de CI, mesma causa, nenhuma do código.** `baseline-tamper-guard` + `--check-baseline-shrink` acusaram 5 UC da Forja. Os gates estavam **certos**: o [#4879](https://github.com/wagnerra23/oimpresso.com/pull/4879) mergeou durante a sessão e regravou o baseline (181→178 UC); minhas branches tinham a versão anterior e *pareciam* reintroduzir violações já zeradas. Corrigido com `git merge origin/main` — **sem regravar baseline** (seria o atalho que desfaz o #4879 em silêncio) — e com a **equivalência re-provada no corpus novo**.

## Artefatos gerados

| PR | Arquivo | Nota |
|---|---|---|
| [#4871](https://github.com/wagnerra23/oimpresso.com/pull/4871) | `lib/uc-regex.mjs` + `.test.mjs` + `uc-derive.mjs` | selftest 9→13 |
| [#4875](https://github.com/wagnerra23/oimpresso.com/pull/4875) | `governance/criar-tela.mjs` | só comentário: aponta a fonte, não restateia o regex |
| [#4888](https://github.com/wagnerra23/oimpresso.com/pull/4888) | `qa/screen-grade-report.mjs` | advisory |
| [#4889](https://github.com/wagnerra23/oimpresso.com/pull/4889) | `casos-coverage-guard.mjs` | **motor do casos-gate REQUIRED**, sozinho |

Equivalência (diff literal, nada "parece igual"): `uc-derive` 30 arquivos/74.308 B **zero**; guard `--report`/gate/`--json`/`--check-baseline-shrink` idênticos; `--write-baseline` 17.121 B / **220 violações item a item** (só `generated_at` difere); `screen-grade:report` 3.983 B idêntico. Meta-testes do guard **46/46** com as sensibilidades G-1/G-2/G-6/G-7 — igualdade de saída não prova mordida, os meta-testes provam.

**DoD cumprido:** `git grep ucHeadRe -- 'scripts/**'` → só a lib, seu selftest (o controle-negativo o usa de propósito) e 1 comentário histórico. **Zero usos de código fora da lib.**

## Persistência

- **git:** 4 PRs em `main` (`4a603ac91e` → `202b7f17e1`/`4e95a512f5`)
- **MCP:** este handoff + session log propagam via webhook (~2min)
- **BRIEFING:** não se aplica — nenhum `Modules/<X>/` tocado

## O que fica ABERTO (o achado que o DoD não alcança)

`git grep <símbolo>` **só acha quem já usa a fonte única** — quem tem implementação própria escapa. Varrendo `split(/^##`, achei `screen-coverage-map.mjs::ucsFromCasos` com regex próprio **sem `[a-zA-Z]?`**: perde `UC-DSR-08b/01b/04b` (lib=181 × ele=178). `casos-gate` cobra os 3; `screen-coverage-gate` não os vê — **dois required discordando do mesmo fato**. Detalhe medido no session log.

## Próximos passos pra retomar

```bash
npm run casos:check && node --test scripts/lib/uc-regex.test.mjs
```

## Pointers detalhados

[Session log](../sessions/2026-07-27-uc-parser-fonte-unica-consumidores.md) — medição uso-a-uso, as 3 sondas do drift, notas operacionais (`--base` que não pegou, rate-limit GraphQL, fila de 819 runs).
