---
id: requisitos-recurring-billing-capterra-inventario
---

# CAPTERRA-INVENTÁRIO — RecurringBilling

> Gerado por skill `comparativo-do-modulo` em **2026-05-06**.
> Fontes: `CAPTERRA-FICHA.md` (14 capacidades) + `Modules/RecurringBilling/` + `resources/js/Pages/Financeiro/` + `Modules/NfeBrasil/`.
> ADR de governança: [0089](../../decisions/0089-capterra-driven-module-evolution.md).

## Resumo

| Bucket | Quantidade | % |
|---|---|---|
| ✅ APROVADO | 1 | 7% |
| 🟡 PARCIAL | 4 | 29% |
| ❌ AUSENTE | 9 | 64% |
| **Total** | 14 | 100% |

**Por score:**

| Score | ✅ | 🟡 | ❌ | Total |
|---|---|---|---|---|
| **P0** (bloqueador) | 1 | 3 | 0 | 4 |
| **P1** (mercado tem) | 0 | 1 | 5 | 6 |
| **P2** | 0 | 0 | 1 | 1 |
| **P3** | 0 | 0 | 3 | 3 |

**Diagnóstico:** módulo com infra **boa** (multi-tenant, drivers plugáveis, webhook idempotente) mas **superficial** — falta tudo de domínio recorrente (subscription/plan/invoice/dunning) e cobertura de teste é zero. Como "boleto avulso" funciona; como "cobrança recorrente" ainda não funciona.

## Inventário detalhado

| # | Capacidade | Score | Status | Evidência | Falta |
|---|---|---|---|---|---|
| 1 | Boleto bancário registrado via API banco | P0 | 🟡 | `Services/Boleto/Drivers/{Inter,C6,Asaas}Driver.php` + `BoletoService` + UI `ConfigurarBoletoSheet.tsx` | Tests/ vazia (0 testes); evidência prod ainda não coletada |
| 2 | Webhook de pagamento idempotente | P0 | 🟡 | Tabela `pg_webhook_events` UNIQUE + `ProcessAsaasWebhookJob` + `AsaasWebhookController` | Sem teste de retry/replay |
| 3 | Multi-gateway por business | P0 | ✅ | `rb_boleto_credentials` UNIQUE(business_id, banco) + `BoletoService::driver()` resolve por tenant + UI cadastro funcional | — |
| 4 | Cancelamento + estorno via API | P0 | 🟡 | `BoletoDriverContract::cancelar()` definido; implementado em InterDriver | Falta UI/botão "Cancelar título" + audit log; verificar implementação em C6/Asaas |
| 5 | Emissão automática de NFe ao receber | P1 | ❌ | Event `InvoicePaid` existe; **nenhum listener encontrado** em `Modules/NfeBrasil/` | Listener completo + autorização SEFAZ + DANFE + e-mail |
| 6 | Reconciliação automática (saldo + extrato) | P1 | 🟡 | `saldo_cached` em fin_contas_bancarias + `BALANCE_UPDATED` handler + `SyncBankBalancesJob` (fallback) + cards no Dashboard | Casamento transação→fatura (sem `rb_invoices` populadas, matcher inexistente) |
| 7 | Cartão de crédito recorrente (assinatura) | P1 | ❌ | — | Tokenização + ChargeAttemptJob + retry + tela |
| 8 | PIX recorrente (Open Finance) | P1 | ❌ | — | Endpoint /pix/recorrencia + jornada de consentimento + webhook |
| 9 | Régua de cobrança (dunning) | P1 | ❌ | — | Configuração de regras (D+1/3/7) + jobs disparam mensagens + log |
| 10 | Tela de assinaturas | P1 | ❌ | Sem subpasta `resources/js/Pages/Financeiro/Assinaturas/` | Página Inertia + filtros + ações pausar/cancelar/reativar + teste E2E |
| 11 | Proration mid-cycle | P2 | ❌ | — | ProrationCalculator + teste edge cases + UI preview |
| 12 | Split payment marketplace | P3 | ❌ | — | SplitConfig model + endpoint + UI |
| 13 | Métricas SaaS (MRR/Churn/LTV) | P3 | ❌ | — | Job mensal + dashboard Recharts |
| 14 | Cobrança via WhatsApp Business | P3 | ❌ | — | Integração WhatsApp Business API + template aprovado |

## Tasks propostas (aguardando aprovação Wagner)

> **Ordem por prioridade** (P0 primeiro). Cada task indica `module:RecurringBilling priority:P{N}`.
> Aprovar com `/comparativo aprovar 1,2,4` ou texto livre. **NÃO foram criadas no MCP ainda.**

### P0 — bloqueador

1. **[P0] Cobertura Pest — emissão e cancelamento dos 3 drivers** — _evidência: `Tests/Feature/` vazia; spec exige round-trip testado com sandbox response_
2. **[P0] Test de retry idempotente do ProcessAsaasWebhookJob** — _evidência: webhook recebido 2× não pode duplicar credit; sem teste cobrindo isso_
3. **[P0] Verificar e completar `cancelar()` em C6Driver e AsaasDriver + UI botão "Cancelar título" + audit log** — _evidência: contrato existe mas implementação não auditada nos 3 drivers; nenhuma UI ainda_

### P1 — mercado tem, cliente vai pedir

4. **[P1] Models Subscription/Plan/Invoice/ChargeAttempt + migrations rb_subscriptions/rb_plans/rb_invoices/rb_charge_attempts** — _fundação para todas as outras capacidades P1_
5. **[P1] Tokenização cartão + ChargeAttemptJob com retry + tela de assinatura** — _depende de #4_
6. **[P1] Listener de InvoicePaid em NfeBrasil → emissão automática NFe55 + DANFE + e-mail** — _diferencial vertical gráfica; spec exige prod-evidence ≥1 NFe autorizada_
7. **[P1] Régua de cobrança configurável (D+1/D+3/D+7) + jobs de envio + log auditável** — _depende de #4_
8. **[P1] Casamento automático transação bancária → fatura (matcher reconciliação)** — _completa capacidade #6_
9. **[P1] Tela /financeiro/assinaturas (Inertia/React) + ações pausar/cancelar/reativar + teste E2E** — _depende de #4_
10. **[P1] PIX recorrente Open Finance — endpoint + jornada de consentimento + webhook de cobrança gerada** — _BCB exige curto prazo; pesquisar driver Asaas/Inter_

### P2

11. **[P2] ProrationCalculator + UI preview de upgrade/downgrade** — _depende de #4_

### P3 — diferenciação opcional

12. **[P3] SplitConfig + endpoint split + UI configuração**
13. **[P3] Dashboard SaaS metrics (MRR/Churn/LTV) com gráfico Recharts**
14. **[P3] WhatsApp Business — envio de link de pagamento + template Meta**

## Próxima reauditoria sugerida

**2026-08-06** (trimestral) ou após mergear ≥3 das tasks P0/P1.

## Observações desta auditoria

- Módulo nasceu com **infra solida (drivers + webhook + multi-tenant)** mas **vazio em domínio recorrente** (sem subscription/plan/invoice).
- **Cobertura de teste = 0%** — bloqueador P0 antes de qualquer coisa nova.
- Listener de `InvoicePaid` em NfeBrasil é o **caminho crítico do diferencial vertical** (gráfica) — gateway boleto é commodity, mas "boleto pago → NFe55 emitida automaticamente" não é.
- Tasks #4 (models de domínio) é **bloqueador implícito** de #5/#7/#9/#11 — vale agrupar como Epic.
