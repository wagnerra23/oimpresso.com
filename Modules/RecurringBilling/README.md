# Modules/RecurringBilling

> Assinaturas + boletos recorrentes + integração bancária (Inter, C6, Asaas). LIVE biz=1 prod.

## Para o cliente final (Larissa, Eliana[E])

Como a sua empresa usa o RecurringBilling no dia-a-dia:

1. **Cadastra um plano** em `/recurring-billing/planos` (ex: "Mensal Standard R$ [redacted Tier 0] / mês")
2. **Assina cliente nele** em `/recurring-billing/assinaturas/criar` selecionando plano + contato
3. **Sistema gera invoice automática** no `next_due_date` (job `recurring:gerar-invoices` daily 02:00 BRT)
4. **Boleto/PIX emitido** automaticamente via gateway (Inter / C6 / Asaas — cred cadastrada em `/recurring-billing/credenciais-boleto`)
5. **Cliente paga** → webhook do banco → invoice `paid` → MRR atualiza
6. **Cliente quer pausar?** Botão "Pausar" em `/recurring-billing/assinaturas/{id}` → suspende geração próximas
7. **Cliente quer cancelar?** Botão "Cancelar" com motivo (churn_reason) → status `canceled` + Asaas refund/cancel automático (se enabled)
8. **NFe-de-boleto-pago automática** ([US-RB-044](../../memory/requisitos/RecurringBilling/SPEC-US-044-nfe-boleto-pago.md)): quando boleto compensa, sistema emite NFSe correspondente automaticamente (canônico irrevogável — não tocar)

## Lifecycle Subscription canônico

```
[start] → active → paused → active → canceled
                ↓
              overdue (invoice atrasada) → active (pagamento) ou canceled (cobrança falhou)
```

States gerenciados pelo `AssinaturaService` ([ADR 0143 FSM pipeline pattern](../../memory/decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md) inspirado mas não FSM tabular).

## Drivers boleto suportados

| Driver | Status | Ambiente | Cred config | Doc |
|---|---|---|---|---|
| **InterDriver** | ✅ prod | sandbox + production | mTLS (certificado_crt + key) + client_id/secret | `Services/Boleto/Drivers/InterDriver.php` |
| **C6Driver** | ✅ prod | production | agencia + conta + codigo_cliente | `Services/Boleto/Drivers/C6Driver.php` |
| **AsaasDriver** | ✅ prod | sandbox + production | api_key | `Services/Boleto/Drivers/AsaasDriver.php` |

Resolução via `BoletoCredentialResolver` (Wave 23 D2 reuse cross-module Financeiro/NfeBrasil).

## Multi-tenant Tier 0

Toda Subscription/Invoice/Plan tem `business_id` global scope ([ADR 0093](../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)). Jobs `SyncBankBalancesJob`, `ProcessAsaasWebhookJob`, `ProcessInterWebhookJob`, `CancelarCobrancaAsaasJob`, `RefundCobrancaAsaasJob` recebem `businessId` no constructor.

## OTel observability (D9 Wave 17+)

Métodos críticos wrap em `OtelHelper::spanBiz`:

- `rb.assinatura.criar` · `rb.assinatura.pausar` · `rb.assinatura.retomar` · `rb.assinatura.cancelar`
- `rb.subscription.update`
- `rb.boleto.emitir` · `rb.boleto.cancelar` (drivers)

## Health check

```bash
php artisan recurring:health
php artisan recurring:health --business=1 --detail
php artisan recurring:health --json --alert  # exit 0/1/2
```

10 sinais críticos: subscriptions_table, invoices_table, plans_table, credenciais_ativas, mrr_baseline, ciclos_inadimplencia, webhook_idempotency, retention_policy, last_invoice_freshness, boleto_drivers_resolvidos.

## Tests

```bash
vendor/bin/pest Modules/RecurringBilling/Tests
```

Cobertura: AssinaturaService (D2/D4 lifecycle), BoletoService (3 drivers), Webhooks (idempotência), Customer Journey 9-step end-to-end, Repository Wave 18, Otel D9.

## ADRs referência

- [ADR 0143](../../memory/decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md) — FSM pattern (inspiração)
- [ADR 0093](../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) — Multi-tenant Tier 0
- [ADR 0101](../../memory/decisions/0101-tests-business-id-1-nunca-cliente.md) — Tests biz=1
- US-RB-044 — NFe-de-boleto-pago (canônico irrevogável)
- US-RB-055 — Schema aditivo v9.75 (notes/favorites/events + cached cols)

## Anti-patterns proibidos

- ⛔ NÃO tocar US-RB-044 NFe-de-boleto-pago sem ADR mãe nova
- ⛔ NÃO `forceDelete` em Subscription com invoices vinculadas — use cancel
- ⛔ NÃO bypass `BoletoCredentialResolver` direto via `BoletoCredential::find()` — use o resolver pra garantir `decryptConfig` correto
- ⛔ NÃO commit credenciais em config_json sem `Crypt::encryptString` em `client_secret`/`api_key`/`certificado_key_b64`/`certificado_senha`
