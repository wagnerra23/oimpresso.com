---
title: "Produto variável: ao escolher o modelo (ex.: Cor), a grade já vem com todos os valores?"
status: proposto
date: "2026-11-19"
owners: [W]
proposed_by: Claude Code, a pedido de [M]
parent_module: Produto
related_specs:
  - memory/requisitos/Produto/SDD-tela-cadastro-produto-v1.0.md (UC-PCAD-32)
---

# A grade já vem preenchida com os valores do modelo?

> **Status: `proposto`.** Merge deste PR = "sim: ao escolher o modelo, a grade vem com todos os
> valores dele, e a pessoa apaga os que não quer". Nenhum código muda aqui. A mudança vem num PR
> depois do merge.

## Onde fica

No Novo produto, produto variável. A pessoa escolhe o modelo da variação, que é cadastrado pela
empresa em Cadastros → Variações. Depois preenche uma linha por valor, com preço de compra e de venda.

## Exemplo: modelo "Cor" com Azul, Verde, Preto, Branco e Vermelho. A camiseta vem só em Azul e Verde.

| | Como está na tela nova | Como era no sistema antigo |
|---|---|---|
| Ao escolher "Cor" | 1 linha vazia | 5 linhas, uma por cor, e uma lista para desmarcar |
| Para ficar só com Azul e Verde | Digita ou escolhe "Azul" na lista, aperta Enter, faz o mesmo com "Verde" | Desmarca Preto, Branco e Vermelho |
| Cliques e teclas | 2 valores escolhidos | 3 valores desmarcados |
| Se o modelo tiver 30 tamanhos e o produto usar 3 | Escolhe 3 | Desmarca 27 |

O seu protótipo (`produto-blade-forms.jsx`) também começa com 1 linha vazia.

## As duas saídas

- **A: preencher com todos os valores**, como o sistema antigo. Mais rápido quando o produto usa quase
  todos os valores do modelo.
- **B: manter 1 linha vazia** (como está hoje e no protótipo). Mais rápido quando o produto usa poucos
  valores de um modelo grande.

Não temos dado de quantos valores os modelos das empresas têm, nem de quantos cada produto usa.
Se quiser, contamos antes de você decidir.

## Para dizer sim ou não

- **Sim à A:** mergeie este PR.
- **Fica como está (B):** comente "manter" no PR e feche.
- **Quer a contagem antes:** comente "contar" no PR.
