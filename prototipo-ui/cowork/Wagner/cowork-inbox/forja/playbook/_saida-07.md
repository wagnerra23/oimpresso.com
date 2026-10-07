---
sessao: "_saida-07"
thread: "07 · alvo.mjs aceita clique em cadeia"
dono: "[CL]"
data: 2026-10-07
tipo: recibo
entregue_em: "#8948"
base_lida: wagnerra23/oimpresso.com@main d9034eb990
---
# _saida-07

## Entregue
Pelo #8948. Das duas formas que a ficha oferece, escolhi o **`--clicar` repetível**. É a mais
barata no código atual: o `--clicar` de um passo já existia com a espera de quietude, e a cadeia
reaproveita o mesmo laço. A outra forma (`--storage chave=valor`) não foi feita.

- `scripts/design-sync/alvo.mjs`: cada `--clicar` é um passo, na ordem dada, e cada passo espera
  estabilizar antes do próximo. Com 1 clique o JSON grava `clicar` como string, igual a antes;
  com 2 ou mais, como array. O passo que não casa sai com exit 2, nomeando o passo
  ("passo 1 de 2").
- `scripts/qa/secao-check.mjs`: repassa cada passo da cadeia como um `--clicar`. Está fora do
  prefixo da ficha, mas sem ele um alvo com cadeia não se reproduz no gate.

A prova do índice (`contem "--storage"`) não casa, porque a flag não é `--storage`. A correção
está em `_ERRATA-INDICE-2026-10-07.md`.

## Provas
- `alvo.mjs --selftest --browser`: 27/27, com 2 checks novos (fixture de 2 cliques: com os 2 a
  seção mede 3 filhos; com 1 clique só ela sai `ausente: true`; fora de ordem → NÃO MEDI, exit 2,
  nomeando o passo 1).
- Mutante: com a cadeia cortada no 1º passo (`passos.slice(0, 1)`), o check novo cai (26/27).
  Restaurado e conferido por hash.
- Guarda da ficha: os 3 alvos da A1 re-medidos com o `alvo.mjs` novo, com as flags gravadas em
  cada JSON, saem **byte-idênticos** ao versionado (`forja--aprovacoes--index`, `forja--cockpit`,
  `forja--trabalho--index`). Controle: comparar dois alvos diferentes acusa diferença.
- `secao:check --servir-espelho` dos alvos antigos com `clicar` string (`financeiro--unificado`,
  `forja--trabalho--index`, `nfe-brasil--tributacao--excecoes`): conforme.

## Onde divergi da ficha
A ficha pede "com 1 clique só → NÃO MEDI rc=2". O `alvo.mjs` não tem como saber que faltou um
passo: ele mede o que a página tem. Com 1 clique, a seção do 2º passo sai `ausente: true` (é o
contrato que o `--clicar` já tinha), e o `secao-check` reprova o alvo por slot ausente. O exit 2
fica para o passo que não casa nenhum elemento, que é o que o instrumento consegue decidir.
