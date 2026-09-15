---
date: "2026-09-15"
time: "20:41 UTC"
slug: complemento-lc10-required-falso
tldr: "COMPLEMENTO do handoff das 19:41 (mesma sessao), que e append-only e por isso nao pode citar o que veio depois dele. Cobre 3 PRs posteriores: o proprio handoff (#7352), o conserto do TL;DR que perdeu a janela de merge (#7354) e o rec LC-10 8a (#7359) — este ultimo registrando um `required` falso que escrevi no ASSUNTO de um commit, depois de o ciclo-adversary REJEITAR a classe nova que eu ia abrir."
cycle: CYCLE-08
prs: [7352, 7354, 7359]
decided_by: [W]
related_adrs:
  - 0399-aposentar-rubrica-module-grade-gate-e-baseline
  - 0344-two-strikes-cobre-processo
next_steps:
  - "Nada pendente — os 16 PRs da sessao estao em main e nenhum ficou aberto"
  - "[W] decidir se o `$?`-apos-pipe vira par P7 do block-sonda-que-mente (deixei no rec do LC-08; havia sessao viva no P5)"
  - "Se um dia valer fechar o vao do push-pos-merge, e ESTENDER `scripts/gh/safe-merge.sh` (camada 2 confere no merge, e cega a push posterior) — nunca hook novo, e o numerador nao e medivel de transcript"
---

# Handoff (complemento) — o que veio depois das 19:41

> **Por que existe:** o handoff [`2026-09-15-1941`](2026-09-15-1941-module-grade-onda5-e-os-residuos.md)
> fecha a Onda 5/5 da ADR 0399 (13 PRs) e e **append-only** — nao pode citar os 3 PRs que vieram
> depois dele. Este complemento cobre so o delta. O estado geral e o daquele.

## TL;DR

O handoff das 19:41 e append-only e nao podia citar o que veio depois dele. Este cobre 3 PRs:
o proprio handoff (#7352), o `## TL;DR` que faltava no session log (#7354, e o commit perdeu a
janela de merge) e o rec **LC-10 8a** (#7359) — que registra um `required` falso que escrevi no
ASSUNTO de um commit, **depois** de o `ciclo-adversary` rejeitar a classe nova que eu ia abrir.
Os 16 PRs da sessao estao em `main`.

## Estado MCP no momento do fechamento

Igual ao do handoff das 19:41 e pela mesma razao: o servidor MCP `oimpresso` **nao esta exposto
como tool nesta sessao** (o Daily Brief chegou pelo hook `brief-fetch-curl` do SessionStart).
Checklist pelos fallbacks canonicos de [`how-trabalhar.md` §Fallback](../how-trabalhar.md),
medido as 20:41 UTC:

- **PRs da sessao:** 16, **todos MERGED** — nenhum aberto (conferido cruzando a lista desta sessao
  com `gh pr list --state open`; o filtro `--author @me` devolve 9 abertos, mas **nenhum e desta
  sessao** — todos os agentes commitam com a mesma conta git).
- **CI do head de `main`** (`0b1de6b12fa` na medicao): 6 success · 27 em fila/rodando · **0 falhas**.
- **Handoffs irmaos apos o meu das 19:41:** 1 (`2026-09-15-2011-rec-suficiencia-e-dedupe-lc08.md`,
  outra sessao) — indice conferido com `fetch` imediatamente antes de inserir a linha.
- **Ledger em `origin/main`:** LC-08 **162** · LC-23 **6** · LC-10 **8** (contadores derivados pelo
  hook, lidos apos o merge do #7359).

## Os 3 PRs

| PR | O que |
|---|---|
| **#7352** | o proprio handoff + session log + indice (R12) |
| **#7354** | `## TL;DR` no session log — o check `Schema session log` reprovava |
| **#7359** | ledger: **LC-10 7→8** |

## O que o #7359 registra, e por que nao e o que eu propus

Eu ia abrir **classe nova** — *"push nao e evidencia de que o conserto entrou no PR"* — depois de
duas vezes nesta sessao pushar conserto em PR ja mergeado (#7329→#7334, #7352→#7354, commits
orfaos recuperados por cherry-pick). O `ciclo-adversary` **REJEITOU**, e as tres razoes se
sustentaram quando medi:

1. **A classe ja existe** em LC-08 (recs `08-03` e `08-04`). Meu grep buscou
   `orfao|perdeu a janela|merge-base` e o ledger diz *"PR mergeado"* / *"state MERGED"* — claim de
   ausencia dependente de vocabulario e ela mesma LC-08 (§5 2026-07-28).
2. **Meu oraculo era PIOR:** `git merge-base --is-ancestor` da falso pra **todo** commit de PR
   aberto, logo nao discrimina. Quem separa e `gh pr view --json state == MERGED` + `headRefOid`,
   que a skill `commit-discipline` §Merge seguro ja prescreve.
3. **O tema tem dono** (`scripts/gh/safe-merge.sh`), entao classe nova seria regua duplicada.

**O que de fato conta, e ficou:** escrevi *"o check **required** exige secao no CORPO"* no
**assunto do commit `a75f571ecb3`**, permanente em `main`. Medido: uniao
`classic_protection ∪ rulesets` = **47 contexts, ZERO** casando `session`/`schema` — **nao e
required**, e o #7352 prova (mergeou legitimamente com ele vermelho). LC-10 exata, agravada por
estar em assunto de commit, que e append-only de fato.

## Recibo do merge final

O #7359 foi mergeado por `scripts/gh/safe-merge.sh 7359 squash` (SHA pinado no servidor +
conferencia dos arquivos em `origin/main` apos). **A 1a tentativa foi RECUSADA com `DESYNC`** — e
corretamente: eu estava em detached HEAD em `origin/main` (tinha feito checkout pra rodar o hook),
entao ele comparou a arvore errada. Voltei a branch, os SHAs bateram (`d1a28bda56a` dos dois
lados) e passou. A recusa foi o mecanismo funcionando.

## Estado

**16 PRs da sessao, todos em `main`.** Ledger: **LC-08 162 · LC-23 6 · LC-10 8** — os tres
passaram pelo `ciclo-adversary` antes de virar canon (1a rodada: 9 recs propostos viraram 1, 2
itens rejeitados; 2a: a classe nova caiu inteira).
