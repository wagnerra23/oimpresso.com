---
module: Woocommerce
na_justified:
  D5: "Gateway de integração WooCommerce webhook — não módulo cliente-facing direto. Integração técnica entre oimpresso e lojas WooCommerce externas (B2B2C). ADR 0121 §integrações + ADR 0105 §cliente como sinal qualificado: módulo dormente preservado pra futuro, sem cliente ativo reportando dor pra justificar UX investigation."
  D6.b: "Sync WooCommerce REST API depende de latência rede externa do site cliente — fora do controle oimpresso. p99 OTel <500ms N/A enquanto módulo dormante sem cliente ativo + instrumentação OTel project-wide pendente. ADR 0105 (cliente como sinal qualificado)."
related_adrs: [0093, 0105, 0121, 0153, 0154, 0155, 0156]
---

# SPEC — Modules/Woocommerce

> Integração com lojas WooCommerce externas — sync de produtos/categorias/tax/atributos via REST API + webhook receiver pra ordens. Herdado do UltimatePOS v6. **Sem cliente ativo hoje** — scaffold preservado pra futuro caso surja cliente que precise.

## Contexto

- **Stack:** Laravel 13.6 + WooCommerce REST API v3 (via `automattic/woocommerce` SDK)
- **Tabelas:** `woocommerce_sync_logs` + colunas adicionadas em `business`, `categories`, `products`, `tax_rates`, `variation_templates`, `media`
- **Owner:** sem owner ativo — manutenção dormente
- **Pré-requisito Tier 0:** `business_id` em todas tabelas/queries ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md))

## User Stories

### US-WOO-001 — Configurar credenciais API (consumer key/secret + URL)
**Como** lojista, **quero** cadastrar URL da loja WooCommerce + consumer_key + consumer_secret, **pra** o oimpresso conseguir falar com a API.
- Tela: `woocommerce/api_settings.blade.php` + partial `partials/api_settings.blade.php`
- Controller: `WoocommerceController::apiSettings|saveApiSettings`
- Tabela: colunas `woocommerce_*` em `business`
- Aceite: ao salvar, faz GET `/wp-json/wc/v3/products?per_page=1` validando credencial; armazena criptografado via `Crypt::encrypt`

### US-WOO-002 — Sync de produtos (oimpresso → WooCommerce)
**Como** lojista, **quero** rodar sync manual ou agendado dos produtos do oimpresso pra Woo, **pra** manter catálogo público alinhado com estoque interno.
- Command: `WoocommerceSyncProducts` (artisan + scheduled)
- Controller: `WoocommerceController::syncProducts`
- Util: `WoocommerceUtil::createProduct|updateProduct`
- Aceite: produto com `disable_woocommerce_sync=true` é pulado; sync popula `woocommerce_product_id` na nossa tabela; log em `woocommerce_sync_logs`

### US-WOO-003 — Sync de categorias e tax rates
**Como** lojista, **quero** que categorias/taxas configuradas no oimpresso sejam refletidas na Woo, **pra** evitar manutenção dupla.
- Util: `WoocommerceUtil::syncCategories|syncTaxRates`
- Tabela: colunas `woocommerce_category_id` em `categories`, `woocommerce_tax_rate_id` em `tax_rates`
- Aceite: idempotente; criar categoria nova no oimpresso dispara create na Woo via observer ou manual sync

### US-WOO-004 — Visualizar sync log + diagnóstico
**Como** lojista/admin, **quero** ver histórico de syncs (timestamp, status, erros, itens afetados), **pra** depurar falha de sync.
- Tela: `woocommerce/sync_log.blade.php` + partial `partials/log_details.blade.php`
- Entity: `WoocommerceSyncLog` (`status`, `details` JSON, `business_id`)
- Aceite: DataTable filtrável por status/tipo; modal mostra payload completo do erro

### US-WOO-005 — Webhook: pedido criado na Woo → venda no oimpresso
**Como** lojista, **quero** que pedido novo na Woo crie automaticamente Transaction tipo `sell` no oimpresso, **pra** o estoque baixar sem retrabalho.
- Controller: `WoocommerceWebhookController::orderCreated`
- Endpoint: POST `/woocommerce/webhook/{business_id}/order-created`
- Aceite: valida HMAC com `woocommerce_wh_oc_secret` (coluna em `business`); cria customer + Transaction + items; respeita `skipped_orders_fields` config

### US-WOO-006 — Webhook: pedido atualizado na Woo → atualizar no oimpresso
**Como** lojista, **quero** que mudança de status do pedido (paid/refunded/cancelled) reflita na Transaction do oimpresso, **pra** financeiro/estoque ficar coerente.
- Controller: `WoocommerceWebhookController::orderUpdated`
- Endpoint: POST `/woocommerce/webhook/{business_id}/order-updated`
- Aceite: refunded reverte Transaction (cria refund); cancelled marca cancelada + libera estoque; idempotente por `wh_id` Woo

## Anti-padrões (NÃO fazer)

- ❌ Armazenar `consumer_secret` em plaintext — sempre `Crypt::encrypt`
- ❌ Webhook sem validação HMAC — qualquer um cria venda no business
- ❌ Sync síncrono pesado no request HTTP — usar queue
- ❌ Apagar `woocommerce_sync_logs` (audit append-only)
- ❌ Compartilhar `consumer_key`/`secret` entre businesses — cada business tem seu

## Testes existentes (Wave B)

- `Tests/Feature/MultiTenantIsolationTest.php`
- `Tests/Feature/ScaffoldTest.php`
- `Tests/Feature/SmokeRoutesTest.php`
