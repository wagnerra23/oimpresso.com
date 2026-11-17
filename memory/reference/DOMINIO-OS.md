---
id: reference-dominio-os
name: Domínio — Ordem de Serviço
description: A OS da oficina como reparo — peça e mão de obra, o split que ela força no fiscal e no estoque, e por que ela deriva venda e nunca o contrário. Aponta também para a outra OS, a que nasce do pedido na Produção.
type: reference
authority: canonical
lifecycle: ativo
updated_at: "2026-11-05"
nav_group: dominio
nav_order: 50
lente: [operar, construir]
related: [reference-dominio-venda, reference-dominio-nota-fiscal, reference-dominio-estagio]
---

# Domínio — Ordem de Serviço

> Vocabulário e estados canônicos: [`memory/dominio/oficina-auto.md`](../dominio/oficina-auto.md),
> cobrado por gate. Aqui fica a leitura — o que a OS é e o que ela força nos outros domínios.
>
> **Esta página trata da OS da oficina (reparo).** A OS por item que nasce de uma venda nos
> modelos de fluxo é outra entidade, de outro módulo: veja
> [A outra OS](#a-outra-os-a-que-nasce-do-pedido) no fim.

## O domínio é reparo. Ponto.

A oficina é **reparo/mecânica**, e essa frase tem história: o dicionário e a
[ADR 0265](../decisions/0265-oficina-reparo-erradica-locacao.md) existem para fechar a porta a
um domínio que **nunca foi do negócio** e que o legado sugeria. O nome comercial do cliente não
é conceito de domínio — é razão social.

Se você encontrar no código termos que sugerem outro fluxo de negócio, **não são
funcionalidade a preservar**: são resíduo catalogado, com erradicação registrada. Não os
reintroduza em tipo de ordem, coluna de kanban, KPI ou label.

## A OS tem dois tipos de item, e é isso que a torna especial

| item | toca | vira |
|---|---|---|
| **peça** | estoque (é produto de verdade) | um documento fiscal |
| **mão de obra / serviço** | não toca estoque | **outro** documento fiscal |

Esse **split** é a característica que distingue a oficina de uma venda de balcão. Não é detalhe
de emissão: muda o que sai do estoque, o que entra no faturamento e quantos documentos a
operação gera.

O dono do split é o [dicionário fiscal](../dominio/fiscal-faturamento.md); a
[Nota fiscal](DOMINIO-NOTA-FISCAL.md) explica por que serviço e mercadoria não cabem no mesmo
documento.

## Na oficina, a direção é sempre a mesma

**A oficina deriva venda; a venda nunca deriva oficina.** Quando o serviço se converte em
receita, é a OS que origina o registro de venda — e a venda carrega essa origem.

Inverter isso na cabeça leva a procurar a OS a partir da venda como se fosse o caminho natural.
É o contrário.

## Estados: dois vocabulários, de propósito

A OS tem estados **de negócio** e uma apresentação **de kanban** que não são a mesma lista — a
tela agrupa o que o negócio separa. O dicionário declara os dois e diz qual é qual.

É a fonte de confusão clássica: alguém lê o rótulo da coluna do kanban e assume que aquilo é o
estado gravado. Não é. Quem manda é a
[máquina de estados](DOMINIO-ESTAGIO.md), e o dicionário mapeia.

## Contexto de produção

Este módulo tem cliente real rodando. Mudança aqui não é exercício: alteração que toque estoque
ou valor segue a regra dura de [`memory/proibicoes.md`](../proibicoes.md) — dois caminhos
independentes de conferência e **impacto apresentado antes de aplicar**.

## A outra OS: a que nasce do pedido

Nos modelos de fluxo ([ADR 0435](../decisions/0435-fluxos-por-modelo-porte-atividade.md)), a
direção é a oposta: **a venda gera a OS**. Cada item da venda que precisa ser produzido vira uma
OS por item no módulo Produção (`pcp_ordens`), com processo próprio no motor de estados e o
roteiro de etapas vindo do produto (sem roteiro no produto, valem as etapas de produção do modelo)
([ADR 0436](../decisions/0436-os-por-item-producao-dona-da-os-do-pedido.md), proposta). As OS da
oficina e do Reparo continuam onde estão.

Dono do vocabulário dessa OS: [`memory/dominio/producao.md`](../dominio/producao.md).
