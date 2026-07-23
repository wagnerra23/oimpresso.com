---
id: requisitos-recurring-billing-adr-arq-0008-asaas-como-conta-bancaria-virtual
---

# ADR ARQ-0008 (RecurringBilling) · Asaas como conta bancária virtual — supersede ARQ-0007 parcialmente

- **Status**: accepted
- **Data**: 2026-05-06
- **Decisores**: Wagner
- **Supersede parcialmente**: arq/0007 (corrige classificação do Asaas)
- **Categoria**: arq

## Contexto

ADR ARQ-0007 classificou o Asaas como "gateway puro" sem conta bancária. Wagner corrigiu: o Asaas é uma **conta bancária virtual PJ** — tem saldo real, extrato, recebimentos, transferências e PIX. Deve aparecer no Financeiro como qualquer outra conta.

## Decisão

**Asaas = conta bancária virtual** com entrada em `fin_contas_bancarias` igual a Inter e C6.

```
fin_contas_bancarias (Asaas)
  banco_codigo = '077' (Inter) ou código Asaas ('274')
  tipo = 'virtual_pj'
  ativo_para_boleto = true
  rb_gateway_credential_id → rb_boleto_credentials (api_key)
```

**O que o Financeiro mostra para a conta Asaas:**

| Feature | Fonte dos dados | Como obtém |
|---|---|---|
| Saldo atual | API Asaas `GET /finance/balance` | Job diário + refresh manual |
| Boletos recebidos | API Asaas `GET /payments?status=RECEIVED` | Webhook `PAYMENT_RECEIVED` |
| Transferências/saques | API Asaas `GET /transfers` | Sync periódico |
| Extrato | API Asaas `GET /financialTransactions` | Sync periódico |

**Webhook Asaas → `account_transactions` core:**
Ao receber `PAYMENT_RECEIVED`: cria `account_transaction` (credit) vinculado à conta Asaas + atualiza `rb_invoices.status=paid`.

**Saldo em tela:**
`fin_contas_bancarias` ganha campo `saldo_cached` + `saldo_atualizado_em` — atualizado pelo job `SyncAsaasBalanceJob` (diário + on-demand via botão "Atualizar saldo").

## Regra unificada pós-correção

| Banco | Tipo | fin_contas_bancarias | rb_boleto_credentials |
|---|---|---|---|
| Inter | Real API | ✅ obrigatório | ✅ com FK |
| C6 | Real CNAB | ✅ obrigatório | ✅ com FK |
| Asaas | Virtual PJ | ✅ obrigatório | ✅ com FK |

**Todos os bancos seguem o mesmo fluxo:** conta bancária first, credencial de cobrança é extensão.

## Consequências

- Tela Financeiro > Contas mostra Asaas com saldo como qualquer outra conta
- Boletos Asaas recebidos aparecem no extrato do Financeiro (via webhook → account_transaction)
- Precisa de job `SyncAsaasBalanceJob` + campo `saldo_cached` em `fin_contas_bancarias`
- Webhook Asaas precisa ser registrado ao salvar credencial (`AsaasDriver::createWebhook()` — já implementado)
