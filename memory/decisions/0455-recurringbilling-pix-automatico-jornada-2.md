---
slug: 0455-recurringbilling-pix-automatico-jornada-2
number: 455
title: "RecurringBilling: o Pix Automático usa a Jornada 2 (QR da recorrência), não a Jornada 3"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-11-23"
module: recurringbilling
quarter: 2026-Q4
tags: [recurringbilling, pix-automatico, bcb, paymentgateway]
supersedes: []
supersedes_docs: # ADR de módulo (fora do padrão NNNN-slug do schema), mesmo tratamento da 0172
  - memory/requisitos/RecurringBilling/adr/arq/0003-pix-automatico-jornada-3-paymentonapproval.md
supersedes_partially: []
superseded_by: []
related:
  - 0456-paymentgateway-dono-dos-drivers-de-cobranca
  - 0093-multi-tenant-isolation-tier-0
pii: false
review_triggers:
  - "o BCB mudar a API Pix (hoje v2.10.0) de forma que a Jornada 3 passe a ser a única aceita pelos PSPs"
  - "um cliente pedir que o primeiro pagamento saia no mesmo QR da autorização"
---

# ADR 0455 — RecurringBilling: o Pix Automático usa a Jornada 2

## Contexto

A ARQ-0003 do módulo ([`adr/arq/0003-pix-automatico-jornada-3-paymentonapproval.md`](../requisitos/RecurringBilling/adr/arq/0003-pix-automatico-jornada-3-paymentonapproval.md), 2026-04-24, `accepted`) escolheu a **Jornada 3** (PAYMENTONAPPROVAL: o cliente autoriza e paga a primeira cobrança no mesmo QR) como padrão, com a Jornada 2 como alternativa.

O código que entrou no `main` em 2026-10-26 ([#9870](https://github.com/wagnerra23/oimpresso.com/pull/9870), US-RB-020/021) faz o contrário: usa a **Jornada 2**, em que o QR serve só para aprovar a recorrência e as cobranças vêm depois, uma por fatura.

- [`Modules/RecurringBilling/Services/MandatoPixAutomatico.php:16`](../../Modules/RecurringBilling/Services/MandatoPixAutomatico.php) — o docblock descreve a jornada: `POST /locrec` → `POST /rec` com o `loc` → `GET /rec/{idRec}` devolve o `pixCopiaECola` que o cliente aprova no app do banco. A classe registra a decisão como "[W] 2026-10-26".
- [`Modules/PaymentGateway/Services/Drivers/BcbPixDriver.php:128`](../../Modules/PaymentGateway/Services/Drivers/BcbPixDriver.php) — a recorrência leva o `loc` criado em `/locrec` (Jornada 2). O `POST /v2/locrec` está em `:274`.

Nenhuma ADR substituía a ARQ-0003. A auditoria do módulo de 2026-11-19 (eixo 3, §5) apontou a contradição como ALTA: a ADR aceita descreve um fluxo que o sistema não executa.

Reproduzir as duas citações:

```bash
git grep -n "Jornada" -- Modules/RecurringBilling/Services/MandatoPixAutomatico.php Modules/PaymentGateway/Services/Drivers/BcbPixDriver.php
```

## Decisão

O Pix Automático do RecurringBilling usa a **Jornada 2** da API Pix do BCB:

1. Ativar o Pix Automático numa assinatura cria a location (`/locrec`) e a recorrência (`/rec`) com o valor que o gerador fatura e a periodicidade do ciclo.
2. O cliente lê o QR da recorrência e aprova no app do banco. Até a aprovação, nenhuma fatura é cobrada por Pix Automático.
3. O webhook da recorrência muda o mandato para `APROVADA`; a partir daí o `CobradorPixAutomatico` (comando `rb:cobrar-pix-automatico`) cobra cada fatura.

O mandato mora em `rb_subscriptions.metadata.pix_automatico`. O driver e a credencial do BCB pertencem ao `Modules/PaymentGateway` ([ADR 0456](0456-paymentgateway-dono-dos-drivers-de-cobranca.md)).

O comportamento continua atrás da flag `RB_COBRAR_PIX_AUTOMATICO` (padrão `false`). Ligar em produção é decisão do [W].

## Justificativa

- A Jornada 2 separa autorizar de pagar. O primeiro pagamento segue a mesma régua das faturas seguintes, então não existe caminho especial para a primeira cobrança.
- O valor da recorrência é o mesmo que o gerador fatura (plano, ou `metadata.valor` quando a assinatura não tem plano), em centavos inteiros. Na Jornada 3 a primeira cobrança sairia pelo QR, fora do gerador.
- É a jornada que está implementada e testada. A ARQ-0003 nunca chegou ao código.

Reabrir se um cliente pedir explicitamente "autoriza e paga no mesmo QR", ou se os PSPs deixarem de aceitar a Jornada 2.

## Consequências

**Positivas:** a ADR do módulo deixa de contradizer o código; o SDD e os casos UC-RBPA-* passam a ter uma decisão para citar.

**Negativas / trade-offs:** a primeira cobrança depende de dois passos do cliente (aprovar o QR e, depois, a fatura ser cobrada no vencimento). A Jornada 3 está fora até decisão nova.

**Append-only:** a ARQ-0003 não foi editada. Ela fica como registro do desenho de abril; esta ADR é a decisão vigente.
