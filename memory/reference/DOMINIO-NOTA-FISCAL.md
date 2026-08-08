---
id: reference-dominio-nota-fiscal
name: Domínio — Nota fiscal
description: O documento fiscal como estado, não como PDF — o que cada modelo cobre, por que falha faz parte do ciclo e por que cancelar nunca é apagar.
type: reference
authority: canonical
lifecycle: ativo
updated_at: "2026-08-03"
nav_group: dominio
nav_order: 30
lente: [operar, construir]
related: [reference-dominio-venda, reference-fluxo-venda]
---

# Domínio — Nota fiscal

> Modelos, estados e regime tributário têm dono e são cobrados por gate:
> [`memory/dominio/fiscal-faturamento.md`](../dominio/fiscal-faturamento.md).
> Aqui fica o que o dicionário não diz — **como pensar** o documento fiscal.

## A nota não é um arquivo, é um estado

O erro mental mais caro nesta área é tratar nota como "o PDF que sai". A nota é um **registro
com ciclo de vida** que conversa com a SEFAZ: ela é enviada, e a resposta pode ser aceitação
ou recusa. O PDF é subproduto.

Consequência prática: **falha faz parte do ciclo normal.** Rejeitada, denegada e erro de envio
não são bugs do sistema — são respostas do fisco, e **cada uma pede uma ação diferente**. Ler
todas como "deu erro" leva a retentar o que não deve ser retentado.

## Cancelar nunca é apagar

Nota autorizada só sai do ar por **evento fiscal**, e o número **não volta a ficar livre**.
Não existe `DELETE` aqui — o registro permanece, com o estado que conta a história.

Quem trata cancelamento como "apagar a linha" cria uma divergência com o fisco que o sistema
não conserta depois. É a regra mais irreversível deste domínio, e está no dicionário junto com
a base legal.

## Serviço e mercadoria são documentos diferentes

Peça e mão de obra não cabem no mesmo documento. Quando a operação tem os dois — o caso comum
da oficina — ela **se divide**, e cada parte vai para o documento que lhe corresponde. O
dicionário descreve o split; a [Ordem de Serviço](DOMINIO-OS.md) mostra onde ele aparece.

## O regime tributário muda a conta

Não é um campo decorativo: o regime da empresa determina como o imposto é calculado e o que a
tela de impostos mostra. Empresa cadastrada no regime errado produz número errado com aparência
de certo — o pior tipo de defeito, porque ninguém desconfia.

## Um conflito conhecido, registrado de propósito

Existe uma tabela de emissão de serviço criada por **dois módulos diferentes, com vocabulários
diferentes**. O dicionário declara qual vocabulário está vivo hoje e trava o guard contra a
volta do antigo; consolidar os módulos é decisão pendente.

Se você encontrar dois conjuntos de estados para a mesma coisa e achar que é bug: **está
catalogado**, leia o dicionário antes de "corrigir".
