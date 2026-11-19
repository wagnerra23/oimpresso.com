---
title: "Composição: copiar as receitas da Fabricação para o produto nas empresas 1 e 8 (ADR 0448, etapa 2)"
status: proposto
date: "2026-11-19"
owners: [W]
proposed_by: Claude Code, a pedido de [M]
parent_module: Manufacturing
related_adrs: [448]
---

# Copiar as receitas da Fabricação para o produto (empresas 1 e 8)

> **Status: `proposto`.** Merge deste PR = "sim, pode gravar a cópia nas empresas 1 e 8".
> Nenhum código muda aqui. A cópia só é gravada depois do merge, rodando o comando com `--aplicar`.

## O que é

A [ADR 0448](../0448-fabricacao-cadastro-producao-execucao-product-bom-fonte-unica.md) (aceita por você
em 13/10) decidiu que a composição do produto mora num lugar só: a tabela `product_bom`.
Hoje as receitas estão na Fabricação. A etapa 2 copia cada receita para lá, sem apagar a original.

## Exemplo: o Banner da empresa 8

| Antes, na receita da Fabricação | Depois, na composição do produto |
|---|---|
| Banner leva 1 Lona | Banner leva 1 Lona |
| Banner leva 2 Madeiras | Banner leva 2 Madeiras |
| Banner leva 4 Ponteiras | Banner leva 4 Ponteiras |

É a mesma receita no lugar novo. Para fazer 1 Banner, o sistema continua tirando 1 Lona, 2 Madeiras
e 4 Ponteiras do estoque.

## O que já foi feito, sem gravar nada

O comando rodou em produção em modo de simulação (`manufacturing:receitas-para-product-bom`, sem
`--aplicar`), nas duas empresas que têm receita:

| Empresa | Receita | Produto | Linhas copiadas |
|---|---|---|---|
| 1 | 5 | Documentação produto | 2 |
| 8 | 2 | Banner | 3 |

- **Conferência:** nas duas, o consumo por unidade da cópia é igual ao que o sistema calcula hoje.
- **Ficam de fora:** as receitas 6 e 7 (8 linhas). Os produtos delas não existem mais, e o sistema
  não usa essas receitas hoje.
- **`product_bom`:** 0 linhas antes e depois da simulação.

## O que muda para quem usa o sistema

Nada, por enquanto. Nenhuma tela lê a composição nova ainda: isso é a etapa 3, que tem a sua própria
prova de antes e depois. A cópia é gravada numa transação só, e rodar de novo não duplica.

## Antes de mergear

A receita 5 da empresa 1 tem o mesmo insumo duas vezes. Isso está no [PR #11262](https://github.com/wagnerra23/oimpresso.com/pull/11262). Se for erro, corrigimos a receita primeiro e só depois gravamos a cópia.

## Para dizer sim ou não

- **Sim:** mergeie este PR. Depois a gente roda com `--aplicar` nas empresas 1 e 8 e mostra a contagem gravada.
- **Não ou mudança:** comente no PR.
