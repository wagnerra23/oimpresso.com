---
date: "2026-09-28"
topic: "doc-id-index: dono do frescor no job refresh do system-map.yml, com --refresh que recusa move pendente"
authors: ["C"]
outcomes:
  - "Medido em origin/main d0018abce: --check rc=1, 330 ids fora do índice, 0 com path mudado"
  - "Consumidores: 89 arquivos citam o nome (git grep); só doc-auto-relink --detect lê o .json para decidir"
  - "Hook de commit recusado (32/300 commits mudam o conjunto de docs; regen reescreve stats e conflita; apaga o sinal de move)"
  - "doc-id-index --refresh + step no system-map.yml; índice regenerado 2520→2850 ids; #8085 mergeado por [W]"
prs: [8085]
us: []
related_adrs: []
---

# Session log 2026-09-28 — doc-id-index ganhou dono do frescor

## TL;DR

O `governance/doc-id-index.json` estava 330 ids atrás e o `--write` não tinha invocador. O dono do frescor passou a ser o job `refresh` do `system-map.yml`, que roda `doc-id-index --refresh` todo dia; esse modo recusa regenerar quando há move pendente, porque é o sinal que o `doc-auto-relink --detect` usa. Mergeado no [#8085](https://github.com/wagnerra23/oimpresso.com/pull/8085).

## O problema
`governance/doc-id-index.json` estava atrasado e nada o mantinha. O CI roda só o `--check-collisions`, por desenho (o docblock de 30/07 explica), e o `--write` "de consolidação" não tinha invocador. Em `origin/main` d0018abce: `--check` rc=1, faltavam 330 ids (98 stamped, 30 ADRs 0388–0417, 88 handoffs, 112 sessions e 2 removidos à mão pelo #8037), e nenhum id tinha path mudado.

## A medição que decidiu
- `git grep -l doc-id-index` = 89 arquivos. `rg --hidden -g '!.git/**'` = 88: ele pula `doc-auto-relink.mjs`, que tem um byte NUL e é tratado como binário.
- Leitor do `.json` em código: só `doc-auto-relink.mjs` (`detectMoves` compara o índice commitado com o corpus). Com o índice atrasado, o `--detect` não vê doc stampado depois do último regen e movido depois.
- Hook de commit (estender `maquinas-inventario-no-commit`): 32 de 300 commits do main mudam o conjunto de docs de `memory/`. Todo regen reescreve o bloco `stats`, então dois PRs concorrentes conflitam, e regenerar no commit do move apaga o sinal do `--detect`. Recusado.
- Publicador diário `system-map.yml` (job `refresh`): escritor único, medido em 16/09 com 0 corridas. `buildIndex` custa ~0,6–1 s. Escolhido.

## O que foi feito ([#8085](https://github.com/wagnerra23/oimpresso.com/pull/8085))
- `doc-id-index.mjs`: `pendingMoves()` exportada (mesmo predicado do `detectMoves`) e modo `--refresh`, um `--write` que recusa com move pendente (exit 0 + `::warning::`). Bite-test pelo CLI no `--selftest`, 18/18. O mutante `if (false && moves.length)` derruba 1; a restauração foi conferida por sha256.
- `system-map.yml`: step `--refresh` no job `refresh` e JSON no `add-paths`.
- Índice regenerado: `--check` rc=1→0, `--check-collisions` rc=0, `--detect` 0 moves antes e depois.
