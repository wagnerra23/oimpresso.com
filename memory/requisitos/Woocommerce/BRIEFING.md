# BRIEFING — Modules/Woocommerce

> **Estado:** 🟡 scaffold preservado, sem cliente ativo hoje, manutenção dormente | **Atualizado:** 2026-05-16 | **Owner:** sem owner ativo

## O que é

Integração entre oimpresso e lojas WooCommerce externas (WordPress + plugin WC) via REST API v3:
- **Outbound (oimpresso → Woo):** sync de produtos, categorias, tax rates, atributos, mídia
- **Inbound (Woo → oimpresso):** webhooks de pedido criado/atualizado criam Transactions tipo `sell` automaticamente

Herdado do UltimatePOS v6 — pacote `automattic/woocommerce` SDK + scaffold de controllers/views.

## Por que existe

WooCommerce é a plataforma de e-commerce mais usada no Brasil entre PMEs (lojistas que já tem WordPress + WooCommerce e querem ERP por trás). Módulo é arma comercial: oimpresso pode atender lojista WP sem ele trocar de site.

**Status hoje:** nenhum cliente piloto rodando WooCommerce sync em prod. ROTA LIVRE (vestuário) não usa. Mantido porque custo de manutenção dormente é baixo e remoção quebra scaffold de outros módulos.

## Capacidades hoje

- ✅ Configurar API (URL + consumer_key/secret criptografado)
- ✅ Sync produtos com flag `disable_woocommerce_sync` per-product
- ✅ Sync categorias + tax rates + variation_templates + mídia
- ✅ Sync log com diagnóstico de erro
- ✅ Webhook order-created (gera Transaction sell)
- ✅ Webhook order-updated (refund/cancel reflete)
- ✅ HMAC signature validation
- ✅ Permissões Spatie integradas (`add_woocommerce_permissions` migration)
- ✅ Notificação `SyncOrdersNotification` ao admin em fim de sync
- ✅ Multi-tenant via `business_id`

## Diferencial vs concorrentes

- Vs Bling/Tiny: também integram com Woo, mas via app marketplace pago — oimpresso traz nativo
- Vs SaaS específico (LWS Loja, Wbuy): oimpresso integra como módulo do próprio ERP, sem custo de assinatura adicional
- Vs sync manual CSV: tempo real via webhook

## Gaps reconhecidos

- 🟡 Sem cliente ativo — risco de bit-rot (migrations de 2018-2021 ainda OK; código não testado em Woo 8.x recente)
- 🟡 Não testado contra WooCommerce 8.x/9.x atuais (escrito pra Woo ~3.x-6.x)
- 🟡 Sem sync de cupons/coupons (P2 backlog se aparecer cliente)
- 🟡 Sem sync inverso de estoque (Woo → oimpresso) — só pedido vira venda
- 🟡 Blade não migrado MWART (uso dormente, sem prioridade)
- 🟡 Sem dashboard consolidado Woo (revenue/conversão) — usa UI Woo

## Estado de testes (Wave B)

- `Tests/Feature/MultiTenantIsolationTest.php` — garante que sync log não vaza cross-tenant
- `Tests/Feature/ScaffoldTest.php`
- `Tests/Feature/SmokeRoutesTest.php`

## Decisões relacionadas

- [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) — Multi-tenant Tier 0
- [ADR 0011](../../decisions/0011-alinhamento-padrao-jana.md) — Padrão modular
- [ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) — Cliente como sinal (sem cliente, sem investimento)

## Próximo passo sugerido

**Não investir até surgir cliente piloto.** Quando aparecer:
1. Smoke test contra Woo 9.x atual (sandbox local) — pode quebrar
2. Atualizar SDK `automattic/woocommerce` se necessário
3. Re-validar webhook HMAC contra payloads atuais
4. Ativar com cliente real biz>=10 (não usar biz=1/4 em prod)
