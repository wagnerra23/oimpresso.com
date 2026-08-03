---
date: "2026-08-03"
hour: "15:52 BRT"
duration: "0.5h"
topic: "Tutorial ponta a ponta do SDD"
authors: [C]
prs: []
us: []
outcomes:
  - "O /documentacao ganhou tutorial US→dry-run→trio→lint→execução→smoke→anchor."
  - "A própria máquina passou a ensinar o uso via npm run feature:tutorial e --help."
  - "Templates deixaram de instruir cópia manual; self-test passou 35 controles."
related_adrs: ["0264-governanca-executavel-trio-dominio-e2e", "0306-strangler-spec-anchored-reconstrucao-sdd"]
---

# Sessão — tutorial SDD guiado

## TL;DR

[W] pediu documentação em estilo tutorial. O guia ganhou nove passos, exemplo real, comandos, resultados esperados, erros comuns e checklist de saída. A preferência máquina>manual foi preservada com ajuda executável no próprio `feature-lint`.

## Validação

`npm run feature:tutorial`; `npm run feature:init -- --help`; `feature:lint:selftest` 35/35; dry-run real sem pasta criada; full-tree 2 features, 0 erros/avisos; `git diff --check` verde. Nenhum teste PHP foi necessário porque não houve alteração de runtime.
