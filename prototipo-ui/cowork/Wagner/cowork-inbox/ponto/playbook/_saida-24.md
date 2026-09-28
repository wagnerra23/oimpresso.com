---
thread: "24 · gap.md — Importações (3 telas)"
dono: "[CL]"
estado: feito
base_lida: wagnerra23/oimpresso.com@main e4289e688 (2026-09-28)
prefixo_tocado: memory/requisitos/Ponto/importacoes-{index,create,show}-gap.md
veredito: "entregue — 3 gap.md; Create inteiro já está à frente no vivo; Show troca tempo real (vivo) por diagnóstico (protótipo)"
---
# _saida-24 · gap.md — Importações (3 telas)

## Achados

| tela | resultado da medição |
|---|---|
| Index | Vivo ordena no servidor e tem empty state com CTA — as 2 lacunas eram do protótipo. Filtro por estado/tipo falta **nos dois**: `D-IMP-FILTRO` = ENTRA. Colunas ID e "linhas com erro" ficam para decidir (sem `D-*`). |
| Create | As 4 lacunas que a thread via no protótipo **já estão resolvidas no vivo**: `accept=".txt"`, os 4 passos com SHA-256, barra de progresso e redirect ao Show (`ImportacaoController.php:92-94`). Rota própria é `D-PONTO-DETALHE`. |
| Show | Polling de 3s e alerta já existem no vivo (`Show.tsx:46-52`, `:82-88`). Faltam diagnóstico e amostra de erros (`D-IMP-EXTRAS` = INCORPORA). ⚠️ O alerta vermelho usa o campo `log` (`ImportacaoController.php:120`), então pode aparecer em importação bem-sucedida — leitura de código, **não medido em runtime**. |

## Como foi medido

- Protótipo relido no build importado no #8067 (`ponto-telas.jsx`, 1.062 linhas). As linhas que a thread citava eram de um build anterior e **não** foram reaproveitadas.
- Lado vivo lido no `.tsx` e no Controller desta base, com linha. Nenhum `.tsx` destas telas tem `data-contract` (`grep -n data-contract` = 0 nas 3 telas), então toda âncora é linha-only até a thread 17.
- O caso vem do charter e do protótipo, nunca do código (§5 2026-06-05). O código só confirma o que o vivo faz.

## Fora do prefixo, declarado

- `memory/requisitos/Ponto/importacoes-{index,create,show}.map.json` — derivados por `scripts/design/gerar-map.mjs` (esqueleto no scratch, depois cópia) com as âncoras medidas preenchidas. `design-code-map-check.mjs --check --strict` rc=0.

## O que não fiz

- Nenhuma mudança em `resources/js/Pages/**` (`nao_toca`). Os pedidos ficam nos gap.md para as threads 17, 27 e 28.
