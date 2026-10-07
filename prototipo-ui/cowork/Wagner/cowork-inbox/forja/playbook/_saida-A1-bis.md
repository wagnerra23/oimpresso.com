---
sessao: "_saida-A1-bis"
thread: "A1-bis · alvo.mjs com --clicar em cadeia + o alvo do Gantt"
dono: "[CL]"
data: 2026-10-07
tipo: recibo
base_lida: wagnerra23/oimpresso.com@main 0050ee8ad7
---
# _saida-A1-bis

Fecha o que o `_saida-A1.md` deixou sem medir. O Gantt só monta depois de dois cliques (aba
Trabalho, depois o segmento Gantt), e o `alvo.mjs` aceitava um `--clicar` só.

## O que mudou na máquina

- `scripts/design-sync/alvo.mjs`: `--clicar` passa a ser repetível. Cada ocorrência é um passo,
  na ordem dada, e cada passo espera estabilizar antes do próximo. Com 1 clique, o JSON grava
  `clicar` como string, igual a antes; com 2 ou mais, como array. O passo que não casa diz qual
  foi ("passo 1 de 2") e sai com exit 2 (não medi).
- `scripts/qa/secao-check.mjs`: repassa cada passo da cadeia como um `--clicar`, na mesma ordem.

## Provas

- `alvo.mjs --selftest --browser`: 27/27 ok, com 2 checks novos (cadeia faz a seção existir;
  cadeia fora de ordem dá exit 2 nomeando o passo 1).
- Mutante: cadeia cortada no 1º passo (`passos.slice(0, 1)`) → o check novo cai (26/27). Restaurado
  e conferido por hash (`44abd3f40e75e986` = `44abd3f40e75e986`).
- Alvos antigos com `clicar` string (`financeiro--unificado`, `forja--trabalho--index`,
  `nfe-brasil--tributacao--excecoes`): `secao:check --servir-espelho` conforme.

## Alvo do Gantt

`governance/design/targets/forja--roadmap-gantt.alvo.json`, 6 seções (visao · gantt · ancora ·
escala · corpo · totais). Medido no espelho servido em `http://127.0.0.1:5561/`, tema dark,
viewport 1280×900:

```
node scripts/design-sync/alvo.mjs --alvo http://127.0.0.1:5561/ --tela forja--roadmap-gantt --rota projects --secoes governance/design/targets/forja--roadmap-gantt.secoes.json --clicar ".fj-viewtabs > .fj-navgroup:first-child > button:nth-of-type(2)" --clicar ".fj-frentebar [role=tablist] > :nth-child(3)" --quieto-ms 2000
```

- 2 rodadas byte-idênticas (`c810e8b06e7a784f`).
- `secao:check --tela forja--roadmap-gantt --servir-espelho`: conforme.
- Injeção (`--injetar-falha .fj-g-body`): `secao:check` reprova.
- O `.fj-g-body` tem hoje 9 linhas no espelho; o export de 2026-09-03 contou 32. O dado do
  protótipo mudou desde então; o número que vale é o medido.

## O que continua aberto

- D1: este alvo mede `forja-page.jsx`, a fonte declarada pelo charter. O contrato aponta
  `forja-gantt.jsx`. Qual vale é decisão [W], e ela destrava a thread 05.
- `secao:check --todos` reprova em `jana--index`, `nfse--index` e `produto--cadastros--index`.
  Os três reprovam igual com o `alvo.mjs` do `main`, então não vêm desta mudança.
