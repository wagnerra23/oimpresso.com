---
title: "Composição: uma tela só para editar a composição do produto (P7 da ADR 0448)"
status: proposto
date: "2026-11-19"
owners: [W]
proposed_by: Claude Code, a pedido de [M]
parent_module: Manufacturing
related_adrs: [448]
related_specs:
  - memory/requisitos/Produto/SPEC.md (US-PROD-025)
---

# Uma tela só para editar a composição

> **Status: `proposto`.** Merge deste PR = "sim: o editor de receita da Fabricação é a tela de composição
> do produto". É a resposta à pergunta **P7** da [ADR 0448](../0448-fabricacao-cadastro-producao-execucao-product-bom-fonte-unica.md).
> Nenhum código muda aqui.

## A pergunta

Hoje existem dois lugares que falam da composição de um produto:

- **Fabricação:** o editor de receita, que já existe e já está em uso.
- **Cadastro do produto:** a [US-PROD-025](../../requisitos/Produto/SPEC.md) pede uma tela de composição
  (o "kit") e a baixa dos componentes quando o kit é vendido no PDV. Essa tela ainda não existe.

A ADR 0448 já decidiu que a composição fica guardada num lugar só. Falta decidir se ela também é
**editada** num lugar só.

## Exemplo: o Banner

O Banner leva 1 Lona, 2 Madeiras e 4 Ponteiras.

| | Uma tela só (recomendado) | Duas telas |
|---|---|---|
| Onde se troca "2 Madeiras" por "3 Madeiras" | No editor de receita. O cadastro do produto abre esse mesmo editor. | No editor da Fabricação **ou** na tela nova do cadastro |
| O que o vendedor vê no PDV ao vender 1 Banner | Baixa de 1 Lona, 3 Madeiras, 4 Ponteiras | Igual, se as duas telas gravarem igual |
| Risco | Nenhum novo | Uma tela mostra uma regra que a outra não tem, e alguém salva por cima |
| Trabalho | Reaproveita o editor que existe (etapa 4) | Fazer e manter uma tela nova com as mesmas regras |

## Recomendação

**Uma tela só.** O editor de receita da Fabricação passa a ser a tela de composição do produto, e a
US-PROD-025 usa esse editor em vez de criar outro. A baixa do kit no PDV continua na US-PROD-025.

## Para dizer sim ou não

- **Sim:** mergeie este PR.
- **Não, quero duas telas:** comente no PR e feche.
