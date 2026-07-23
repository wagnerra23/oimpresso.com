---
id: requisitos-recurring-billing-briefing
module: RecurringBilling
status: producao
updated_at: "2026-07-23"
distilled_at: "2026-07-23"
distilled_by: jana:distill-module-truth
---

# BRIEFING — RecurringBilling (verdade destilada)

## Estado atual
O módulo de assinaturas recorrentes, `RecurringBilling`, gerencia planos, assinaturas e faturas com suporte a múltiplos gateways de pagamento. Está em produção (assinaturas + invoices live em biz=1). A maturidade de features (Eixo 1, 14 capacidades) é baixa (7% aprovado / 29% parcial / 64% ausente — CAPTERRA-INVENTARIO-v2).

## Capacidades
- **Planos**: CRUD completo para `rb_plans` com configurações de ciclo e trial.
- **Contratos**: Gerenciamento de `rb_subscriptions` em vários estados.
- **Cobrança**: Integração com gateways de pagamento via `BoletoService::driver()`.
- **Cancelamento**: Inter/Asaas via endpoint `/financeiro/rb-invoices/{id}/cancelar` (UI `Faturas/Index.tsx`); C6 exige cancelamento manual no portal (driver stub, US-RB-042 `_parcial_`).
- **Webhooks**: Idempotência via tabela `pg_webhook_events` `(provider, event_id)`.
- **NFe**: Emissão automática de NFe após pagamento via `NfeBrasil`.
- **Sync bancário**: Funcionalidade de sincronização de saldos bancários e extratos.
- **Interface**: Página Inertia completa para gestão de assinaturas.
- **Nova assinatura**: Criação simplificada com interface intuitiva (PR #2369).

## Gaps
- **Dunning / retry scheduler**: régua de cobrança e reprocessamento automático `_pendente_` (SPEC).
- **PIX Automático e cartão tokenizado**: meios de pagamento recorrente `_pendente_` (SPEC).
- **Proração**: cálculo de proporcional em troca de plano `_pendente_` (SPEC).

## Última mudança
Materialização da US-RB-056 e um "dente de cálculo" test-only (PR #3737), 2026-07-03 — janela desta destilação. (O backfill de gateway US-RB-052 / PR #4045, de 2026-07-09, é posterior a essa janela.)

## Proveniência (destilado de)

- audit `requisitos/RecurringBilling/CAPTERRA-FICHA.md` — CAPTERRA-FICHA.md
- audit `requisitos/RecurringBilling/CAPTERRA-INVENTARIO-v2.md` — CAPTERRA-INVENTARIO-v2.md
- audit `requisitos/RecurringBilling/CAPTERRA-INVENTARIO.md` — CAPTERRA-INVENTARIO.md
- handoff `handoffs/2026-07-03-1215-dente-calculo-recurringbilling.md` (2026-07-03) — 2026-07-03-1215-dente-calculo-recurringbilling.md
- handoff `handoffs/2026-07-03-1245-us-rb-056-materializada.md` (2026-07-03) — 2026-07-03-1245-us-rb-056-materializada.md
