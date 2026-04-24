---
status: researching
priority: alta
problem: "oimpresso não tem cobrança recorrente. Hoje toda venda é avulsa via /sells/create. Faltam contratos/assinaturas, faturas geradas em ciclo, smart retries, NFSe automática, régua de inadimplência, Pix Automático (BCB 2025+)."
persona: "Cliente SaaS/contratos do oimpresso (mensalidades, contratos de manutenção, planos de produtos recorrentes) + Larissa-financeiro que precisa de baixa automática"
estimated_effort: "12-14 semanas dev sênior (pacote completo: 6 módulos)"
references:
  - https://claude.ai/chat/dda41749-c416-4e78-9a3a-e5255d7282c0
  - eduardokum/laravel-boleto (CNAB 240/400)
  - rafwell/laravel-focusnfe (NFSe Nacional)
  - reference_ultimatepos_integracao.md
related_modules:
  - Financeiro (faturas geradas viram títulos a receber)
  - NfeBrasil (NFSe = sub-conjunto/companion)
  - Officeimpresso (revenue: vender como add-on licenciado)
---

# Ideia: CobrancaRecorrente — assinaturas + Pix Automático + Smart Retries

## Problema

Cliente que precisa cobrar mensalidade/contrato hoje precisa criar venda manual a cada mês. UltimatePOS tem `transactions` mas não tem:

- **Ciclo de vida de assinatura** (trialing/active/past_due/unpaid/canceled)
- **Geração automática de faturas** em ciclo (mensal/anual/customizado) com proração
- **Smart retries** com ML (Stripe-style): reconhece soft vs hard decline, retenta em horário ótimo
- **Pix Automático** (BCB 2025+, ~14× mais barato que cartão)
- **Régua de dunning** multicanal (e-mail/SMS/WhatsApp + portal self-service)
- **NFSe automática** integrada à fatura (obrigatório PT/Lei Complementar 214/2025)
- **Reajuste automático** por IPCA/IGP-M em data de aniversário

## Persona

| Persona | Job |
|---|---|
| **Cliente recorrente do tenant** (ex: aluno de academia, assinante de software, contrato de manutenção) | Pagamento automático mensal sem fricção; portal pra atualizar cartão/PIX |
| **Larissa-financeiro** (cliente do oimpresso) | Vê inadimplência por aging; régua roda sozinha; baixa automática chega no Financeiro |
| **Wagner / Officeimpresso** | Vende como add-on premium licenciado a tenants (revenue stream) |

## Status

`researching` — Claude (mobile) gerou arquitetura aprofundada de 6 módulos via 2 rodadas de busca web em 2026-04-24. Não promovido a SPEC ainda.

## Arquitetura proposta — 6 módulos separados (pattern UltimatePOS)

Comunicação **só por eventos Laravel** — nenhum módulo chama método de outro direto.

### 1. `RecurringBilling` (núcleo)
Ciclo de vida de contratos/assinaturas + geração de faturas. Tabelas prefix `rb_`:
- `rb_plans` (ciclo, trial, setup_fee, índice de reajuste)
- `rb_contracts` (anchor_date, next_billing_date, status)
- `rb_invoices` — vinculada a `transactions` do core via `transaction_id` nullable + `idempotency_key` único
- `rb_proration_events` (audit de upgrades/downgrades)

`GenerateRecurringInvoicesJob` via scheduler.

### 2. `PaymentGateway` (abstração)
Adapter pattern com interface única `ChargeInterface`:
- `AsaasGateway`, `IuguGateway`, `PagarmeGateway`, `StripeGateway`, `MercadoPagoGateway`
- Tabelas `pg_credentials`, `pg_payment_methods`, `pg_charge_attempts` (idempotency_key UNIQUE), `pg_webhook_events` (event_id UNIQUE)
- Idempotência **obrigatória**: webhooks Asaas são "at least once"

### 3. `PixAutomatico` (módulo próprio — não merge no PaymentGateway)
Estados específicos do BCB que cartão/boleto não têm:
- `pa_authorizations` (JRC — Jornada de Recorrência de Consentimento, status: created/activated/refused/expired/cancelled, limite_max)
- `pa_payment_instructions` (e2e_id, scheduled_date)
- `pa_authorization_events` (auditoria completa)
- Recomendado: **Jornada 3 (PAYMENTONAPPROVAL)** da Woovi/OpenPix — mais simples, paga + autoriza no mesmo QR

### 4. `NFSe` (emissão fiscal)
Adapter pattern: `FocusNFeProvider`, `PlugNotasProvider`, `NFEioProvider`, `NotaasProvider`. Recomendação: `rafwell/laravel-focusnfe`. **Separar do billing** — emissão é assíncrona, não pode travar cobrança. NFS-e Nacional obrigatória 2026 (Lei Complementar 214/2025).

### 5. `Dunning` (recuperação de inadimplência)
- `dun_rules`, `dun_steps` (action: email/sms/whatsapp/block_access/cancel/retry_charge)
- `dun_campaigns`, `dun_campaign_steps`
- Bom dunning recupera 20-40% dos pagamentos falhos

### 6. `Boleto` (opcional — só se emitir CNAB direto)
Se usar Asaas/Iugu, vira adapter dentro do PaymentGateway. Lib `eduardokum/laravel-boleto` cobre BB/Bradesco/Caixa/Santander/Itaú/Sicoob/Banrisul.

## Comunicação por eventos

```
RecurringBilling → InvoiceGenerated
  ├─ PaymentGateway → tenta cobrança
  ├─ NFSe → emite nota (assíncrono)
  └─ Dunning → fica dormente

PaymentGateway → PaymentSucceeded
  ├─ RecurringBilling → marca paga + cria próximo ciclo + cria transaction no core
  └─ Dunning → encerra campanha

PaymentGateway → PaymentFailed
  ├─ Dunning → inicia/avança régua
  └─ RecurringBilling → status=past_due
```

## Integração crítica com core UltimatePOS

1. **Ao confirmar pagamento**: criar `transactions` (`type=sell`, `payment_status=paid`) + `transaction_sell_lines` + `transaction_payments` com método customizado ("Pix Automático", "Cartão Recorrente"). Linkar `transaction.id` em `rb_invoices.transaction_id`.
2. **Multi-tenant**: toda tabela com `business_id` indexado + global scope.
3. **Permissões Spatie**: registrar no boot do ServiceProvider (`recurring_billing.access`, etc.)
4. **Sidebar**: hook no evento que monta sidebar (padrão módulo Essentials)
5. **Accounting (se instalado)**: mapear sell/sell_payment ao plano de contas
6. **Não reusar tabelas Superadmin** — Superadmin cobra tenants, RecurringBilling cobra clientes finais. Níveis diferentes.

## Decisões pendentes

1. **Merchant-of-record vs gateway direto**: muda completamente o NFSe (oimpresso emite vs cliente emite)
2. **Portal B2C self-service** (cliente final atualiza cartão sozinho, vê 2ª via) — reduz suporte drasticamente
3. **Split de pagamento**: se for marketplace, +1 módulo `PaymentSplit`
4. **Stack open source de referência**: **Lago** (event-driven moderno) ou **Kill Bill** (maduro)

## Atenção Reforma Tributária 2026 (BR)

CBS e IBS retidos na fonte → split-payment muda fluxo de caixa. Crédito fiscal só compensável após 60 dias. RecurringBilling precisa conciliar **bruto cobrado vs líquido recebido vs crédito a compensar**.

## Ordem pragmática de implementação

| # | Módulo | Tempo estimado |
|---|---|---|
| 1 | PaymentGateway + 1 adapter (Asaas) | 2 sem |
| 2 | RecurringBilling (geração + cobrança manual) | 3 sem |
| 3 | NFSe (Focus NFe ou PlugNotas) | 2 sem |
| 4 | Dunning simples (email + bloqueio) | 1 sem |
| 5 | PixAutomatico (depende homologação PSP) | 2 sem |
| 6 | Boleto CNAB direto (opcional) | 3 sem |
| 7 | 2º adapter (Iugu/Pagar.me) | 1 sem |

**Total realista: 12-14 semanas dev sênior.**

## Conexões

- **Financeiro** — fatura recorrente paga vira título recebido. Sincronia via observer.
- **NfeBrasil** — NFSe é módulo separado mas integra com NFe Nacional 2026.
- **Officeimpresso** — possível add-on licenciado por business (revenue stream).
- **PontoWr2** — folha de pagamento futura conectaria (colaborador como contrato recorrente).
- **Design System** — telas de assinaturas/faturas/régua seguem ADR UI-0006.

## Próximos passos

1. **Validar com ROTA LIVRE/clientes**: alguém cobra mensalidade hoje? (provavelmente não — venda avulsa de papelaria)
2. **Validar tese de revenue**: oimpresso vende como add-on Officeimpresso? Quem paga (tenant ou Wagner)?
3. **Decidir merchant-of-record vs gateway direto** antes de começar (afeta NFSe inteiro)
4. **Promover** quando spec leve estiver completa: `_Ideias/CobrancaRecorrente/` → `requisitos/RecurringBilling/`
5. **Estudar Lago e Kill Bill** como blueprint open source
