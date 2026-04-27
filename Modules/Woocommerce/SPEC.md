# Modules/Woocommerce — SPEC (resumo legado)

## Propósito

Bridge entre uma loja WooCommerce externa e o UltimatePOS. Sincroniza
categorias, produtos, taxas e pedidos (via webhooks reativos + sync
manual).

## Superfícies relevantes

### Webhooks (públicos, validação por HMAC-SHA256)

Quatro endpoints sem `auth` Laravel — são chamados pelo servidor Woo:

- `POST /webhook/order-created/{business_id}` → `WoocommerceWebhookController@orderCreated`
- `POST /webhook/order-updated/{business_id}` → `orderUpdated`
- `POST /webhook/order-deleted/{business_id}` → `orderDeleted`
- `POST /webhook/order-restored/{business_id}` → `orderRestored`

**Validação:** header `x-wc-webhook-signature` deve bater com
`base64(hmac_sha256(payload, business.woocommerce_wh_<oc|ou|od|or>_secret))`.
Falha de assinatura → log emergency, **não** processa o pedido.

### Admin (`web + auth + SetSessionData + AdminSidebarMenu`, prefixo `/woocommerce`)

- `GET /` → settings landing
- `GET /api-settings` + `POST /update-api-settings`
- `GET /sync-categories` / `sync-products` / `sync-orders`
- `GET /sync-log` + `view-sync-log` + `get-log-details/{id}`
- `POST /map-taxrates`
- `GET /reset-categories` / `reset-products`
- Install: `GET /install`, `/install/update`, `/install/uninstall`

## Riscos regressivos conhecidos

1. **CRÍTICO**: bypass do HMAC = qualquer um cria/altera sale em
   qualquer business. Cobertura via `WoocommerceWebhookTest`.
2. Mudança de schema em `business` (colunas `woocommerce_wh_*_secret`)
   quebra validação silenciosamente.
3. `business->owner->id` é assumido — se um business sem owner chegar,
   `Business::findOrFail` passa mas `owner->id` explode.

## Cobertura de testes (batch 7)

- `tests/Feature/Modules/Woocommerce/WoocommerceWebhookTest.php`

Filtro: `vendor/bin/pest --filter=Woocommerce`

## Recomendação

**MANTER.** Integração legítima e usada. Recomendações futuras (não
bloqueantes):

- Mover validação HMAC pra middleware dedicado `wc.webhook` para tirar
  duplicação dos 4 métodos.
- Hardening: rate limit nos webhooks e early-return 401 quando
  assinatura falha (hoje devolve 200 silencioso, o que mascara
  ataques).
- Suite de validação dos payloads (hoje confia no shape do Woo).
