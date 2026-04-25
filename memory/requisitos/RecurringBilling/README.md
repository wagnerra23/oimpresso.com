---
module: RecurringBilling
alias: recurring-billing
status: ativo
migration_target: react
migration_priority: alta
risk: alto
problem: "UltimatePOS não tem cobrança recorrente. Cliente que cobra mensalidade/contrato cria venda manual a cada mês. Falta ciclo de vida assinatura, smart retries, NFSe automática, régua dunning, Pix Automático (BCB 2025+)."
persona: "Cliente recorrente do tenant (mensalidade/contrato) + Larissa-financeiro + Wagner/Officeimpresso (revenue add-on)"
positioning: "Cobre todo mês sozinho, no Pix Automático mais barato. Mensalidade que entra em 92%+ dos meses, mesmo quando o cartão falha — com retentativa inteligente e régua que recupera 30% da inadimplência."
estimated_effort: "12-14 semanas dev sênior (6 sub-módulos em 7 ondas)"
revenue_tier: 2
revenue_pricing:
  free: "Sem cobrança recorrente — só vendas avulsas (módulo não disponível no plano POS básico)"
  starter: "R$ 149/mês — até 50 contratos ativos, 1 gateway, dunning email-only"
  pro: "R$ 449/mês — 500 contratos, multi-gateway, Pix Automático, dunning multicanal, NFSe automática"
  enterprise: "R$ 999/mês — ilimitado, split-payment, portal cliente self-service white-label, API"
revenue_take_rate: "0,8% sobre GMV cobrado via gateway próprio (capped R$ 19,90 por título), 0% se merchant-of-record do cliente"
references:
  - https://claude.ai/chat/dda41749-c416-4e78-9a3a-e5255d7282c0
  - eduardokum/laravel-boleto
  - rafwell/laravel-focusnfe
  - reference_ultimatepos_integracao.md
  - Lago (open-source benchmark)
  - Kill Bill (open-source benchmark)
related_modules:
  - Financeiro
  - NfeBrasil
  - Officeimpresso
last_generated: 2026-04-24
last_updated: 2026-04-24
---

# RecurringBilling

> **Pitch para o tenant:** _Cobre todo mês sozinho, no Pix Automático mais barato._ Mensalidade que entra em 92%+ dos meses, mesmo quando o cartão falha — com retentativa inteligente e régua que recupera 30% da inadimplência.

## Propósito

Tornar oimpresso plataforma de **billing recorrente moderna brasileira**, com:

- **Ciclo de vida assinatura** — `trialing → active → past_due → unpaid → canceled`
- **Faturas geradas em ciclo** (mensal/anual/customizado) com proração mid-cycle
- **Smart retries ML** — distinguir soft vs hard decline, retentar em horário ótimo
- **Pix Automático** (BCB 2025+) — 14× mais barato que cartão, autorização recorrente nativa
- **Dunning multicanal** — email + SMS + WhatsApp + portal self-service
- **NFSe automática** — emissão integrada à fatura paga (compliance Lei Complementar 214/2025)
- **Reajuste IPCA/IGP-M** automático no aniversário
- **Portal B2C self-service** — cliente final atualiza cartão / vê 2ª via / vê histórico

Padrão de mercado BR: Lago (event-driven moderno, MIT license) e Kill Bill (Java, maduro). Inspiração arquitetural sem copiar — adaptado pra UltimatePOS multi-tenant + Spatie + Pix Automático.

## Posicionamento de mercado (revenue thesis)

RecurringBilling tem **maior take rate** dos 4 módulos: tenant cobra GMV alto (mensalidade × N clientes × meses), oimpresso fica com 0,8% capped. Modelo Stripe-like: SaaS clientes Stripe não pensam em pagar 2,9% + R$ 0,30 porque a alternativa (montar gateway próprio) é absurda.

| Plano | Preço/mês | Contratos | Take rate (gateway próprio) |
|---|---|---|---:|
| **Starter** | R$ 149 | até 50 ativos | 0,8% capped R$ 19,90 |
| **Pro** | R$ 449 | até 500 ativos + Pix Automático + multi-gateway | 0,8% capped R$ 19,90 |
| **Enterprise** | R$ 999 | ilimitado + split-payment + portal white-label | 0% (já paga premium fixo) |

**Margem alta** (subscription cobre infra; take rate é margem quase pura).

**Lock-in extremo**: contratos ativos × histórico de retentativa × cartões salvos. Cliente que tem 200 contratos ativos via oimpresso não migra — risco de quebrar pagamentos é alto demais.

**Cenário típico**: tenant com 100 contratos × R$ 200/mês = GMV R$ 20.000/mês. Take rate 0,8% capped = ~R$ 160/mês oimpresso (Pro R$ 449 + R$ 160 = R$ 609 efetivo por tenant médio).

## Índice

- **[SPEC.md](SPEC.md)** — user stories US-RB-NNN + regras Gherkin R-RB-NNN
- **[ARCHITECTURE.md](ARCHITECTURE.md)** — 6 sub-módulos event-driven, integração com Financeiro/NfeBrasil
- **[GLOSSARY.md](GLOSSARY.md)** — vocabulário (assinatura, dunning, MoR, GMV, JRC, etc.)
- **[CHANGELOG.md](CHANGELOG.md)** — versão a versão
- **[adr/](adr/)** — decisões numeradas (`arq/`, `tech/`, `ui/`)
- **[RUNBOOK.md](RUNBOOK.md)** — _stub_ — operações: rotacionar credencial gateway, recuperar webhook, etc.

## 6 sub-módulos (event-driven)

```
┌─────────────────┐     ┌──────────────────┐     ┌──────────────┐
│ RecurringBilling│     │ PaymentGateway   │     │ PixAutomatico│
│ (núcleo)        │ ──▶ │ (adapter)        │ ──▶ │ (BCB 2025+)  │
│ rb_*            │     │ pg_*             │     │ pa_*         │
└─────────────────┘     └──────────────────┘     └──────────────┘
        │                        │
        ▼                        ▼
┌─────────────────┐     ┌──────────────────┐
│ NFSe            │     │ Dunning          │
│ (emissão fiscal)│     │ (recuperação)    │
│ nfse_*          │     │ dun_*            │
└─────────────────┘     └──────────────────┘
                                  ▲
                                  │
                          ┌──────────────────┐
                          │ Boleto opcional  │
                          │ (CNAB direto)    │
                          │ bol_*            │
                          └──────────────────┘
```

Comunicação **só por evento Laravel** — nenhum sub-módulo chama método de outro direto.

## Áreas funcionais (por sub-módulo)

| Sub-módulo | Áreas | Responsabilidade |
|---|---|---|
| **RecurringBilling** | Plans, Contracts, Invoices, Proration | Núcleo: ciclo de vida, geração de fatura, proração |
| **PaymentGateway** | Adapters, Cards, ChargeAttempts, Webhooks, SmartRetries | Cobrança: 1 interface, N gateways (Asaas, Iugu, Pagar.me, Stripe, MercadoPago) |
| **PixAutomatico** | Authorizations, PaymentInstructions, AuthorizationEvents | Estados BCB que cartão/boleto não têm (JRC, e2e_id) |
| **NFSe** | Providers, Issuance | NFSe automática (Focus NFe, PlugNotas, NFE.io); separado pra não travar billing |
| **Dunning** | Rules, Steps, Campaigns, Channels | Recuperação multicanal: email/SMS/WhatsApp/block/cancel/retry |
| **Boleto** | (compartilhado com Financeiro) | Opcional CNAB direto; gateway via PaymentGateway adapter |

## Quem ganha o que

| Persona | Job | Tela atende |
|---|---|---|
| **Cliente recorrente** (assinante final) | "Atualizar cartão antes do próximo ciclo" | `/portal/billing/cards` (portal self-service) |
| | "Ver 2ª via da fatura de março" | `/portal/billing/invoices/{id}` |
| | "Cancelar assinatura" | `/portal/billing/subscription/cancel` |
| **Larissa-financeiro** (tenant) | "Quem está em past_due hoje? Vou ligar" | `/recurring-billing/contracts?status=past_due` |
| | "Por que essa cobrança falhou?" | `/recurring-billing/contracts/{id}/charge-attempts` |
| | "Ativar Pix Automático pro cliente X" | `/recurring-billing/contracts/{id}/payment-methods` |
| **Gestor (tenant)** | "MRR/ARR do mês? Churn?" | `/recurring-billing/dashboard` (KPIs) |
| **Wagner / Officeimpresso** | Vender add-on premium licenciado | (Superadmin licença) |

## Status atual (2026-04-24)

- ✅ **Spec promovida** de `_Ideias/CobrancaRecorrente/` para `requisitos/RecurringBilling/` (`spec-ready`)
- ⏳ **Onda 1:** PaymentGateway + 1 adapter Asaas (2 sem)
- ⏳ **Onda 2:** RecurringBilling núcleo (geração fatura + cobrança manual) (3 sem)
- ⏳ **Onda 3:** NFSe via FocusNFe ou PlugNotas (2 sem)
- ⏳ **Onda 4:** Dunning simples (email + bloqueio) (1 sem)
- ⏳ **Onda 5:** Pix Automático (depende homologação PSP) (2 sem)
- ⏳ **Onda 6:** Boleto CNAB direto (opcional) (3 sem)
- ⏳ **Onda 7:** 2º adapter (Iugu/Pagar.me) (1 sem)

## Onde se conecta

- **Core UltimatePOS** — fatura paga vira `transaction` (sell) + `transaction_payment` no core. NÃO duplica fonte de verdade.
- **Financeiro** — fatura recorrente paga vira título recebido baixado. Evento `RecurringInvoicePaid` consumido por `Modules\Financeiro\Listeners\BaixarTituloRecurring`.
- **NfeBrasil** — NFSe é módulo **filho** dentro de RecurringBilling (estende padrão de NfeBrasil). Decisão final: ver ADR ARQ-0002.
- **Officeimpresso** — Superadmin cobra tenants pelo plano. RecurringBilling é cobrança **dos clientes finais do tenant**. Níveis diferentes — não reusar tabelas.
- **PontoWr2** — folha colaborador futura conectaria como contrato recorrente `tipo=folha`.

## Próximos passos imediatos

1. **Validar com cliente piloto**: alguém quer cobrar mensalidade hoje? (ROTA LIVRE provavelmente não — vendas avulsas)
2. **Decidir: merchant-of-record vs gateway direto** — afeta NFSe e take rate (ADR ARQ-0003)
3. **Decidir gateway MVP**: Asaas (multi-banco, preço bom, Pix nativo)
4. **Scaffold módulo** + sub-módulos em sequência
5. **Onda 1**: PaymentGateway interface + AsaasAdapter + idempotência webhook + 1 teste happy path
