---
sessao: "_saida-A1b"
thread: "A1b · ALVO forja--roadmap-gantt (o 4º da A1)"
dono: "[CL]"
data: 2026-10-07
tipo: recibo
depende: "#8948 (thread 07)"
base_lida: wagnerra23/oimpresso.com@main d9034eb990
---
# _saida-A1b

## Entregue
`governance/design/targets/forja--roadmap-gantt.alvo.json` + `.secoes.json`, 6 seções
(visao · gantt · ancora · escala · corpo · totais), mais as 4 linhas da Forja na tabela
"Alvos exportados" do `governance/design/targets/README.md` (as 3 da A1 tinham ficado de fora,
porque o README estava fora do prefixo daquela thread).

Âncora medida: `forja-page.jsx`, como a D1 decidiu.

## Como foi medido
Espelho servido por `servirEstatico` em `http://127.0.0.1:5561/`, tema dark, viewport 1280×900,
com o `alvo.mjs` da thread 07 (#8948):

```
node scripts/design-sync/alvo.mjs --alvo http://127.0.0.1:5561/ --tela forja--roadmap-gantt --rota projects --secoes governance/design/targets/forja--roadmap-gantt.secoes.json --clicar ".fj-viewtabs > .fj-navgroup:first-child > button:nth-of-type(2)" --clicar ".fj-frentebar [role=tablist] > :nth-child(3)" --quieto-ms 2000
```

- 2 rodadas byte-idênticas (`c810e8b06e7a784f`).
- `secao:check --tela forja--roadmap-gantt --servir-espelho`: conforme (rodado com o
  `secao-check` do #8948).
- Injeção (`--injetar-falha .fj-g-body`): o `secao:check` reprova.
- O `.fj-g-body` tem hoje 9 linhas no espelho; o export de 2026-09-03 contou 32. O número que
  vale é o medido.

## Ordem de merge
Este PR entra **depois do #8948**. O alvo grava `clicar` como array, e o `secao-check` do `main`
de hoje passa o valor de `clicar` como um argumento só. Antes do #8948, o `secao-check` deste alvo falha.
