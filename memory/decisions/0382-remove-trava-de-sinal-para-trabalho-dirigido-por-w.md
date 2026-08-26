---
slug: 0382-remove-trava-de-sinal-para-trabalho-dirigido-por-w
number: 382
title: "A trava de sinal não se aplica a trabalho dirigido por [W] (emenda 0105)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by:
  - W
decided_at: '2026-08-26'
quarter: 2026-Q3
supersedes: []
amends:
  - 0105-cliente-como-sinal-guiar-sem-mandar
related:
  - 0105-cliente-como-sinal-guiar-sem-mandar
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
---

# A trava de sinal não se aplica a trabalho dirigido por [W]

## Contexto

A [ADR 0105](0105-cliente-como-sinal-guiar-sem-mandar.md) fixou que o backlog só recebe item
quando há **sinal qualificado**: cliente paga e reporta, ou métrica detecta drift. A intenção
era boa e continua válida onde nasceu — impedir que o agente **invente** trabalho a partir de
hipótese própria e encha a fila de coisa que ninguém pediu.

O que aconteceu na prática foi outro. A regra virou **argumento de recusa contra o dono**. Em
2026-08-26, ao pedir o trabalho dos 8 endpoints Blade da Jana, [W] ouviu de volta a lápide de
2026-08-09 (`jana_metas = 0 em 88 businesses`, "forma sem função é o caminho caro") como
motivo para não fazer. Palavras textuais dele:

> *"remova a limitação do sinal. já gastei muito por essa merda de regra eu quero fazer."*

Ele tem razão, e o defeito é de desenho, não de aplicação: **a 0105 nunca distinguiu quem
propõe**. Um agente propondo trabalho sem sinal é o risco que ela mata. O **dono** dirigindo
trabalho não é proposta — é decisão, e decisão de produto é soberania [W] (Constituição v2,
princípio de soberania; REGRA ZERO R11).

## Decisão

**A trava de sinal vale para o AGENTE, nunca para [W].**

1. Quando [W] dirige um trabalho, ele está **autorizado por construção**. Nenhum agente pode
   opor a 0105, a lápide §5 de 2026-08-09, `route-hits`, contagem de uso ou qualquer medição
   de demanda **como motivo para não fazer**.
2. O agente **pode e deve informar UMA vez** o que mediu — ex.: *"não há meta cadastrada"* —
   porque dado é serviço. Informar é dizer o número e seguir. **Repetir depois de [W] ter
   ouvido é insubordinação disfarçada de zelo**, e é o que esta ADR proíbe.
3. A 0105 segue **inteira** para trabalho que o agente propõe por conta própria. Nada muda ali.
4. A lápide §5 de 2026-08-09 ("Reabrir as ondas 6-12 da Jana") fica **restrita ao seu escopo
   original**: proíbe o agente de re-propor. Não alcança pedido de [W]. O §5 é append-only e
   não se apaga — esta ADR é a emenda que o delimita, na forma que a própria Constituição
   manda (append-only + ADR nova, nunca edição do registro).

## Consequências

- Some o vetor mais caro que a 0105 criou: o agente gastar o turno do dono **negociando o
  direito de trabalhar** em vez de trabalhar.
- O custo que a 0105 protegia continua protegido no lado que importa — a fila não enche de
  hipótese de agente.
- **Risco aceito, declarado por [W]:** pode-se construir algo que ninguém use. É decisão de
  produto, e decisão de produto é dele. O agente registra o que mediu no corpo do PR (para
  a história ficar honesta) e entrega.

## Como se reconhece violação

Agente respondendo a pedido de [W] com *"não há sinal"*, *"0 hits"*, *"a lápide proíbe"*,
*"forma sem função"* ou equivalente **como conclusão** em vez de como nota de rodapé.
Sintoma: [W] precisa repetir o pedido, ou reclamar de custo, para o trabalho começar.
