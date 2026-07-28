---
id: requisitos-recurring-billing-adr-arq-0009-sync-saldo-webhook-mais-fallback
---

# ADR ARQ-0009 (RecurringBilling) · Sync de saldo e boletos — webhook + fallback periódico

- **Status**: accepted
- **Data**: 2026-05-06
- **Decisores**: Wagner
- **Categoria**: arq

## Decisões finalizadas (2026-05-06)

### Decisão 1 — Onde fica saldo no Financeiro
**Dashboard Financeiro** — cards de saldo por conta + gráfico total consolidado.
`GET /financeiro` mostra: card por conta ativa com saldo_cached + atualizado_em + variação dia.

### Decisão 2 — Quando o saldo atualiza
**Webhook em tempo real** (Asaas suporta), com **job diário como fallback**:
- Webhook `BALANCE_CHANGED` / `PAYMENT_RECEIVED` → atualiza `fin_contas_bancarias.saldo_cached` imediatamente
- `SyncBankBalancesJob` diário 06:00 → reconcilia todos os bancos com API (fallback + auditoria)
- Botão "Atualizar saldo" manual → chama o job on-demand para 1 conta

### Decisão 3 — Boletos recebidos no Financeiro
**Webhook + sync periódico de fallback**:
- Webhook `PAYMENT_RECEIVED` → cria `account_transaction` (credit) + atualiza `rb_invoices.status=paid`
- `SyncAsaasPaymentsJob` diário 06:00 → reconcilia pagamentos recentes (últimas 48h) que webhook possa ter perdido
- Idempotência por `(provider, external_id)` — sem duplicatas mesmo rodando os dois

## Fluxo completo

```
Cliente paga boleto Asaas
  ↓ webhook PAYMENT_RECEIVED (< 30s)
  WebhookController@asaas
  ├── valida HMAC (rejeita 401 se falha)
  ├── idempotência por (provider='asaas', event_id)
  ├── cria account_transaction (credit, conta Asaas)
  ├── atualiza rb_invoices.status = paid
  ├── atualiza fin_contas_bancarias.saldo_cached += valor
  ├── dispara InvoicePaid (NFe escuta aqui)
  └── responde 200 imediato (processamento async via job)

Diário 06:00 — SyncBankBalancesJob
  ├── Asaas: GET /finance/balance → saldo_cached
  ├── Inter: GET /saldo → saldo_cached (quando Inter implementado)
  └── Registra sync em fin_contas_bancarias.saldo_atualizado_em
```
