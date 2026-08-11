---
date: "2026-08-09"
time: "00:50 BRT"
slug: "varredura-prs-travados-2-vitimas-remanescentes"
tldr: "A varredura dos PRs abertos achou 2 vítimas remanescentes do deadlock da ADR 0370, além das 4 que o handoff das 19:36 tratou. O #5071 estava preso desde 31/07 com 94 pass e ZERO falhas — e 3 required que nunca nasceram; destravado (merge de main + índice de ADR REGENERADO, não escolhido) e mergeado. O #5069 foi fechado por [W]: alterava 9 scripts de governança que o main também evoluiu."
cycle: null
prs: [5071, 5069]
us: []
decided_by: [W]
next_steps:
  - "Gap sem máquina: ninguém mede se PR JÁ ABERTO satisfaz required recém-promovido (o required-always-run mede o main)"
  - "Restam abertos: #5473, #5397 e #5119 (draft, 7 falhas reais — dívida, não deadlock)"
---

# Handoff — a varredura achou 2 vítimas que o conserto de hoje não alcançou

## Estado MCP no momento do fechamento

- `cycles-active` → **nenhum cycle ATIVO em COPI**.
- `my-work` → 6 tasks, **todas em REVIEW** (`US-TR-309/310/305`, `US-PG-008`, `US-PROD-027`, `US-INFRA-023`).
- Handoff **dono do tema**: [`2026-08-08-1936-deadlock-required-promocao-e-consolidacao-recusada`](2026-08-08-1936-deadlock-required-promocao-e-consolidacao-recusada.md) — ele diagnosticou a causa e destravou **4 PRs**. Este é a **continuação**, não duplicata: a varredura achou **mais 2** que ficaram de fora.
- Handoff anterior desta sessão: [`2026-08-08-2249-valor-estoque-2-decisoes-e-o-pr-sem-required`](2026-08-08-2249-valor-estoque-2-decisoes-e-o-pr-sem-required.md).

## O que aconteceu

[W] pediu para varrer os PRs abertos procurando a condição que eu tinha descrito no handoff anterior: **verde e vazio** — check sem falha, mas required que nunca nasceram.

Dos **6** abertos na hora: **2 na condição**, **1 com dívida real** (#5119, draft, 7 falhas — não é deadlock) e 3 limpos.

**O #5071 era o caso puro.** 94 pass, **zero falhas, zero pendentes**, e **3 required ausentes** — e confirmei que os três (`catalog.json == SCOPEs`, `SUPERFICIE.md == árvore`, `Self-test — classificação por papel`) pertencem a `catalog-graph.yml` e `module-surface.yml`: **os mesmos dois workflows** promovidos no commit `9199a82a12f` (ADR 0370). Mesma causa, mesmos jobs, só que este ficou de fora do conserto.

Estava preso desde **31/07 — 8 dias** com cara de PR verde.

O conserto: merge de `main`, e o único conflito foi o `_INDEX-GENERATED.md`. Como é **arquivo gerado**, resolvi **regenerando** (`adr-index-generate.mjs --write`), não escolhendo um lado. Dois riscos conferidos antes de encostar: o número **0362 estava livre** (a sequência em `main` pulava de 0361 para 0363 — o buraco era exatamente desta ADR) e o diff final contra `main` é **só a ADR nova + o índice**, nada arrastado dos 340 commits. Required foram de 3 ausentes → 0, `mergeState=CLEAN`, mergeado. Em `main` a sequência 0360→0366 ficou **sem buraco**.

**O #5069 não era o mesmo caso**, apesar da assinatura parecida — e por isso **não o toquei**. Medido: 14 commits, 74 arquivos, +2771/−642, e **9 scripts de governança que o `main` também mexeu** desde a base (`system-map.mjs` com 5 commits no intervalo), mais uma falha real no `Governance Gate` (required). Resolver ali é decidir **qual versão da lógica vale**, em código que faz gates morderem — não é resolução mecânica. Devolvi a decisão; **[W] fechou o PR**, que era a saída certa: o trabalho já tinha sido superado por `main` em 9 dias.

## Persistência

- **git:** [#5071](https://github.com/wagnerra23/oimpresso.com/pull/5071) → **mergeado** (`ec739bffdb2`, squash) — traz a ADR `0362-errata-0360-admin-nao-respondia-403-o-bypass-estava-ligado`. [#5069](https://github.com/wagnerra23/oimpresso.com/pull/5069) → **CLOSED** por [W].
- **CI do #5071:** 101 pass · 3 skipping · 0 falhas · **41/41 required presentes** (conferidos nominalmente contra `required-checks-baseline.json`, não por "0 falhas").
- **MCP:** nenhuma task tocada — a varredura não tem US própria.

## O gap estrutural, que segue sem máquina

Rodei o [`required-always-run.mjs`](../../scripts/governance/required-always-run.mjs): **`41 required · 41 always-run · 0 filtrados` — verde**. E ele está **certo**: mede o `main`, onde todo required nasce.

O que ninguém mede é se um **PR já aberto** consegue satisfazer um check **recém-promovido**. Foi esse vão que deixou 2 PRs parados por mais de uma semana sem sinal nenhum — e o handoff das 19:36 já o tinha nomeado. Continua aberto: **não armei máquina**, porque seria gate novo e a regra exige FP medido antes ([ADR 0344](../decisions/0344-two-strikes-cobre-processo.md) — 1ª ocorrência conserta, não codifica; esta é a 2ª manifestação do mesmo vão, então já há material se [W] quiser promover).

## Lições catalogadas

- **`0 falhas` não prova que o gate nasceu.** O #5071 exibia 94 verdes e estava estruturalmente incapaz de mergear. O que o revelou foi **contar os checks** e comparar com um PR irmão (94 × 103), depois conferir os required **nominalmente**.
- **Conflito em arquivo GERADO não se resolve escolhendo lado — se regenera.** O `_INDEX-GENERATED.md` tem o gerador escrito no próprio cabeçalho; escolher `--ours`/`--theirs` teria produzido um índice que não corresponde à árvore.
- **Assinatura igual não é causa igual.** #5071 e #5069 pareciam o mesmo problema; um era 2 arquivos e resolveu em minutos, o outro eram 9 scripts de gate em disputa com `main`. Medir o tamanho **antes** de encostar foi o que separou os dois.
- **Vazio de comando não é evidência — pode ser falha.** Ao medir os PRs #5465/#5466 o `git fetch` falhou (`couldn't find remote ref`) e o `| tail` **mascarou o exit code**, devolvendo `rc=0`. Se eu tivesse lido o vazio como "sem conflito", teria reportado os dois como limpos. Mesma família já catalogada no §5 (`crontab -l`, `git grep -F` com `\E`).
- **Errei uma inferência e ela vale registro:** reportei #5465/#5466 como *"órfãos de branch, talvez abandonados"* porque o `git ls-remote --heads` não listava as refs. A medição era o que era, mas a **conclusão** foi além do dado — os dois eram trabalho vivo e mergearam minutos depois (00:17 e 00:25). Ausência de ref num instante não significa descarte.
