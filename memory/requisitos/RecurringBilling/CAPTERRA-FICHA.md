---
id: requisitos-recurring-billing-capterra-ficha
---

# CAPTERRA-FICHA — RecurringBilling

> **Ficha canônica de benchmark do módulo RecurringBilling** — fonte de verdade para a skill `comparativo-do-modulo`.
> ADR de governança: [0089](../../decisions/0089-capterra-driven-module-evolution.md).

---

## Identidade do módulo

- **Nome interno**: `RecurringBilling`
- **Domínio de negócio**: cobrança recorrente de assinaturas + emissão de boleto/PIX/cartão + reconciliação + emissão de nota fiscal automática ao receber
- **Cliente principal alvo**: ROTA LIVRE (Larissa, biz=4) + qualquer business oimpresso com receita recorrente
- **Concorrentes-alvo direto** (5):
  - **Iugu** — iugu.com — gateway recorrente brasileiro mais maduro; foco em SaaS
  - **Asaas** — asaas.com — gateway BR + virtual PJ (já integrado como driver do oimpresso, ADR ARQ-0008)
  - **Vindi** — vindi.com.br — recorrência + dunning + cobrança massificada
  - **Pagar.me** — pagar.me — Stone group; boleto/cartão/PIX consolidado
  - **Mercado Pago Cobranças** — mercadopago.com.br — pagamentos + recebimento recorrente; força no marketplace

## Comparativos de referência

- `memory/comparativos/oimpresso_vs_concorrentes_capterra_2026_04_25.md` — análise geral oimpresso (genérica, não específica a recorrente)
- _(adicionar aqui ao criar comparativo dedicado a "gateways de cobrança recorrente BR")_

## Capacidades baseline com score

```yaml
capacidades:
  - nome: "Boleto bancário registrado via API banco"
    score: P0
    descricao: "Emite boleto registrado direto via API do banco (Inter/Asaas/Sicoob/Bradesco), sem intermediário; recebe webhook de pagamento"
    quem_tem: ["Iugu", "Asaas", "Vindi", "Pagar.me"]
    referencias: ["https://developers.bancointer.com.br/v4/reference/cobrancabolepix"]
    evidencia_de_pronto: "Driver instanciável via BoletoService::driver(business_id) + teste de roundtrip + webhook PAYMENT_RECEIVED registrado em pg_webhook_events + saldo atualizado em fin_contas_bancarias"

  - nome: "Webhook de pagamento idempotente"
    score: P0
    descricao: "Recebe e processa webhook do gateway garantindo at-least-once + idempotência por (provider, event_id)"
    quem_tem: ["Iugu", "Asaas", "Vindi", "Pagar.me", "Mercado Pago"]
    evidencia_de_pronto: "Tabela pg_webhook_events com UNIQUE(provider, event_id) + ProcessAsaasWebhookJob + teste cobrindo retry idempotente"

  - nome: "Multi-gateway por business"
    score: P0
    descricao: "Tenant escolhe qual gateway/banco usar; credenciais isoladas por business_id"
    quem_tem: ["—"] # diferencial oimpresso
    evidencia_de_pronto: "rb_boleto_credentials com UNIQUE(business_id, banco) + BoletoService::driver lê credencial do tenant ativo + UI de cadastro funcionando"

  - nome: "Cartão de crédito recorrente (assinatura)"
    score: P1
    descricao: "Cobrança automática mensal/anual em cartão tokenizado, com retry em falha"
    quem_tem: ["Iugu", "Asaas", "Vindi", "Pagar.me", "Mercado Pago"]
    evidencia_de_pronto: "Tokenização de cartão (PCI scope reduced) + ChargeAttemptJob com retry + tela de recorrência ativa"

  - nome: "PIX recorrente (Open Finance)"
    score: P1
    descricao: "Cobrança PIX agendada via Open Finance (jornada de autorização do pagador)"
    quem_tem: ["Iugu", "Asaas", "Pagar.me"]
    referencias: ["https://www.bcb.gov.br/estabilidadefinanceira/pixrecorrencia"]
    evidencia_de_pronto: "Endpoint /pix/recorrencia + jornada de consentimento + webhook de cobrança gerada"

  - nome: "Régua de cobrança (dunning automático)"
    score: P1
    descricao: "Sequência configurável de e-mails/SMS/WhatsApp a cada N dias após vencimento"
    quem_tem: ["Iugu", "Asaas", "Vindi"]
    evidencia_de_pronto: "Configuração de regras (D+1, D+3, D+7) + jobs disparam mensagens + log auditável"

  - nome: "Cancelamento + estorno via API"
    score: P0
    descricao: "Cancelar boleto não pago / estornar cartão pago, ambos via API do gateway"
    quem_tem: ["Iugu", "Asaas", "Vindi", "Pagar.me"]
    evidencia_de_pronto: "BoletoDriverContract::cancelar() implementado nos 3 drivers + tela com botão 'Cancelar título' + audit log"

  - nome: "Emissão automática de NFe ao receber"
    score: P1
    descricao: "Quando boleto/cartão é confirmado, dispara emissão de NFe modelo 55 (mercadoria) ou NFSe (serviço) sem intervenção humana"
    quem_tem: ["—"] # diferencial vertical (gráfica)
    referencias: ["module NfeBrasil"]
    evidencia_de_pronto: "Listener de InvoicePaid em Modules/NfeBrasil + NFe autorizada SEFAZ + DANFE renderizada + e-mail enviado"

  - nome: "Reconciliação automática (saldo + extrato)"
    score: P1
    descricao: "Saldo do banco sincronizado em tempo real; transações casadas com títulos sem ação manual"
    quem_tem: ["Asaas (saldo)", "Iugu", "Vindi"]
    evidencia_de_pronto: "fin_contas_bancarias.saldo_cached atualizado por webhook BALANCE_UPDATED + sync diário fallback + tela financeiro mostra saldo + R$ recebido casado a fatura"

  - nome: "Tela de assinaturas (subscription management)"
    score: P1
    descricao: "Dashboard com lista de assinaturas ativas, status, próxima cobrança, histórico, ações (pausar/cancelar/reativar)"
    quem_tem: ["Iugu", "Asaas", "Vindi", "Pagar.me"]
    evidencia_de_pronto: "Página Inertia /financeiro/assinaturas + filtros + ações + teste E2E"

  - nome: "Proration mid-cycle (upgrade/downgrade)"
    score: P2
    descricao: "Cliente troca de plano no meio do ciclo; sistema calcula crédito/débito proporcional"
    quem_tem: ["Iugu", "Vindi"]
    referencias: ["adr/tech/0003-proration-mid-cycle.md"]
    evidencia_de_pronto: "ProrationCalculator::calc(plano_atual, plano_novo, dias_restantes) + teste cobrindo casos edge + UI mostrando preview do valor"

  - nome: "Split payment (marketplace)"
    score: P3
    descricao: "Recebimento dividido automaticamente entre N recebedores"
    quem_tem: ["Pagar.me", "Mercado Pago", "Iugu"]
    evidencia_de_pronto: "Modelo SplitConfig + endpoint POST /assinaturas/{id}/split + UI de configuração + teste de divisão correta"

  - nome: "Métricas SaaS (MRR / Churn / LTV)"
    score: P3
    descricao: "Dashboard mostra MRR ativo, churn mensal, LTV médio dos clientes"
    quem_tem: ["Vindi", "Iugu (parcial)"]
    evidencia_de_pronto: "Cálculo via job mensal + dashboard com gráfico Recharts + comparação com mês anterior"

  - nome: "Cobrança via WhatsApp Business"
    score: P3
    descricao: "Envia link de pagamento + boleto via WhatsApp oficial Business API; recebe confirmação"
    quem_tem: ["Asaas", "Mercado Pago"]
    evidencia_de_pronto: "Integração WhatsApp Business + template aprovado Meta + log de envio + click-through"
```

## Como auditar este módulo (etapa específica)

> Esta seção é **lida pela skill** no passo 2.5.

**Locais a inspecionar (paths exatos):**
- Drivers: `Modules/RecurringBilling/Services/Boleto/Drivers/{Inter,C6,Asaas}Driver.php`
- Service orquestrador: `Modules/RecurringBilling/Services/Boleto/BoletoService.php` (decryptConfig + driver factory)
- Contrato: `Modules/RecurringBilling/Contracts/BoletoDriverContract.php` (emitir / cancelar / pdf)
- Models: `Modules/RecurringBilling/Models/{BoletoCredential,Invoice,Subscription,Plan}.php`
- Webhooks: `Modules/RecurringBilling/Http/Controllers/AsaasWebhookController.php` + `Routes/api.php`
- Jobs: `Modules/RecurringBilling/Jobs/{ProcessAsaasWebhookJob,SyncBankBalancesJob,GenerateInvoicesJob,SendBoletoEmailJob}.php`
- Events: `Modules/RecurringBilling/Events/{InvoicePaid,SubscriptionCreated}.php`
- Tabelas: `pg_webhook_events`, `rb_boleto_credentials`, `rb_invoices`, `rb_subscriptions`, `rb_plans`, `rb_charge_attempts`
- FK no Financeiro: `fin_contas_bancarias.rb_gateway_credential_id`, `fin_contas_bancarias.saldo_cached`, `fin_contas_bancarias.tipo_conta`
- Telas: `resources/js/Pages/Financeiro/ContasBancarias/components/ConfigurarBoletoSheet.tsx`, `resources/js/Pages/Financeiro/RecurringBilling/*` (criar conforme necessário)
- Tests: `Modules/RecurringBilling/Tests/Feature/*.php` (Pest)

**Critérios customizados de classificação:**

| Capacidade | ✅ APROVADO requer | 🟡 PARCIAL aceita | ❌ AUSENTE |
|---|---|---|---|
| Boleto bancário registrado | Driver implementa contrato + teste + UI cadastra credencial + webhook recebido em prod | Driver existe, UI/teste ausente | Sem driver |
| Webhook idempotente | Tabela `pg_webhook_events` UNIQUE + Job processa + teste de retry | Tabela existe, sem retry | Sem tabela |
| Cartão recorrente | Tokenização + ChargeAttemptJob + tela | Modelo existe, sem job | Sem modelo |
| Reconciliação | `saldo_cached` + sync webhook + sync fallback diário + UI mostra | 1 dos 4 OK | Nenhum dos 4 |
| NFe automática | Listener de `InvoicePaid` em NfeBrasil + NFe autorizada em prod ≥1 vez | Listener existe sem prod-evidence | Sem listener |
| Tela de assinaturas | Página Inertia funcional + ações + teste E2E | Página existe sem ações | Sem página |

**Métricas de prod relevantes:**
- Taxa de webhook processado com sucesso — meta `>99.5%` — query: `SELECT processed, count(*) FROM pg_webhook_events WHERE provider='asaas' GROUP BY processed`
- Latência média do ProcessAsaasWebhookJob — meta `<5s p95`

## Métricas de adoção

- **Última auditoria**: nunca (1ª execução pendente, prova de conceito ADR 0089)
- **Capacidades P0 cobertas**: a determinar
- **Gap P0+P1 atual**: a determinar
- **Próxima reauditoria sugerida**: 2026-08-06 (trimestral)

## Histórico de revisão da ficha

- `2026-05-06` — Wagner — criação da ficha como prova de conceito do ADR 0089

## Referências externas

- Iugu API: https://dev.iugu.com/reference
- Asaas API: https://docs.asaas.com/reference
- Vindi API: https://vindi.github.io/api-docs
- Pagar.me API: https://docs.pagar.me/
- Mercado Pago Recurring: https://www.mercadopago.com.br/developers/pt/docs/subscriptions/landing
- Banco Central — PIX Recorrência: https://www.bcb.gov.br/estabilidadefinanceira/pixrecorrencia

---

## UX heuristics (Capterra v2 — eixo Usabilidade)

> Capterra v2 ([ADR 0101](../../decisions/0101-sistema-charter-capterra-governanca-escopo.md) §3 eixos): além de features, mede ergonomia.

```yaml
ux_heuristics:
  - id: emit-nfe-clicks
    nome: "Cliques pra emitir NFe a partir de boleto pago"
    score: P0
    benchmark: "Asaas: 1 (auto). Iugu: 5 manual. Vindi: 3 manual."
    target: "<= 1 (deve ser auto)"
    metrica: "navegacao_steps_emit_nfe_pos_boleto_pago"

  - id: dashboard-time-to-saldo
    nome: "Tempo até ver saldo atualizado no dashboard"
    score: P1
    benchmark: "Asaas: <2s realtime. Iugu: F5 manual. Vindi: F5 manual."
    target: "<= 3s primeira pintura"
    metrica: "first_paint_dashboard_extrato_p95_ms"

  - id: cancelar-titulo-clicks
    nome: "Cliques pra cancelar título não pago"
    score: P0
    benchmark: "Asaas: 2 (lista → botão). Iugu: 2. Vindi: 4 (motivo obrigatório)."
    target: "<= 2"
    metrica: "navegacao_steps_cancelar_titulo"
```

## Automation targets (Capterra v2 — eixo Automação)

> O que mercado faz sem humano? Listener? Cron? Job? Webhook?

```yaml
automation_targets:
  - id: nfe-on-boleto-paid
    nome: "Auto-emitir NFe55 quando boleto recorrente é pago"
    score: P0
    benchmark: "Asaas SIM (default). Iugu SIM (config). Vindi PARCIAL."
    target: "Listener invoice.paid → EmitirNfeJob, p95 < 30s, idempotente"
    metrica: "auto_nfe_p95_seconds + auto_nfe_success_rate"

  - id: saldo-extrato-sync-daily
    nome: "Sync extrato + saldo da conta bancária diário sem ação humana"
    score: P0
    benchmark: "Asaas SIM (real-time). Iugu SIM (D-1). Inter API SIM (D-7)."
    target: "Cron diário 07:00 BRT roda InterExtratoJob, idempotency_key UNIQUE"
    metrica: "saldo_drift_dias + sync_extrato_idempotency_violations"

  - id: dunning-regua-d1-d3-d7
    nome: "Disparo automático de mensagens a D+1/D+3/D+7 do vencimento"
    score: P1
    benchmark: "Iugu SIM (configurável). Asaas SIM. Vindi SIM."
    target: "DunningSchedulerJob + SendDunningMessageJob, idempotente (invoice_id, dia)"
    metrica: "dunning_dispatched_total + dunning_idempotency_violations"

  - id: webhook-replay-protection
    nome: "Webhook de pagamento idempotente (replay 2x não duplica crédito)"
    score: P0
    benchmark: "Todos têm. Padrão: UNIQUE(provider, event_id)."
    target: "pg_webhook_events UNIQUE + ProcessAsaasWebhookJob skip se já processado"
    metrica: "webhook_replay_skipped + webhook_double_credit_24h (alvo 0)"

  - id: charge-retry-cartao
    nome: "Retry exponencial em cartão recusado (smart retry)"
    score: P1
    benchmark: "Iugu SIM (3 tentativas). Vindi SIM. Asaas SIM."
    target: "ChargeAttemptJob retry com backoff 1d/3d/7d; retry_count em ChargeAttempt"
    metrica: "charge_retry_success_rate + charge_recovered_after_retry"
```
