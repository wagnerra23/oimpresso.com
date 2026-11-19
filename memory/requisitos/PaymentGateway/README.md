# Modules/PaymentGateway

> Camada técnica de cobrança BR — drivers de API (Inter, C6, Asaas, BCB Pix, Pagar.me, Sicoob), CNAB por banco, webhooks, credenciais, TEF. Consumida por Sell, Financeiro, RecurringBilling, NFSe, Superadmin.

**Estado em 2026-11-19:** em produção, com dois agendamentos ativos em `app/Console/Kernel.php` (`inter-reconcile-pix` e `inter-importar-recebimentos`) e um terceiro registrado que só executa com flag (`retry-orphan-webhooks`). As flags que tocam valor nascem `false` em `Modules/PaymentGateway/Config/config.php`. Estado consolidado das capacidades: [BRIEFING.md](BRIEFING.md).
**ADR de criação:** a [ADR 0170](../../decisions/0170-paymentgateway-extracao-camada-cobranca.md) foi arquivada em 2026-07-10 sem sucessora; qual ADR passa a ser o charter do módulo é decisão pendente do [W].
**Origem:** extraído de `Modules/RecurringBilling` (drivers + webhooks).

> Seções marcadas "plano original" descrevem o desenho de maio/2026 (Onda 0) e não foram reconciliadas com o código; o contrato vigente está em [CONTRACTS.md](CONTRACTS.md) e o escopo em [SCOPE.md](SCOPE.md).

## Por que existe

Antes do ADR 0170, drivers bancários e webhooks moravam em `Modules/RecurringBilling`. Quando `Modules/Financeiro` e `Modules/NfeBrasil` começaram a consumir `BoletoCredentialResolver` cross-module (Wave 23 D2), ficou claro que esses internals eram **infra**, não feature de recorrência.

Este módulo é essa infra: **um único lugar pra falar com bancos**. Quem precisa cobrar (Sell avulso, Invoice recorrente, mensalidade SaaS) conversa com `PaymentGatewayContract` e ignora qual driver está embaixo.

## Para o cliente final (Larissa, Eliana[E])

### Cadastrar gateway

1. `/settings/payment-gateways` (Settings → Gateways de Pagamento)
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

Drivers de API em `Modules/PaymentGateway/Services/Drivers/` (lista viva: `ls` nessa pasta):

| Driver | Tipos (`supports()`) |
|---|---|
| `InterDriver` | boleto, pix_cob, pix_cobv |
| `C6Driver` | boleto, pix_cob |
| `AsaasDriver` | boleto, pix_cob, card |
| `BcbPixDriver` | pix_recv (PIX Automático) |
| `PagarmeDriver` | boleto, pix_cob, card |
| `SicoobApiDriver` | boleto, pix_cob ([RUNBOOK-sicoob-api.md](RUNBOOK-sicoob-api.md)) |

Drivers CNAB (remessa/retorno por arquivo) em `Services/Cnab/Drivers/`: Ailos, Banrisul, BB, Bradesco, BTG, Caixa, Cresol, Itaú, Santander, Sicoob, Sicredi.

A credencial é resolvida por `PaymentGatewayService::for($account)` ou `comCredencial($credential)`; não existe `PaymentGatewayCredentialResolver`. `PesaPalDriver` nunca foi criado.

## Lifecycle Cobrança canônico

```
[criada] → emitida → paga
              ↓
            vencida → emitida (smart retry, até 3x) ou cancelada (após N dias)
              ↓
           cancelada (manual) | erro (gateway recusou)
```

Plano original. Não existe `CobrancaService`; as transições acontecem em `PaymentGatewayService`, `ReconciliarCobrancaService` e nos processadores de webhook/CNAB.

## Multi-tenant Tier 0

Toda `Cobranca` e `PaymentGatewayCredential` tem `business_id` global scope ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)).

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

Não existe comando `paymentgateway:health`. O teste de conexão por credencial é feito na tela `/settings/payment-gateways` via `Services/HealthCheckService.php`. Os comandos que existem estão em [CONTRACTS.md §5](CONTRACTS.md).

## Tests

Testes em `Modules/PaymentGateway/Tests/` e `tests/Feature/PaymentGateway/`. Contagem: `git ls-files 'Modules/PaymentGateway/Tests/**Test.php' 'tests/Feature/PaymentGateway/**Test.php' | wc -l`. A lane de CI é `.github/workflows/paymentgateway-pest.yml`, com quarentena em `.github/paymentgateway-pest-quarantine.list`. Rodar manualmente só no CT 100 (proibicoes.md §Ambiente).

## ADRs referência

- [ADR 0170](../../decisions/0170-paymentgateway-extracao-camada-cobranca.md) — charter original (arquivada em 2026-07-10, sem sucessora)
- [ADR 0017](../../decisions/0017-officeimpresso-restaurado-superadmin-exclusivo.md) — emendado pela Onda 5 (Superadmin dogfooding)
- [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) — Tier 0 isolation
- [ADR 0143](../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md) — FSM pattern
- US-RB-044 — NFe-de-boleto-pago (vira listener de `CobrancaPaga`, canônico irrevogável)
- Resolução BCB 380/2024 — PIX Automático (regulamentação do driver `bcb_pix`)

## Anti-patterns proibidos

- ⛔ NÃO importar driver concreto fora do módulo. Sempre via `app(PaymentGatewayContract::class)`
- ⛔ NÃO instanciar `BoletoService` / `PixService` / `CardService` direto — só via contrato
- ⛔ NÃO commit credenciais em `config` sem `Crypt::encryptString` em `client_secret` / `api_key` / `certificado_key_b64` / `certificado_senha` / `webhook_secret`
- ⛔ NÃO `forceDelete` em Cobranca com status emitida/paga — use cancel + soft delete (audit LGPD D7.b)
- ⛔ NÃO emitir cobrança avulsa sem `contact_id` (pagador) — viola LGPD (rastreabilidade do destinatário)
- ⛔ NÃO resolver credencial direto via `PaymentGatewayCredential::find()` fora do serviço — use `PaymentGatewayService::for()`/`comCredencial()`
- ⛔ NÃO processar webhook sem checar idempotência via `gateway_webhook_events.external_id` UNIQUE constraint
- ⛔ NÃO reusar `external_id` entre `sandbox` e `production` — sempre escopo por `ambiente`

## Migração de RecurringBilling (plano original da Onda 3)

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
