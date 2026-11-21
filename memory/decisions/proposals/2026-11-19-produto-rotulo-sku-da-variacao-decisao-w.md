---
title: "Produto variável: o texto da opção \"SKU + número da variação\" não bate com o SKU gravado"
status: proposto
date: "2026-11-19"
owners: [W]
proposed_by: Claude Code, a pedido de [M]
parent_module: Produto
related_specs:
  - memory/requisitos/Produto/SDD-tela-cadastro-produto-v1.0.md (UC-PCAD-32)
---

# O texto da opção do SKU da variação

> **Status: `proposto`.** Merge deste PR = "sim: troca o texto para **SKU + valor da variação
> (ex.: CAMAzul)** e mantém o SKU como o sistema grava hoje". Nenhum código muda aqui. A troca do texto
> vem num PR pequeno depois do merge.

## Onde fica

No Novo produto, produto variável, campo "Formato do SKU da variação". Ele tem duas opções:

1. **Número do SKU**
2. **SKU + número da variação**

O texto da opção 2 é o do seu protótipo (`produto-blade-forms.jsx`). O problema é que essa opção
**não** usa número nenhum.

## Exemplo: camiseta com SKU `CAM`, nas cores Azul e Verde

| | Opção 1: Número do SKU | Opção 2: hoje se chama "SKU + número da variação" |
|---|---|---|
| O que o sistema grava | `CAM-1`, `CAM-2` | `CAMAzul`, `CAMVerde` |
| O que o protótipo mostrava | `CAM-1`, `CAM-2` | `CAM-AZU`, `CAM-VER` |
| O que o sistema antigo dizia | — | "Variação de SKU (Exemplo -> ABCS, ABCM)" |

Na opção 1 o hífen aparece nos códigos de barras C128 e C39. Nos outros tipos sai `CAM1`, `CAM2`.

A opção 2 junta o SKU com o **nome inteiro** do valor, sem espaço, sem hífen e sem acento. Isso é o
que o sistema já gravava antes da tela nova, e a tela nova mostra a mesma prévia. Só o texto da opção
está errado.

Um detalhe que o exemplo mostra: letra com acento é removida, não trocada. "Médio" vira `CAMMdio`.

## As duas saídas

| | A (recomendado): trocar só o texto | B: mudar o SKU para o formato do protótipo |
|---|---|---|
| Texto da opção | **SKU + valor da variação (ex.: CAMAzul)** | SKU + número da variação |
| SKU gravado | `CAMAzul` (igual a hoje) | `CAM-AZU` (muda nos produtos novos) |
| Produtos já cadastrados | Não mudam | Não mudam, mas ficam num formato diferente dos novos |
| Risco | Nenhum | "Azul" e "Azul-marinho" viram o mesmo `CAM-AZU` |

## Para dizer sim ou não

- **Sim à A:** mergeie este PR.
- **Quero a B, ou outro texto:** comente no PR.
