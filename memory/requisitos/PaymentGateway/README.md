# Modules/PaymentGateway

> Camada técnica de cobrança BR — drivers Inter / C6 / Asaas / PIX Automático BCB, webhooks, credenciais, CNAB. Consumida por Sell, RecurringBilling, NFSe, Superadmin.

**Status:** Onda 0 (ADR 0170 proposto, módulo registrado mas não habilitado).
**Live:** previsto biz=1 após Onda 4.
**Origem:** extraído de `Modules/RecurringBilling` (drivers + webhooks + BoletoService).

## Por que existe

Antes do ADR 0170, drivers bancários e webhooks moravam em `Modules/RecurringBilling`. Quando `Modules/Financeiro` e `Modules/NfeBrasil` começaram a consumir `BoletoCredentialResolver` cross-module (Wave 23 D2), ficou claro que esses internals eram **infra**, não feature de recorrência.

Este módulo é essa infra: **um único lugar pra falar com bancos**. Quem precisa cobrar (Sell avulso, Invoice recorrente, mensalidade SaaS) conversa com `PaymentGatewayContract` e ignora qual driver está embaixo.

## Para o cliente final (Larissa, Eliana[E])

### Cadastrar gateway

1. `/payment-gateway/credenciais` (Settings → Gateways de Pagamento)
2. "Adicionar gateway" → escolhe banco (Inter / C6 / Asaas / Pix BCB)
3. Cola credenciais (Inter: certificado mTLS + client_id/secret; C6: agência/conta/codigo; Asaas: api_key; BCB Pix: certificado + CNPJ recebedor homologado)
4. Vincula a **uma conta bancária** (FK pra `accounts.id`) — é onde o dinheiro vai cair
5. Health check automático (consulta endpoint de status do banco)

### Emitir cobrança

Três pontos de entrada — **mesma lista de saída** em `/cobranca`:

| Onde | Quem aciona | Como |
|---|---|---|
| **Drawer da Venda** | Larissa | Botão "Emitir cobrança" no `SaleSheet.tsx` (Sells/Index) → herda contato, valor, descrição |
| **Faturamento automático** | Cron RecurringBilling | Job `recurring:gerar-invoices` daily 02:00 → Invoice gerada → chama `PaymentGateway::emitirBoleto()` |
| **Cobrança avulsa** | Larissa | `/cobranca` → "Nova cobrança" → escolhe contato + valor + vencimento + tipo (boleto/PIX/cartão) |

### Acompanhar

`/cobranca` lista TUDO que foi emitido:
- Filtros: status (aberta/paga/vencida/cancelada), gateway, conta, vencimento
- KPIs: valor a receber, vencidas, ticket médio, taxa de inadimplência
- Drawer: detalhe + linha digitável + QR code + remessa/retorno + payload bruto do gateway
- Bulk actions: enviar 2ª via, cancelar, baixar manual

### O que **não** muda

- `Modules/RecurringBilling` continua tendo `/recurring-billing/planos` e `/recurring-billing/assinaturas` — só que internamente passa a chamar `PaymentGatewayContract` ao invés de `BoletoService` direto.
- Tela Financeiro normal (`/financeiro`, fluxo de caixa, DRE) — só consome `Cobranca` como source-of-truth de "a receber".

## Drivers suportados

| Driver | Status | Tipos | Ambiente | Cred config |
|---|---|---|---|---|
| **InterDriver** | 🟡 Onda 3 (migrar de RB) | boleto + pix cob/cobv | sandbox + production | mTLS (cert+key) + client_id/secret |
| **C6Driver** | 🟡 Onda 3 (migrar de RB) | boleto | production | agencia + conta + codigo_cliente |
| **AsaasDriver** | 🟡 Onda 3 (migrar de RB) | boleto + pix + cartão | sandbox + production | api_key |
| **PixAutomaticoBcbDriver** | 🔵 Onda 4 (novo) | pix recv (Pix Automático) | sandbox + production | mTLS BCB + CNPJ recebedor homologado |
| **PesaPalDriver** | ⚪ vestigial (deprecated) | cartão internacional | production | api_key | substituído por Asaas em Onda 5 |

Resolução via `PaymentGatewayCredentialResolver` (ex-`BoletoCredentialResolver` movido de RB).

## Lifecycle Cobrança canônico

```
[criada] → emitida → paga
              ↓
            vencida → emitida (smart retry, até 3x) ou cancelada (após N dias)
              ↓
           cancelada (manual) | erro (gateway recusou)
```

States gerenciados pelo `CobrancaService` (ADR 0143 FSM pipeline pattern inspirado).

## Multi-tenant Tier 0

Toda `Cobranca` e `PaymentGatewayCredential` tem `business_id` global scope ([ADR 0093](../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)).

Jobs (`ProcessInterWebhookJob`, `ProcessAsaasWebhookJob`, `ProcessBcbPixWebhookJob`, `CancelarCobrancaJob`) recebem `businessId` no constructor.

Webhooks resolvem `business_id` via `external_reference` antes de qualquer query — nunca confiam só em headers/IP.

## Eventos broadcast

```
CobrancaEmitida    → RB marca Invoice.boleto_id; Sell marca sale.payment_status='aguardando'
CobrancaPaga       → RB marca Invoice.paid_at; NFSe emite NFSe (US-RB-044 canônico);
                     Sell faz AccountTransaction; Superadmin renova subscription do tenant
CobrancaVencida    → RB smart retry; Subscription overdue
CobrancaCancelada  → RB Invoice canceled; Sell sale.payment_status='cancelled'
CobrancaErro       → Otel alerta + retry handler decide reemitir ou parar
```

Quem se interessa, escuta. PaymentGateway **não conhece** RecurringBilling / Sell / NFSe / Superadmin.

## OTel observability

Métodos críticos wrap em `OtelHelper::spanBiz` (mesmo padrão D9 Wave 17 do RB):

- `pg.cobranca.emitir.boleto` · `pg.cobranca.emitir.pix` · `pg.cobranca.emitir.pix_automatico` · `pg.cobranca.cobrar_cartao`
- `pg.cobranca.cancelar` · `pg.cobranca.consultar`
- `pg.webhook.processar.inter` · `pg.webhook.processar.c6` · `pg.webhook.processar.asaas` · `pg.webhook.processar.bcb_pix`
- `pg.driver.health_check.{driver}`

## Health check

```bash
php artisan paymentgateway:health
php artisan paymentgateway:health --business=1 --detail
php artisan paymentgateway:health --json --alert  # exit 0/1/2 pra cron
```

Sinais críticos (a definir em Onda 1, baseline 10 igual ao RB):

- `payment_gateway.credentials_table` — schema OK
- `cobrancas.table` — schema OK
- `gateway_webhook_events.table` — schema OK
- `credenciais_ativas` — pelo menos 1 credencial ativa por business com cobrança aberta
- `driver_resolvidos` — 4 drivers respondem ao `health()` (inter, c6, asaas, bcb_pix)
- `webhook_idempotency` — `gateway_webhook_events` sem duplicatas em última hora
- `last_emitida_freshness` — última cobrança emitida há menos de 24h pra business ativos
- `cobrancas_em_erro` — count de status=erro em última hora abaixo do threshold
- `pix_recv_mandatos_ativos` — mandatos PIX Automático vigentes
- `retention_policy` — payload_gateway em cobrancas concluídas há +90d sob retention

## Tests

```bash
vendor/bin/pest Modules/PaymentGateway/Tests
```

Cobertura prevista (Onda 1+):
- `PaymentGatewayService` — contrato com mock de cada driver
- Drivers — sandbox real Inter/Asaas/BCB; mock C6 (sem sandbox)
- Webhooks — idempotência multi-tenant + assinatura
- `PaymentGatewayCredentialResolver` — escolha por business/account/tipo
- `CobrancaService` — FSM transitions
- OTel D9 — spans presentes
- Customer Journey — Sell → emitir → webhook → marcar paga → AccountTransaction

## ADRs referência

- [ADR 0170](../../memory/decisions/0170-paymentgateway-extracao-camada-cobranca.md) — charter (este módulo)
- [ADR 0017](../../memory/decisions/0017-officeimpresso-restaurado-superadmin-exclusivo.md) — emendado pela Onda 5 (Superadmin dogfooding)
- [ADR 0093](../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) — Tier 0 isolation
- [ADR 0143](../../memory/decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md) — FSM pattern
- US-RB-044 — NFe-de-boleto-pago (vira listener de `CobrancaPaga`, canônico irrevogável)
- Resolução BCB 380/2024 — PIX Automático (regulamentação do driver `bcb_pix`)

## Anti-patterns proibidos

- ⛔ NÃO importar driver concreto fora do módulo. Sempre via `app(PaymentGatewayContract::class)`
- ⛔ NÃO instanciar `BoletoService` / `PixService` / `CardService` direto — só via contrato
- ⛔ NÃO commit credenciais em `config` sem `Crypt::encryptString` em `client_secret` / `api_key` / `certificado_key_b64` / `certificado_senha` / `webhook_secret`
- ⛔ NÃO `forceDelete` em Cobranca com status emitida/paga — use cancel + soft delete (audit LGPD D7.b)
- ⛔ NÃO emitir cobrança avulsa sem `contact_id` (pagador) — viola LGPD (rastreabilidade do destinatário)
- ⛔ NÃO bypass `PaymentGatewayCredentialResolver` direto via `PaymentGatewayCredential::find()` — use o resolver pra garantir `decryptConfig` correto
- ⛔ NÃO processar webhook sem checar idempotência via `gateway_webhook_events.external_id` UNIQUE constraint
- ⛔ NÃO reusar `external_id` entre `sandbox` e `production` — sempre escopo por `ambiente`

## Migração de RecurringBilling (referência pra Onda 3)

Mapa de arquivos:

```
ORIGEM (RecurringBilling)              →  DESTINO (PaymentGateway)
─────────────────────────────────────     ────────────────────────────────────
Services/Boleto/Drivers/InterDriver       Services/Drivers/InterDriver
Services/Boleto/Drivers/C6Driver          Services/Drivers/C6Driver
Services/Boleto/Drivers/AsaasDriver       Services/Drivers/AsaasDriver
Services/Boleto/BoletoService             Services/BoletoService
Services/Boleto/BoletoCredentialResolver  Services/PaymentGatewayCredentialResolver
Entities/BoletoCredential                 Entities/PaymentGatewayCredential
Http/Controllers/InterWebhookController   Http/Controllers/Webhooks/InterWebhookController
Http/Controllers/AsaasWebhookController   Http/Controllers/Webhooks/AsaasWebhookController
Jobs/ProcessInterWebhookJob               Jobs/ProcessInterWebhookJob
Jobs/ProcessAsaasWebhookJob               Jobs/ProcessAsaasWebhookJob
Jobs/CancelarCobrancaAsaasJob             Jobs/CancelarCobrancaJob (driver-agnostic)
Jobs/RefundCobrancaAsaasJob               Services/Drivers/AsaasDriver::refund() (driver-internal)
```

URLs de webhook antigas viram 301 redirect durante 30 dias (padrão Onda 10 do próprio RB).
