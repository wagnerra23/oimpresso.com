---
thread: "23 · gap.md — Colaboradores"
dono: "[CL]"
estado: feito
base_lida: wagnerra23/oimpresso.com@main e4289e688 (2026-09-28)
prefixo_tocado: memory/requisitos/Ponto/colaboradores-{index,edit}-gap.md
veredito: "entregue — 2 gap.md; D-COLAB-CPF já aplicado nos dois lados; vivo à frente em datas e em Switch"
---
# _saida-23 · gap.md — Colaboradores

## Achados

| tela | resultado da medição |
|---|---|
| Index | **CPF e PIS já redigidos no vivo** (`redigirDigitos`, `Index.tsx:144-153`) — o "PARAR SE" da thread se cumpriu, `D-COLAB-CPF` está nos dois lados. Faltam no vivo os 2 filtros (Escala, **Sem PIS cadastrado**) e as 2 colunas (Último ponto, Saldo BH) que `D-COLAB-COLUNAS` incorpora. |
| Edit | **Vivo à frente em 2 pontos:** usa `Switch` (`Edit.tsx:139`, `:146`) e `type="date"` com `after:admissao` no servidor (`ColaboradorController.php:102`). `D-PONTO-ATOMO-BOOLEANO` é catch-up do protótipo. Falta no vivo o card "Dados do HRM" (cargo e id HRM não vêm no payload). |

Nenhum valor de CPF ou PIS foi copiado para doc, commit ou PR.

## Como foi medido

- Protótipo relido no build importado no #8067 (`ponto-telas.jsx`, 1.062 linhas). As linhas que a thread citava eram de um build anterior e **não** foram reaproveitadas.
- Lado vivo lido no `.tsx` e no Controller desta base, com linha. Nenhum `.tsx` destas telas tem `data-contract` (`grep -n data-contract` = 0 nas 2 telas), então toda âncora é linha-only até a thread 17.
- O caso vem do charter e do protótipo, nunca do código (§5 2026-06-05). O código só confirma o que o vivo faz.

## Fora do prefixo, declarado

- `memory/requisitos/Ponto/colaboradores-{index,edit}.map.json` — derivados por `scripts/design/gerar-map.mjs` (esqueleto no scratch, depois cópia) com as âncoras medidas preenchidas. `design-code-map-check.mjs --check --strict` rc=0.

## O que não fiz

- Nenhuma mudança em `resources/js/Pages/**` (`nao_toca`). Os pedidos ficam nos gap.md para as threads 17, 27 e 28.
