---
id: reference-dominio-titulo
name: Domínio — Título
description: O que o cliente deve e o que a empresa deve — como nasce, como se quita e por que não se digita à mão. Aponta pro dicionário do financeiro, sem redeclarar vocabulário.
type: reference
authority: canonical
lifecycle: ativo
updated_at: "2026-08-03"
nav_group: dominio
nav_order: 20
lente: [operar, construir]
related: [reference-dominio-venda, reference-fluxo-venda]
---

# Domínio — Título

> Vocabulário e valores canônicos vivem no **dicionário do financeiro**, cobrado por gate.
> Aqui fica só o que o dicionário não explica: **de onde o título vem e por que não se
> digita**. Divergiu? O dicionário ganha.
>
> Dono: [`memory/dominio/financeiro.md`](../dominio/financeiro.md).

## A ideia em uma frase

**Toda obrigação tem título** — a receber ou a pagar. O título é o registro de que alguém
deve; ele não é o dinheiro, é a promessa.

## O que confunde quem chega

**O título da venda nasce sozinho.** Venda a prazo gera o título automaticamente, por
observer — não por digitação. Quem vem de planilha tende a cadastrar "contas a receber" à mão
depois de vender, e é assim que o cliente passa a aparecer devendo **duas vezes**: uma pela
venda, outra pela digitação.

Se você está prestes a criar um título para uma venda que já existe, pare: ele já está lá.

## Os três momentos

| momento | o que é | onde |
|---|---|---|
| **título** | a obrigação nasce | gerado pela venda, ou lançado quando não vem de venda |
| **baixa** | o "recebi" / "paguei" | total quita; parcial deixa o resto em aberto |
| **caixa** | o movimento de dinheiro | **derivado da baixa** — não se edita direto |

A ordem importa: **o caixa é consequência da baixa**, não uma tela paralela. "Recebi mas o
caixa não mostra" é quase sempre recebimento que não virou baixa.

## Duas palavras que não são sinônimas

- **estorno** é de pagamento/baixa — vive no financeiro;
- **devolução** é de mercadoria — vive em vendas.

Trocar as duas troca o domínio inteiro do problema. O dicionário lista os demais termos
proibidos (inclusive por que "duplicata" não existe aqui).

## Conciliação

Casar o que o banco diz com o que o sistema diz é um fluxo próprio, com estados próprios —
inclusive o de **ignorado**, que existe para o que nunca vai casar. O dono descreve os
estados; não os repita em código nem em tela sem olhar lá.

## Antes de mexer

Título é **valor**. Alteração que toque preço, total, desconto, imposto, pagamento ou baixa
exige conferir por dois caminhos independentes e **apresentar o impacto antes de aplicar** —
regra nascida de incidente real em produção, não de zelo teórico:
[`memory/proibicoes.md`](../proibicoes.md) (§ *Cálculo de valor ou estoque*).
