---
module: Marketplaces
purpose: "Integração com marketplaces brasileiros (Mercado Livre primeiro; Shopee, Amazon e Magalu depois): o pedido do marketplace vira venda no núcleo com NF-e e rastreio, e anúncio e estoque ficam sincronizados. Cross-vertical. Ativado pela ADR 0439 em 2026-10-26."
migracao_ui: "n/a — módulo novo, nasce Inertia (sem Blade)"
contains:
  - "DataController"
  - "InstallController"
  - "ContasController — conectar a conta do vendedor no Mercado Livre por OAuth (US-MKT-003)"
  - "Catálogo global de marketplaces (mkt_marketplaces) e conta do vendedor por empresa com token cifrado (mkt_account_credentials)"
  - "Anúncios (mkt_listings), pedidos recebidos (mkt_orders) e log de webhook append-only (mkt_webhook_log) — US-MKT-001"
not_contains:
  - "Loja própria WordPress/WooCommerce → Modules/Woocommerce"
  - "Emissão de NF-e → Modules/NfeBrasil (chamado, não duplicado)"
  - "Venda e etapas da venda → núcleo (transactions + ExecuteStageActionService, ADR 0143)"
  - "Recebíveis do Mercado Pago → Modules/Financeiro"
depends_on:
  - NfeBrasil
  - Financeiro
owner: wagner
status: em construção
url_prefixes:
  - /marketplaces/install (instalação 1-clique)
  - /marketplaces/contas/mercado-livre/conectar e /marketplaces/oauth/callback/mercado-livre (US-MKT-003)
---

# Modules/Marketplaces

## Por que existe

[W] pediu em 2026-10-25 a integração com marketplaces (P0 do levantamento de lacunas frente à
concorrência). A [ADR 0439](../../decisions/0439-ativacao-modulo-marketplaces-mercado-livre-primeiro.md)
ativou o módulo e fechou as decisões D1–D8.

## Fronteira

O módulo é dono da conversa com o marketplace (token, anúncio, pedido, notificação). Venda, NF-e
e recebível pertencem ao núcleo, ao NfeBrasil e ao Financeiro, e são chamados, nunca duplicados.

## Referências

- [SPEC](SPEC.md) · [BRIEFING](BRIEFING.md)
- [ADR 0439](../../decisions/0439-ativacao-modulo-marketplaces-mercado-livre-primeiro.md) · [ADR 0093 — multi-tenant Tier 0](../../decisions/0093-multi-tenant-isolation-tier-0.md)
