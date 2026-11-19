---
title: "Composição: o insumo repetido na receita 5 da empresa 1 é proposital ou erro?"
status: proposto
date: "2026-11-19"
owners: [W]
proposed_by: Claude Code, a pedido de [M]
parent_module: Manufacturing
related_adrs: [448]
---

# O insumo repetido na receita 5

> **Status: `proposto`.** Merge deste PR = "é erro de cadastro: pode apagar a linha que sobra antes da
> cópia". Nenhum código muda aqui.

## O que achamos

Na simulação da cópia das receitas (ADR 0448, etapa 2), a receita 5 da empresa 1 tem o **mesmo
insumo duas vezes**:

| Linha | Insumo | Quantidade | Grupo |
|---|---|---|---|
| 1 | Homocinética Fiat Palio/Siena/Fiorino 1.5/1.6 (variação 11003) | 1 | sem grupo |
| 2 | Homocinética Fiat Palio/Siena/Fiorino 1.5/1.6 (variação 11003) | 1 | grupo 1 |

O produto da receita se chama "Documentação produto".

## Exemplo do que isso faz hoje

Quando o insumo aparece duas vezes, o sistema **soma**. Então, para fazer **1** "Documentação produto",
ele tira **2** homocinéticas do estoque.

| Se for... | Para fazer 1 unidade, o estoque perde |
|---|---|
| Como está hoje (duas linhas) | 2 homocinéticas |
| Uma linha só | 1 homocinética |

A cópia para a composição nova mantém as duas linhas, então o resultado continua igual: 2 por unidade.

## A pergunta

As duas linhas são de propósito, ou alguém cadastrou o insumo duas vezes sem querer?

O nome do produto e o insumo de peça de carro sugerem receita de teste, mas quem sabe é você.

## Para responder

- **É erro (pode apagar uma linha):** mergeie este PR. Apagamos a linha do grupo 1 na Fabricação,
  rodamos a simulação de novo e só então gravamos a cópia.
- **É de propósito:** comente "manter" no PR e feche. A cópia leva as duas linhas como estão.
