---
id: reference-dominio-venda
name: Domínio — Venda
description: Como uma venda atravessa o oimpresso, do balcão ao estoque, ao título e à nota — apontando pros donos do vocabulário e da máquina de estados, sem redeclarar nenhum dos dois.
type: reference
authority: canonical
lifecycle: ativo
updated_at: "2026-08-03"
nav_group: dominio
nav_order: 10
lente: [operar, construir]
---

# Domínio — Venda

> **Este documento não declara vocabulário e não declara estados.** Os valores canônicos
> (o que é `type`, `status`, `payment_status`, `source`; o que é devolução e o que é estorno)
> vivem no **dicionário de vendas**, que é fonte-única cobrada por gate no CI. Os estágios e as
> transições vivem na **máquina de estados**. Aqui fica só a **travessia**: o que acontece, em
> que ordem, e quem escreve o quê.
>
> Divergiu? **O dicionário e o código ganham** — este documento é que está errado, e a correção
> é aqui.

## Antes de ler: quem é dono de quê

Pergunte primeiro, leia depois. Cada linha aponta pro dono; nenhuma repete o conteúdo dele.

| Se a pergunta é… | O dono é | Onde |
|---|---|---|
| *"que valores `transactions.type`/`status`/`payment_status` aceitam?"* | dicionário de domínio | [`memory/dominio/vendas.md`](../dominio/vendas.md) |
| *"por que a venda não sai deste estágio?"* | máquina de estados (FSM) | [ADR 0143](../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md) + [`app/Domain/Fsm/`](../../app/Domain/Fsm) |
| *"quando o estoque baixa de verdade?"* | dicionário de estoque + os side-effects da FSM | [`memory/dominio/estoque.md`](../dominio/estoque.md) |
| *"onde nasce o que o cliente deve?"* | dicionário do financeiro | [`memory/dominio/financeiro.md`](../dominio/financeiro.md) |
| *"a nota é obrigatória? quando?"* | dicionário fiscal | [`memory/dominio/fiscal-faturamento.md`](../dominio/fiscal-faturamento.md) |
| *"por que o gate reprovou meu enum novo?"* | o guard que compara dicionário ⇔ migration | [`scripts/domain-dict-guard.mjs`](../../scripts/domain-dict-guard.mjs) |

> **Por que a tabela não traz os valores.** Um segundo lugar com a mesma lista drifa do
> primeiro no dia em que alguém acrescenta um valor — e os dois ficam coerentes entre si e
> errados quanto ao mundo. Aqui a regra é: **o segundo documento aponta pro primeiro.**

## A travessia

Uma venda não é uma linha numa tabela — é um objeto que **atravessa quatro domínios**, e cada
um deles tem dono próprio:

```
   venda  ──▶  estoque  ──▶  financeiro  ──▶  fiscal
 (o que foi   (o que saiu   (o que o      (o que foi
  combinado)   da prateleira) cliente deve)  declarado)
```

O ponto que confunde quem chega: **esses quatro não acontecem juntos, nem necessariamente
todos.** Uma venda pode existir sem ter baixado estoque (ainda reservado), sem ter gerado
título (pagamento à vista) e sem nota (operação que não exige). É por isso que "a venda está
pronta?" não tem resposta única — depende de qual dos quatro você está perguntando.

**Quem decide a passagem de um para o outro é a máquina de estados**, não a tela e não o
serviço que salva. Toda mudança de estágio passa por um gateway único, e o `UPDATE` direto no
estágio é bloqueado por uma trava no próprio model — de propósito, para que não exista um
segundo caminho que a auditoria não veja. Os efeitos colaterais (reservar, consumir, liberar,
cancelar em cascata) são peças isoladas que a máquina aciona; não moram no controller.

Quem for mexer nisso: o dono é a [ADR 0143](../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md),
e o código é [`app/Domain/Fsm/`](../../app/Domain/Fsm). A UI que expõe as ações ao operador é o
painel do drawer de venda — [`FsmActionPanel.tsx`](../../resources/js/Pages/Sells/_components/FsmActionPanel.tsx) —
e ele **desenha o que a máquina permite**, em vez de decidir por conta própria.

## Quem escreve o quê

Regra que evita a maior parte dos bugs desta área: **a UI nunca inventa estado.**

- o **operador** informa intenção (adicionar item, aplicar desconto, escolher pagamento);
- o **backend deriva** o que decorre disso — o quanto falta pagar é consequência do pagamento
  registrado, nunca um campo que a tela escolhe;
- a **máquina de estados** decide se a transição é permitida, por papel e por estágio;
- o **histórico** é append-only: registro de transição não se edita nem se apaga.

Se você está prestes a escrever um estado calculado na tela, provavelmente a conta pertence ao
backend. Vale o inverso também: leitura que a tela faz por conta própria costuma ser o começo
de um número que discorda do relatório.

## Cuidado especial — valor e estoque

Alteração que possa mexer em **valor** (preço, total, desconto, imposto, frete, pagamento) ou
em **estoque** (quantidade, reserva, baixa) tem regra própria e mais dura no projeto: exige
conferir o cálculo por dois caminhos independentes e **apresentar o impacto antes de aplicar**.
Não é burocracia — nasceu de um incidente real em que um separador decimal mal interpretado
inflou vendas em produção.

O dono dessa regra é [`memory/proibicoes.md`](../proibicoes.md) (§ *Cálculo de valor ou
estoque*). Leia antes, não depois.

## O que este documento não cobre

Compra, devolução de compra e transferência também vivem na mesma espinha de `transactions`,
mas têm dicionário próprio — [`compras.md`](../dominio/compras.md) e
[`estoque.md`](../dominio/estoque.md). Ordem de serviço da oficina deriva venda, e não o
contrário; quando precisar desse recorte, o dono é o módulo, não este texto.
