---
module: Marketplaces
status: rascunho
version: "1.0"
last_updated: "2026-06-13"
lifecycle: aguarda-sinal-qualificado
piloto: ROTA LIVRE (Modules/Vestuario — sinal natural se Larissa ativar venda ML) OU 1º OfficeImpresso saudável com venda ML ativa (Vargas/Mhundo)
piloto_previsao: depende de (a) Larissa confirmar interesse ML/Shopee OU (b) 1º cliente OfficeImpresso reportar "vendo em ML, quero integrar"
cnae_aplicavel: cross-vertical (qualquer cliente que vende online — não tem CNAE próprio)
related_adrs:
  - "0143-fsm-pipeline-live-prod-marco-2026-05-12"
  - "0121-oimpresso-modular-especializado-por-vertical"
  - "0094-constituicao-v2-7-camadas-8-principios"
  - "0093-multi-tenant-isolation-tier-0"
  - "0105-cliente-como-sinal-guiar-sem-mandar"
  - "0106-recalibracao-velocidade-fator-10x-ia-pair"
  - "0035-stack-ai-canonica-wagner-2026-04-26"
  - "0011-alinhamento-padrao-jana"
  - "0089-capterra-driven-module-evolution"
  - "0117-multiplos-numeros-whatsapp-por-business"
  - "0119-migration-factory-capacidade-institucional"
  - "0129-state-machine-canonica-fsm-rbac"
related_proposals: [proposals/drafts/marketplaces-modulo-cross-vertical.md]
last_review: 2026-05-12
owner: [W]
---

# Especificação funcional — Marketplaces (planejado — não existe) (cross-vertical)

> Convenção do ID: `US-MKT-NNN` para user stories, `R-MKT-NNN` para regras Gherkin.
> **Modulo NÃO existe em código.** SPEC **antecipatório** — formaliza contrato de construção SE/QUANDO (a) Larissa (ROTA LIVRE biz=4 ativa) reportar venda marketplace OU (b) 1+ OfficeImpresso saudável (Vargas/Extreme/Gold/Mhundo) confirmar "vendo em ML/Shopee, preciso integrar com NFe" ([ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) gatilho).
> Antes de scaffoldear (caso ativado), ler [Modules/Vestuario](../../../Modules/Vestuario) (vertical live referência) + [Modules/NfeBrasil SPEC](../NfeBrasil/SPEC.md) (NFe motor) + [ADR 0143](../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md) (FSM canon — integração obrigatória) + [ADR 0117](../../decisions/0117-multiplos-numeros-whatsapp-por-business.md) (padrão multi-canais per-business) e imitar ([ADR 0011](../../decisions/0011-alinhamento-padrao-jana.md)).

## 1. Visão

`Marketplaces (planejado — não existe)` é o módulo **cross-vertical canônico** que permite **qualquer vertical** oimpresso (Vestuario / OficinaAuto / Autopecas / ComunicacaoVisual / futuras) vender via **marketplaces brasileiros** (Mercado Livre, Shopee, Amazon BR, Magalu Hub, Americanas, futuros) com:

- **OAuth2 per-business** (cada `business_id` tem credenciais próprias — Tier 0 [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md))
- **Webhooks orders → FSM canon stage `pedido_ml_recebido`** ([ADR 0143](../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md))
- **Sync bi-direcional opt-in** (anúncios + estoque + preço + status pedido)
- **NFe automática** com CFOP marketplace correto (5102/5949/5910 conforme operação) — reusa [Modules/NfeBrasil](../NfeBrasil/SPEC.md) US-NFE-002
- **Reconciliação financeira** (taxa ML + frete + Mercado Pago split D+12 a D+60) — reusa [Modules/Financeiro](../Financeiro/) AR
- **Rastreamento Correios → marketplace** (auto-fechamento pedido SLA)
- **Disputas/claims workflow** (Mercado Livre claims, Shopee returns)
- **Jana IA** responde "quantos pedidos ML hoje?" / "qual SKU mais vendido ML mês?"

**Tese de entrada:** Brasil tem **gap concreto** entre ERPs especializados por vertical (oimpresso modular) e hubs marketplace generalistas (Tiny/Bling/Olist). Cliente oimpresso vertical (vestuário/auto/comvisual) que também vende ML hoje **sai do oimpresso pra Tiny** pra ter integração. Marketplaces (planejado — não existe) evita perda + entrega Jana IA + multi-tenant Tier 0 que nenhum hub atual tem.

**Status atual:** **NÃO em construção.** Sem sinal qualificado (Larissa ou 1º OfficeImpresso saudável), **viola ADR 0105**. Tiny/Bling cobrem ~95% do que o mercado pede — só faz sentido construir se cliente oimpresso reportar dor real.

## 2. Marketplaces cobertos (Brasil)

### 2.1 Mercado Livre (prioridade #1 — ~60-70% volume marketplace BR)

- **API canônica**: [developers.mercadolivre.com.br](https://developers.mercadolivre.com.br) — REST + JSON
- **OAuth2 flow**: authorization code grant + refresh token (refresh válido 90 dias se rotacionado periodicamente, access token 6h)
- **Webhooks**: tópicos `orders_v2`, `items`, `claims`, `messages`, `shipments`, `questions`, `payments`, `stock_locations`
- **Rate limits**: ~1500 calls/min por seller (HTTP 429 se exceder) — varia per app_id em recursos de imagem
- **Endpoints chave**:
  - `GET /users/me` — identifica seller
  - `GET /orders/search?seller={id}` — listagem pedidos
  - `GET /orders/{id}` + `/orders/{id}/shipments` — detalhe + envio
  - `POST /items` / `PUT /items/{id}` — anúncios
  - `GET /sites/MLB/categories` — categorias BR
  - `GET /shipments/{id}/items` — rastreio
  - `POST /messages/packs/{pack}/sellers/{user}` — mensagem cliente
  - `POST /post-purchase/v1/claims/{id}/evidences/files` — defesa disputa
- **Categorias + atributos obrigatórios**: cada categoria (`MLB1234`) tem `attributes` mandatory variando (marca, modelo, GTIN, peso, dimensões). API `/categories/{id}/attributes` lista. Cadastro sem atributos obrigatórios = anúncio rejeitado.
- **Tipos anúncio (listing_type_id)**:
  - **`free`** (Grátis) — sem destaque, 60d, sem fee fixo, ❌ cancela após 60 sem venda
  - **`bronze`** (legacy descontinuado)
  - **`gold_special`** (Clássico) — 10-14% comissão + custo fixo R$ [redacted Tier 0]-6,75 produtos R$ [redacted Tier 0]-R$ [redacted Tier 0] ilimitado
  - **`gold_pro`** (Premium) — 15-19% comissão + parcelamento sem juros até 12x + destaque busca
- **Frete (logistics)**:
  - **`me1` (Custom)** — vendedor contrata Correios/transportadora própria
  - **`me2` Coletas/Places/Flex** — Mercado Envios coleta no vendedor, ML banca frete grátis se elegível
  - **`me2` Full** — estoque consignado no CD ML, fulfillment ML inteiro, fee adicional armazenagem
  - **`drop_off`** — vendedor entrega em agência Correios
- **Taxa ML** (2026): 10-19% per categoria + custo fixo R$ [redacted Tier 0]-7 produtos baixo valor (a partir de R$ [redacted Tier 0] isento custo fixo no Full)
- **Garantia ML** (Mercado Pago Protege) — automática 90 dias, vendedor paga se aprovada cliente
- **Reputação** (5 cores: verde escuro → vermelho) — calculada via SLA cancelamentos + atrasos + reclamações + entregas no prazo
- **Disputa cliente** — janela 30d após entrega abrir reclamação; workflow `mediation` → `claim` → resolução
- **Mercado Pago split** — vendedor recebe `money_release_date` D+0 (reputação verde) até D+14 (reputação baixa), até 60d em casos extremos

### 2.2 Shopee Brasil (prioridade #2 — ~20-25% volume)

- **API**: [open.shopee.com](https://open.shopee.com) — Open Platform v2
- **OAuth2**: `partner_id` + `shop_id` + `signature` HMAC-SHA256
- **Webhooks**: events `order.tracking_update`, `order.status_update`, `item.update`
- **Taxas Shopee BR** (2026 estimativa): ~6-10% comissão por venda + R$ [redacted Tier 0] frete subsidiado (Shopee absorve maior parte)
- **Frete subsidiado** — Shopee paga frete pra cliente em produtos elegíveis; vendedor paga taxa logística menor
- **Particularidade BR**: programa Shopee Garantida (similar Mercado Pago Protege) + cashback Shopee Coins
- **Categorias**: 28 top-level categorias, atributos variáveis

### 2.3 Amazon Brasil (prioridade #3 — ~5-10% volume, margem maior)

- **API**: [SP-API](https://developer-docs.amazon.com/sp-api) — Selling Partner API REST
- **OAuth2**: LWA (Login with Amazon) + IAM role + signed AWS Sig V4
- **Endpoints chave**: `/orders/v0/orders`, `/listings/2021-08-01/items`, `/fba/inbound/v0/shipments`, `/reports/2021-06-30/reports`
- **Particularidades BR**: marketplace ID `A2Q3Y263D00KWC` (BR), endpoint `sellingpartnerapi-na.amazon.com`
- **Taxas Amazon BR**: ~10-15% comissão por categoria + tarifa FBA se Fulfillment by Amazon
- **Notas fiscais**: Amazon BR exige NFe pra emissor PJ — `getInvoice` API recupera dados pra vendedor emitir
- **Tamanho mercado**: menor que ML mas margem maior (cliente classe A/B)

### 2.4 Magalu Hub + Americanas + outros (prioridade #4 — fase 4+)

- Driver pattern adapter — schema agnóstico permite plugar novos marketplaces sem mudar core (`mkt_marketplaces` table como catálogo de drivers).

## 3. Schema proposto

> Multi-tenant Tier 0 IRREVOGÁVEL — TODA tabela tem `business_id` indexado + FK + global scope ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)).

### 3.1 `mkt_marketplaces` (catálogo público — sem business_id)

```sql
id, key VARCHAR(50) UNIQUE NOT NULL,  -- 'mercado_livre', 'shopee', 'amazon_br', 'magalu', 'americanas'
nome VARCHAR(255),
driver_class VARCHAR(255),  -- 'App\Marketplaces\Drivers\MercadoLivreDriver'
api_base_url VARCHAR(500),
oauth_authorize_url VARCHAR(500),
oauth_token_url VARCHAR(500),
docs_url VARCHAR(500),
ativo BOOL DEFAULT 1,
created_at, updated_at
```

Seed inicial: `mercado_livre` ✅, `shopee` ✅, `amazon_br` ✅, `magalu` 🟡 (fase 4), `americanas` 🟡 (fase 4).

### 3.2 `mkt_account_credentials` (per-business — Tier 0)

```sql
id, business_id INT NOT NULL INDEX FK,
marketplace_id INT NOT NULL FK mkt_marketplaces,
seller_id_external VARCHAR(255),  -- ID do vendedor no marketplace (ML user_id, Shopee shop_id, Amazon merchant_id)
seller_nickname VARCHAR(255),
access_token TEXT ENCRYPTED,  -- via Laravel Crypt::encrypt
refresh_token TEXT ENCRYPTED,
access_token_expires_at DATETIME,
refresh_token_expires_at DATETIME,
oauth_state JSON,  -- partner_id, shop_id, scopes, etc per-marketplace
webhook_secret VARCHAR(255),  -- segredo HMAC validação webhooks
last_sync_at DATETIME NULL,
last_refresh_at DATETIME NULL,
status ENUM('active', 'token_expired', 'revoked', 'suspended') DEFAULT 'active',
created_at, updated_at,
UNIQUE (business_id, marketplace_id, seller_id_external)
```

Per ADR 0117 padrão multi-canal — múltiplas contas mesmo marketplace per business (ex: Larissa pode ter 2 ML accounts pra brands diferentes).

### 3.3 `mkt_listings` (anúncios)

```sql
id, business_id INT NOT NULL INDEX FK,
account_id INT NOT NULL FK mkt_account_credentials,
marketplace_id INT NOT NULL FK mkt_marketplaces,
product_id INT NOT NULL FK products (UltimatePOS),
variation_id INT NULL FK variations,
item_id_external VARCHAR(255) NOT NULL,  -- MLB1234567890, Shopee item_id, Amazon ASIN
title VARCHAR(500),
status ENUM('active', 'paused', 'closed', 'under_review', 'rejected'),
listing_type VARCHAR(50),  -- 'gold_pro', 'gold_special' (ML)
price_marketplace DECIMAL(15,4),
stock_marketplace INT,
category_external VARCHAR(100),
fee_estimate_pct DECIMAL(5,2),  -- 14.5%
permalink VARCHAR(500),
attributes JSON,  -- atributos obrigatórios categoria
last_sync_at DATETIME,
sync_error TEXT NULL,
created_at, updated_at,
INDEX (business_id, marketplace_id, status),
INDEX (business_id, product_id)
```

### 3.4 `mkt_orders` (bridge marketplace ↔ UltimatePOS transactions)

```sql
id, business_id INT NOT NULL INDEX FK,
account_id INT NOT NULL FK,
marketplace_id INT NOT NULL FK,
order_id_external VARCHAR(255) NOT NULL,  -- ML order_id, Shopee ordersn, Amazon AmazonOrderId
transaction_id INT NULL FK transactions,  -- vinculado ao registro UltimatePOS (criado por Listener)
status_external VARCHAR(100),  -- 'paid', 'shipped', 'delivered', 'cancelled' (varia per marketplace)
buyer_external JSON,  -- {nickname, email_hash, phone_hash} — PII REDACTED, hash em PR/log
total_amount DECIMAL(15,4),
total_fee DECIMAL(15,4),  -- taxa marketplace
shipping_cost DECIMAL(15,4),
shipping_type VARCHAR(50),  -- 'me1', 'me2', 'me2_full', 'fba'
shipping_paid_by ENUM('seller', 'buyer', 'marketplace'),
payment_method VARCHAR(50),  -- 'mercado_pago', 'credit_card', 'pix', 'boleto'
date_created_external DATETIME,
date_closed_external DATETIME NULL,
money_release_date DATETIME NULL,  -- ML: quando vendedor recebe split
margin_real DECIMAL(15,4) GENERATED,  -- total - fee - shipping_cost - cost_of_goods
raw_payload JSON,  -- snapshot completo webhook (audit trail)
processed_at DATETIME,
created_at, updated_at,
UNIQUE (business_id, marketplace_id, order_id_external),
INDEX (business_id, status_external),
INDEX (transaction_id)
```

### 3.5 `mkt_shipments`

```sql
id, business_id INT NOT NULL INDEX FK,
order_id INT NOT NULL FK mkt_orders,
shipment_id_external VARCHAR(255),
tracking_code VARCHAR(255),
tracking_url VARCHAR(500),
status_external VARCHAR(100),  -- 'ready_to_ship', 'shipped', 'delivered', 'returned'
status_correios VARCHAR(100) NULL,  -- 'OBJETO POSTADO', 'EM TRÂNSITO', etc
last_correios_event_at DATETIME NULL,
estimated_delivery_at DATETIME NULL,
delivered_at DATETIME NULL,
created_at, updated_at,
INDEX (business_id, status_external),
INDEX (tracking_code)
```

### 3.6 `mkt_webhook_log` (append-only audit)

```sql
id, business_id INT NOT NULL INDEX FK,
marketplace_id INT NOT NULL FK,
topic VARCHAR(100),  -- 'orders_v2', 'items', 'claims', etc
resource_external VARCHAR(500),  -- '/orders/12345'
payload JSON,
signature_valid BOOL,
processed_at DATETIME NULL,
processing_status ENUM('pending', 'processed', 'failed', 'skipped'),
error_message TEXT NULL,
attempts INT DEFAULT 0,
received_at DATETIME,
INDEX (business_id, marketplace_id, processing_status),
INDEX (received_at)
```

**Append-only** ([ADR 0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md) Pilar 4 audit trail) — sem UPDATE/DELETE; correção via novo registro `superseded_by`.

### 3.7 `mkt_disputes` (reclamações)

```sql
id, business_id INT NOT NULL INDEX FK,
order_id INT NOT NULL FK mkt_orders,
dispute_id_external VARCHAR(255),
type ENUM('claim', 'mediation', 'return', 'cancellation_request'),
reason VARCHAR(255),
reason_detail TEXT,
status ENUM('opened', 'in_analysis', 'resolved_buyer', 'resolved_seller', 'cancelled'),
amount_disputed DECIMAL(15,4),
evidence_uploaded JSON,  -- {file_path, type, uploaded_at}
opened_at DATETIME,
resolved_at DATETIME NULL,
resolution_text TEXT,
created_at, updated_at,
INDEX (business_id, status),
INDEX (order_id)
```

### 3.8 `mkt_pricing_rules` (preço dinâmico per marketplace)

```sql
id, business_id INT NOT NULL INDEX FK,
account_id INT NOT NULL FK,
listing_id INT NULL FK,  -- NULL = aplica a todos
product_id INT NULL FK,
markup_pct DECIMAL(6,2),  -- 35%
price_floor DECIMAL(15,4),  -- mínimo aceitável
price_ceiling DECIMAL(15,4) NULL,
auto_adjust_competitor BOOL DEFAULT FALSE,  -- copilot pricing (fase 5)
active BOOL DEFAULT 1,
created_at, updated_at
```

### 3.9 `mkt_reputation_snapshots` (histórico reputação ML)

```sql
id, business_id INT NOT NULL INDEX FK,
account_id INT NOT NULL FK,
level_external VARCHAR(50),  -- '5_green', '4_light_green', '3_yellow', '2_orange', '1_red'
level_normalized INT,  -- 1-5 (5 = melhor)
cancellations_pct DECIMAL(5,2),
claims_pct DECIMAL(5,2),
delayed_handling_pct DECIMAL(5,2),
snapshot_payload JSON,
captured_at DATETIME,
INDEX (business_id, account_id, captured_at)
```

## 4. FSM stages Sells novos (marketplace path)

Reusa fundação canônica [ADR 0143](../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md). Duas opções:

**Opção A (preferida)** — Adicionar processo seed `venda_marketplace` paralelo ao `venda_com_producao`:

```
pedido_ml_recebido (initial via webhook) → aguardando_envio_ml →
enviado_ml → entregue_ml → completed (T)

Laterais:
  disputa_aberta_ml → disputa_em_analise →
    disputa_aprovada_cliente (T) [trigger CancelarVendaCascade + estorno ML]
    | disputa_aprovada_vendedor (volta pra entregue_ml)
  cancelado_ml_cliente (T) [janela ML antes envio]
  cancelado_ml_atraso_sla (T) [ML cancelou por SLA — sanção reputação]
  reembolso_solicitado → reembolso_aprovado (T)
```

**Opção B** — Adicionar stages `pedido_ml_recebido` ao processo existente `venda_com_producao` quando produto requer produção (impressão sob demanda ComVis vendida em ML).

**Decisão D2 (ADR proposal)**: começar Opção A (paralelo) — simpler, menos risco regressão pipeline canon ROTA LIVRE. Opção B vira US-MKT-020 fase 5 sob demanda real.

Actions críticas (`is_critical: true` — RBAC obrigatório ADR 0143):
- `marcar_enviado_ml` (🔒 + emite NFe automática)
- `confirmar_entrega_ml` (🔒)
- `aceitar_disputa` (🔒 + CancelarVendaCascade + RefundCobrancaJob)
- `defender_disputa` (🔒 + upload evidências)
- `cancelar_pedido_ml` (🔒)

## 5. CFOP fiscal marketplace

Mapping inicial sugerido (decisão final = D5 do ADR proposal):

| Operação | CFOP | Justificativa |
|---|---|---|
| Venda peça/produto direta marketplace (vendedor envia) — same UF | **5102** | venda mercadoria adquirida terceiros, mesma UF |
| Venda peça/produto direta marketplace — outra UF | **6102** | mesmo, interestadual |
| Venda intermediada ML (operation_type=OLSS Mercado Livre) — same UF | **5949** | "outra saída" — Portaria CAT SP Nº 59 06/07/2018 |
| Venda intermediada ML — outra UF | **6949** | mesmo, interestadual |
| Remessa estoque pra ML Full (consignação) — same UF | **5910** | remessa em consignação |
| Remessa estoque pra ML Full — outra UF (CD ML em SP→) | **6910** | mesmo, interestadual |
| Retorno mercadoria ML Full não vendida | **1411** / **2411** | retorno simbólico consignação |

**Decisão D5**: per-categoria automático (driver `MercadoLivreCFOPResolver`) com override manual per-business. Wagner aprova mapping antes ativar.

## 6. Sync rastreamento Correios → marketplace

**Cron job** `marketplaces:sync-shipments` (frequência adaptativa — 30min em pedidos recentes, 6h em pedidos >7d):

1. Pra cada `mkt_shipments.status_external NOT IN ('delivered', 'returned')`:
   - Consulta API ML/Shopee/Amazon nativa (preferencial — eles já fazem proxy Correios)
   - Fallback API Correios pública (https://www2.correios.com.br/sistemas/rastreamento/) se marketplace não retorna
2. Update `status_correios` + `last_correios_event_at`
3. Se `status_external == 'delivered'`:
   - Trigger FSM action `confirmar_entrega_ml` via `ExecuteStageActionService`
   - Listener `EntregaConfirmadaListener` emite NFe se ainda não emitida + dispara `NotificarClienteEntregueJob`
4. SLA alert: se `enviado_ml > 10 dias úteis sem delivered` → alerta atendente (risco sanção ML reputação)

## 7. Disputa workflow

Webhook ML `claims` topic dispara:

1. `WebhookController` valida HMAC + idempotency-key
2. Cria `mkt_disputes` row + linka `mkt_orders.dispute_id`
3. Trigger FSM action `disputa_aberta_ml` (stage `disputa_em_analise`)
4. Job `NotificarAtendenteDisputaJob` → WhatsApp/email user com role `marketplace.disputas.atender`
5. UI Page `Marketplaces/Disputas/Show.tsx`:
   - Mostra payload claim + histórico mensagens
   - Botões: "Investigar" / "Upload evidência (foto/NF/tracking)" / "Aceitar reembolso cliente" / "Defender"
   - Permission `marketplaces.disputa.{view,respond,accept,defend}`
6. Workflow:
   - `defender_disputa` → POST `/post-purchase/v1/claims/{id}/evidences` ML
   - `aceitar_disputa` → POST `/claims/{id}/refund` + side-effect `CancelarVendaCascade` ([ADR 0143](../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md)) + libera estoque
7. ML decide D+5 a D+15 → webhook resolução → atualiza `mkt_disputes.status` + FSM stage final

## 8. User Stories (US-MKT-001..025)

> Convenção: **P0** = bloqueia 1ª piloto · **P1** = competitivo vs Tiny/Bling · **P2** = diferencial · **P3** = backlog/feature-wish.
> Estimates IA-pair fator 10x ([ADR 0106](../../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md)) com margem 2x.

### US-MKT-001 · Schema fundação 9 tabelas + Models global scope — **P0** · 4h

Migrations + Models todas com `business_id` + `HasBusinessScope` ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)) + Pest isolation biz=1 vs biz=99 ([ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md)).

### US-MKT-002 · Catálogo `mkt_marketplaces` seed 3 drivers — **P0** · 2h

Seeder com Mercado Livre + Shopee + Amazon BR. UI admin superadmin pra ativar/desativar.

### US-MKT-003 · OAuth2 Mercado Livre — authorization code flow + refresh token — **P0** · 6h

- Page `Marketplaces/Connect.tsx` — botão "Conectar Mercado Livre"
- Redirect → `https://auth.mercadolibre.com.br/authorization?response_type=code&client_id={app_id}&redirect_uri={callback}`
- Callback `/marketplaces/oauth/callback/mercado_livre` troca code → tokens
- Persiste em `mkt_account_credentials` (encrypted)
- Job `RefreshTokensJob` daily 5h BRT — refresh tokens próximos expiração
- Permission `marketplaces.account.{connect,disconnect,view}`

### US-MKT-004 · OAuth2 Shopee + Amazon BR — **P1** · 6h

Driver pattern: classe `MarketplaceDriver` abstrata + `MercadoLivreDriver`, `ShopeeDriver`, `AmazonBrDriver`.

### US-MKT-005 · Importar 1 anúncio manual ML (proof of concept) — **P0** · 5h

- UI `Marketplaces/Listings/Import.tsx`: cola `MLB1234567890` ou `permalink`
- Service `MercadoLivreItemImporter` busca via API + mapeia pra produto UltimatePOS
- Pest: importar item simula sucesso + mismatch produto

### US-MKT-006 · Webhook receiver ML orders_v2 + idempotency-key — **P0** · 5h

- Rota pública `POST /webhooks/marketplace/{marketplace_key}` (sem auth Laravel — HMAC valida)
- Middleware `ValidateMarketplaceWebhookSignature` valida HMAC SHA256 com `webhook_secret` por account
- Idempotency-key derivada de `(marketplace_id, resource, payload_hash)` → previne reprocessing
- Persiste em `mkt_webhook_log` (append-only)
- Dispatch Job `ProcessMarketplaceWebhookJob` async

### US-MKT-007 · Pedido ML → cria `mkt_orders` + transaction UltimatePOS + FSM stage initial — **P0** · 8h

- Job `ProcessMercadoLivreOrderJob` lê pedido ML completo + buyer + items + shipping
- Cria `Transaction` UltimatePOS + `TransactionSellLines` + `Contact` (buyer com email/phone hash — PII redacted em log)
- Linka `mkt_orders.transaction_id`
- Inicia FSM `venda_marketplace` stage `pedido_ml_recebido` (ADR 0143)
- Pest: webhook payload sample → ordem criada com 5 linhas + tx vinculada + stage correto

### US-MKT-008 · UI Page `Marketplaces/Orders/Index.tsx` (Cockpit V2) — **P0** · 6h

- Lista pedidos com filtros (marketplace, status, data, valor)
- Drawer `OrderSheet` com timeline FSM + items + buyer + shipping + dispute (se houver)
- Bulk actions: "Emitir NFe" / "Imprimir etiquetas" / "Marcar enviado"

### US-MKT-009 · NFe automática ao emitir → CFOP marketplace resolver + dispatch SEFAZ — **P0** · 7h

- Listener `OrderMarketplaceFaturadoListener` no event FSM action `emitir_nfe`
- Service `MarketplaceCFOPResolver` aplica mapping §5 (per-categoria + override business)
- Reusa [Modules/NfeBrasil US-NFE-002](../NfeBrasil/SPEC.md) pra emissão
- NFe enviada XML pro ML via `POST /orders/{id}/invoices` (ML aceita upload)
- Pest: pedido paid → emitir NFe → cstat 100 + ML recebe XML

### US-MKT-010 · Sync rastreamento Correios + auto-fechamento — **P0** · 6h

§6 implementação. Cron `marketplaces:sync-shipments` Centrifugo realtime push frontend ao mudar status.

### US-MKT-011 · Sync estoque oimpresso → ML (one-way push) — **P1** · 8h

- Event listener `StockAdjusted` (UltimatePOS) → dispatch `SyncListingStockJob`
- PUT `/items/{id}` ML com novo stock + price
- Debounce 60s (evita burst rate limit) — fila Redis com lock per item_id
- Pest: ajuste estoque → ML stock atualiza em <2min

### US-MKT-012 · Sync estoque ML → oimpresso (one-way pull via webhook) — **P1** · 4h

Webhook `items` topic dispara `SyncListingFromMarketplaceJob` — usa em estoque Full ML (consignação) onde ML é fonte verdade.

### US-MKT-013 · Anúncio em lote (bulk create) — **P1** · 10h

- Page `Marketplaces/Listings/Bulk.tsx` — seleciona N produtos UltimatePOS + categoria ML + atributos comuns + listing_type
- Service `BulkListingCreator` com chunks 50 itens (rate limit safe)
- Status report: criados / falhados / motivo per item
- Pest: 100 produtos → ≥95 anúncios criados

### US-MKT-014 · Disputa workflow completo — **P1** · 12h

§7 implementação. UI + permissions + upload evidência + RefundCobrancaJob integração.

### US-MKT-015 · Reconciliação financeira Mercado Pago split D+X — **P1** · 8h

- Pedido ML → cria `transaction_payment` UltimatePOS com `expected_date = money_release_date` + status `pending`
- Cron `marketplaces:reconcile-payouts` daily — confirma payout Mercado Pago via API → marca payment `paid`
- Dashboard "Recebíveis Pendentes Marketplaces" mostra previsão fluxo caixa D+1, D+7, D+14, D+30
- Reusa [Modules/Financeiro](../Financeiro/) AR

### US-MKT-016 · Reputação ML monitoring + alerta SLA — **P1** · 5h

- Cron `marketplaces:capture-reputation` daily → snapshot em `mkt_reputation_snapshots`
- Alerta WhatsApp/email se `level_normalized` cai 1+ nível ou `cancellations_pct > 3%`
- UI Page `Marketplaces/Dashboard.tsx` mostra trend 90 dias

### US-MKT-017 · Pricing rules per marketplace (markup diferenciado) — **P1** · 6h

§3.8 implementação. Calculadora `MarketplacePricingService` aplica markup_pct sobre custo produto + frete embutido.

### US-MKT-018 · Jana tool `marketplaces.consulta` (relatórios IA) — **P2** · 4h

Tool: "quantos pedidos ML hoje?", "qual SKU mais vendido ML mês?", "reputação ML atual?". Reusa [Modules/Jana](../../../Modules/Jana) tool pattern.

### US-MKT-019 · Etiquetas envio ML Coletas + integração Correios — **P2** · 6h

GET `/shipments/{id}/label` ML retorna PDF etiqueta — print direto + Bulk print 20 etiquetas/página.

### US-MKT-020 · Pipeline produção sob demanda marketplace (ComVis) — **P2** · 8h

Pedido ComVis vendido em ML → FSM `venda_com_producao` (ADR 0143) com stage `pedido_ml_recebido` injetado no início. Casos: cliente compra banner personalizado via ML, oimpresso recebe arte, produz, envia.

### US-MKT-021 · Shopee orders webhook + NFe (mesmo pattern ML) — **P2** · 10h

Implementação completa Shopee — driver, OAuth, webhook, NFe.

### US-MKT-022 · Amazon BR orders webhook + NFe (SP-API) — **P2** · 12h

Idem Amazon — IAM role + LWA + signed requests AWS Sig V4.

### US-MKT-023 · Pricing dinâmico copilot competitor watch — **P3** · 14h

Cron monitora preço concorrentes via ML search API + sugere ajuste preço (Jana). PolicyEngine `REQUIRE_HUMAN_REVIEW` antes aplicar.

### US-MKT-024 · Analytics avançado margem real (CMV + frete + taxa + tributos) — **P3** · 10h

Dashboard margem por SKU/categoria/marketplace + DRE marketplace agregado.

### US-MKT-025 · Magalu Hub + Americanas drivers (4º + 5º marketplaces) — **P3** · 16h

Sob demanda sinal cliente — driver pattern facilita adição.

## 9. Concorrentes (research consolidada §discovery)

| Concorrente | Pricing | Forte | Calcanhar oimpresso pode bater |
|---|---|---|---|
| **Tiny (Olist)** | R$ [redacted Tier 0]-639/m | 40+ marketplaces nativos + 100 integrações + NFe + reputação | Multi-tenant Tier 0 ausente; sem IA conversacional; vertical ausente (raso) |
| **Bling** | R$ [redacted Tier 0]/m base + tiers per pedidos | 250+ marketplaces + base instalada 300k+ users | Bugs sync estoque alto volume reportados; UI envelhecida; sem multi-tenant Tier 0; sem IA |
| **Conta Azul** | R$ [redacted Tier 0]/m start | Financeiro forte + integração contador | Marketplace raso — só ML + 1-2 outros; sem multi-tenant Tier 0 |
| **Olist** | Pricing premium | AI pricing + logística integrada + ecosystem | Foco grandes sellers; sem vertical depth |
| **MagaluHub** | Free (cativo Magalu) | Integração nativa Magalu + Casas Bahia + Extra | Lock-in Magalu; weak outros marketplaces |
| **eNotas** | per NFe | NFe automatizada multi-marketplace | Só fiscal, não ERP completo |
| **UpSeller** | Free omni-channel | Free + omni-channel BR | Engenharia menor escala; sem IA |
| **Anymarket / Lexos / Ideris** | Enterprise R$ [redacted Tier 0]k+/m | Hub robusto enterprise | Caro, complexo, overkill SMB |

**Wedge oimpresso (3 frases):**
> *"O ERP modular que integra Mercado Livre / Shopee / Amazon SEM tirar você do seu vertical (vestuário/auto/comvisual), responde pelo WhatsApp `quantos pedidos ML hoje?` via Jana IA, e mantém multi-tenant Tier 0 (sua tabela não vaza pra outro cliente como acontece no Bling/Tiny multi-empresa)."*

## 10. Riscos top

1. **Tiny/Bling cobrem 95% do que mercado pede** — construir Marketplaces (planejado — não existe) sem sinal qualificado é desperdício. ADR 0105 enforcement crítico aqui
2. **API ML mudanças frequentes** — Mercado Livre faz 5-10 breaking changes/ano. Manter driver atualizado tem custo recorrente
3. **Rate limit 1500/min mais agressivo do que parece** — bulk operations precisam queue + debounce; mass sync 5000 produtos em 1 click = bloqueio app
4. **Mercado Pago split D+0 a D+60 cria descasamento caixa** — financeiro precisa modelar "recebível futuro garantido" sem inflar AR. Reconciliação errada gera relatório errado
5. **CFOP marketplace ambiguo** — Portaria CAT SP Nº 59 06/07/2018 ainda gera dúvida contador. Risco multa SEFAZ se errado
6. **OAuth refresh token expira 90d se sem rotação** — perder token = cliente reauthorize manualmente = atrito UX
7. **Webhook delivery não-garantido** — ML/Shopee podem perder webhooks; fallback poll necessário (custo rate limit)
8. **NFe automática + cancelamento ML** — se ML cancela pedido D+1 mas NFe emitida D+0, precisa cancelar NFe SEFAZ (24h janela) — caso edge ADR 0143 §CancelarVendaCascade

## 11. Pré-requisitos pra ATIVAR (mudar status pra `em_construcao`)

### 11.1 Sinal qualificado de mercado ([ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md))

**Pelo menos 1 dos 3 cenários:**

1. **Larissa (ROTA LIVRE biz=4) confirma** vender ou querer vender em ML/Shopee — Wagner pergunta em call dedicada (Cenário preferido — Larissa = 99% volume, sinal forte)
2. **1+ OfficeImpresso saudável** (Vargas/Extreme/Gold/Mhundo/Produart) reporta "vendo ML, preciso integrar com NFe automática" — pacote pioneer
3. **3+ outreach inbound** em 90d perguntando "oimpresso integra com ML?"

### 11.2 Features mínimas validadas (paridade competitiva vs Tiny/Bling)

7 capacidades core funcionam end-to-end em homologação antes 1º cliente:

1. US-MKT-001 (schema), US-MKT-002 (catálogo), US-MKT-003 (OAuth ML)
2. US-MKT-005 (importar anúncio manual)
3. US-MKT-006 (webhook receiver)
4. US-MKT-007 (pedido → transaction + FSM stage)
5. US-MKT-008 (UI Orders)
6. US-MKT-009 (NFe automática CFOP)
7. US-MKT-010 (sync rastreamento Correios)

### 11.3 Capacidade time

WIP atual (~Q2/26): 5 pessoas com Vestuario live, ComVis em construção, OficinaAuto V0, MWART Financeiro. Marketplaces (planejado — não existe) ativa **só após** ComVis ter 1ª piloto verde + sinal Larissa/OfficeImpresso (Q4/26 estimado).

### 11.4 ADR de ativação

Quando pré-requisitos satisfeitos, abrir ADR canon `Marketplaces-ativacao-cross-vertical` com:
- evidência sinal qualificado (call Larissa transcript ou contrato OfficeImpresso)
- evidência 7 features mínimas verde (Pest + smoke real)
- aprovação Wagner [W] + revisão Felipe [F]
- mudança SPEC `status: feature-wish` → `status: em_construcao`
- criação batch tasks no MCP via `tasks-create` ([ADR 0070](../../decisions/0070-jira-style-task-management-current-md-removed.md) — não markdown)

## 12. Pricing tier sugerido (calibração pendente)

> Add-on cross-vertical sobre tier base oimpresso ([ADR 0121](../../decisions/0121-oimpresso-modular-especializado-por-vertical.md) Princípio P5).

| Tier add-on | Preço/m | Inclui | Posição vs Tiny/Bling |
|---|---|---|---|
| **Marketplace Start** | **+R$ [redacted Tier 0]/m** | 1 marketplace (ML), 200 pedidos/m, NFe auto, sync rastreio | Match Bling Cobalt |
| **Marketplace Pro** | **+R$ [redacted Tier 0]/m** | 3 marketplaces (ML+Shopee+Amazon), 2000 pedidos/m, ilim anúncios, Jana IA tool, dashboard margem | Match Tiny Construa |
| **Marketplace Enterprise** | **+R$ [redacted Tier 0]/m** | Marketplaces ilim, pedidos ilim, copilot pricing, multi-account per marketplace, success dedicado | Premium vs Tiny Domine R$ [redacted Tier 0]/m |
| **Setup** | **R$ [redacted Tier 0] pioneer** / R$ [redacted Tier 0]-3.000 regular | — | Pioneer primeiros 2 clientes |

Calibração final via research pós-sinal (não antes — viola ADR 0105).

## 13. Anti-padrões — o que NÃO fazer

1. ❌ **Construir SEM sinal qualificado §11.1** — viola ADR 0105
2. ❌ **Single tenant credentials** — cada `business_id` tem credenciais próprias (Tier 0 IRREVOGÁVEL [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md))
3. ❌ **Mass UPDATE estoque sem queue + debounce** — burst >1500 req/min = ban app ML
4. ❌ **Webhook receiver sem HMAC validation** — qualquer um pode forjar pedidos
5. ❌ **PII real buyer em PR/commit/log** — email + phone DEVEM ser hash (`PiiRedactor` ou SHA256 truncado)
6. ❌ **Mass updates `current_stage_id`** bypass FSM gateway ([ADR 0143](../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md)) — `fsm:scan-drift` detecta, mas cause root é evitar
7. ❌ **CFOP genérico 5949 pra tudo** — risco SEFAZ multa; usar resolver per-operação
8. ❌ **NFe emitida sem aguardar `payment_status=paid` ML** — comum cliente cancelar antes pagar; risco emitir NFe sem venda real
9. ❌ **Sync bi-direcional sem debounce** — loop infinito (ML → oimpresso → ML → oimpresso...)
10. ❌ **Esquecer rotacionar refresh_token** — 90d expira, perde acesso
11. ❌ **Smoke test biz=1 WR2 com credenciais ML real** — usar conta sandbox ML; biz=99 cross-tenant ([ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md) + feedback `test_biz_99_cross_tenant_convention`)
12. ❌ **Ativar Marketplaces antes de ComVis ter 1ª piloto verde** — viola WIP ([ADR 0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md) §5 SoC)
13. ❌ **Tratar marketplaces como Modules/<Vertical>** — é cross-vertical (vestuário, auto, autopeças, comvisual TODOS podem usar). Não confundir com Modules/Vestuario etc.

## 14. Decisões pendentes (resolver SE/QUANDO ativar) — ver ADR proposal

D1-D8 detalhadas em [proposals/drafts/marketplaces-modulo-cross-vertical.md](../../decisions/proposals/drafts/marketplaces-modulo-cross-vertical.md).

## 15. Referências

- ADR 0143 — FSM Pipeline canon LIVE prod (integração obrigatória)
- ADR 0121 — Modular especializado por vertical (módulo cross-vertical sancionado)
- ADR 0117 — Múltiplos canais per-business (pattern OAuth multi-account)
- ADR 0094 — Constituição v2 (princípios duros)
- ADR 0093 — Multi-tenant Tier 0 IRREVOGÁVEL
- ADR 0105 — Cliente como sinal qualificado (gatilho ativação)
- ADR 0106 — Recalibração velocidade fator 10x IA-pair
- ADR 0035 — Stack IA canônica (Jana tool integration)
- ADR 0011 — Alinhamento padrão Jana (referência módulos)
- ADR 0129 — FSM canônica (mãe da fundação)
- ADR 0119 — Migration Factory (aplicável se cliente migra Tiny → oimpresso)
- [SPEC NfeBrasil](../NfeBrasil/SPEC.md) — US-NFE-002 reusada
- [SPEC RecurringBilling](../RecurringBilling/SPEC.md) — US-RB-044 boleto-pago→NFe (parcial reuse)
- [SPEC Financeiro](../Financeiro/) — reconciliação Mercado Pago split AR
- [SPEC OficinaAuto](../OficinaAuto/SPEC.md) — template vertical
- [SPEC Autopecas](../Autopecas/SPEC.md) — template antecipatório feature-wish (mesmo lifecycle aplicável aqui)
- [MATRIZ-ROI.md](MATRIZ-ROI.md) — 25 features × ROI score
- [ROADMAP.md](ROADMAP.md) — 5 fases CONDICIONAL
- [RUNBOOK criar módulo](../Infra/RUNBOOK-criar-modulo.md)
- [Mercado Livre Developers](https://developers.mercadolivre.com.br)
- [Shopee Open Platform](https://open.shopee.com)
- [Amazon SP-API](https://developer-docs.amazon.com/sp-api)
- [Portaria CAT SP Nº 59 06/07/2018](https://legislacao.fazenda.sp.gov.br) — CFOP marketplace

---

**Última atualização:** 2026-05-12 — SPEC criada **antecipatória** sem sinal qualificado confirmado. Status `feature-wish` lifecycle `aguarda-sinal-qualificado`. Não codar até gatilho §11 satisfeito. Revisar trimestralmente — se 12 meses sem sinal, considerar arquivar como `historical` ([ADR 0095](../../decisions/0095-skills-tiers-convencao-interna.md) lifecycle).
