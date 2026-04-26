# SPEC — Modulo Woocommerce

> Status: legado UltimatePOS. Recomendacao: **manter** (pelo menos um cliente WR2 sincroniza com loja Woo).

## Proposito

Sincronizacao bidirecional entre UltimatePOS (estoque + pedidos) e
WooCommerce (loja online). Sync manual disparado pelo admin
(`/woocommerce/sync-*`) + 4 webhooks publicos para Woo notificar mudancas
de pedido em tempo real.

## Rotas

### Webhooks publicos (sem middleware web/auth — Woo HTTPS-bate-direto)

| Verbo | Rota | Acao |
|---|---|---|
| POST | `/webhook/order-created/{business_id}` | Cria sale a partir de order Woo |
| POST | `/webhook/order-updated/{business_id}` | Atualiza sale existente |
| POST | `/webhook/order-deleted/{business_id}` | Marca sale como deletada |
| POST | `/webhook/order-restored/{business_id}` | Reverte deletada |

Validacao: `WoocommerceWebhookController::isValidWebhookRequest()` checa
HMAC do header `X-WC-Webhook-Signature` contra `woocommerce_app_consumer_secret`.
Sem assinatura valida -> webhook ignora silenciosamente (nao retorna 500).

### Admin (`web, SetSessionData, auth, language, timezone, AdminSidebarMenu`, prefix `/woocommerce`)

| Verbo | Rota | Acao |
|---|---|---|
| GET | `/` | Dashboard |
| GET/POST | `/api-settings`, `/update-api-settings` | URL + consumer key/secret |
| GET | `/sync-categories`, `/sync-products`, `/sync-orders` | Sync triggers |
| POST | `/map-taxrates` | Mapeia taxas Woo -> POS |
| GET | `/sync-log`, `/view-sync-log`, `/get-log-details/{id}` | Audit |
| GET | `/reset-categories`, `/reset-products` | Limpa flags `woocommerce_*_id` |
| GET | `/install`, `/install/update`, `/install/uninstall` | Hooks |

## Sem FormRequests

`Modules/Woocommerce/Http/Requests/` esta vazio (`.gitkeep`). Validacao de
payload e feita inline com `$request->validate([...])` ou direto na lib
`automattic/woocommerce` (REST client).

## Riscos conhecidos

- Webhook nao reage com erro 4xx quando assinatura falha — Woo ficaria
  desligando o webhook ao perceber 4xx repetidos. Comportamento atual eh
  proposital (200 silencioso evita disable).
- Sync de produtos pode estourar `max_execution_time` em catalogos grandes;
  ainda nao foi movido pra job assincrono.

## Testes (este PR)

- `Modules/Woocommerce/Tests/Feature/WebhookValidationTest.php` — webhooks sem assinatura/payload malformado nao geram 500; rotas admin exigem auth.

## Pendencias

- Adicionar FormRequest pra `update-api-settings`.
- Mover `syncProducts` pra Job (estoque + delta de variations).
- Documentar mapeamento de taxa em `Modules/Woocommerce/SPEC-tax-mapping.md`.
