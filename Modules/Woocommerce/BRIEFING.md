# BRIEFING — Modules/Woocommerce

> Estado consolidado da capacidade. Atualizado por PR (skill `brief-update` Tier B).
> Última atualização: 2026-05-16 Wave 18 SATURATION.

## O que é

Integração unidirecional POS oimpresso → WooCommerce externo:
- **Sync categories**: POS taxonomy → WC categories
- **Sync products**: POS products + variations + stock → WC products (batched)
- **Sync orders**: WC orders → POS Transaction (Sells) — pull periódico
- **Reset**: cleanup local + (opcional) remoto, para reonboarding

Quando rodar: módulo opcional, ativado via subscription `woocommerce_module`. Cliente piloto
potencial: lojas vestuário/comunicação visual que já operam loja online WooCommerce.

## Status atual

- **Module Grade v3**: 78/100 → meta 88+ via Wave 18 SATURATION (D4 +11, D9 +5)
- **3 Services canon** extraídos (Wave 16) — Controllers thin, Service contém transaction + lógica
- **OTel observability** — 6 spans cobrindo todos os métodos públicos críticos
- **Health check command** — `woocommerce:health` cobre schema/DI/creds/last-sync
- **FSM canon (ADR 0143)**: N/A — estado vive no WooCommerce, oimpresso só espelha

## Diferenciais

- Sync incremental por `--new-products` (não re-sincroniza catálogo todo se não precisa)
- Reset reversível (logs em `woocommerce_sync_logs` permitem auditoria após cleanup)
- Health command **stateless** (não hit em API externa — zero custo, sem ban risk)
- OTel spans nomeados com prefix `woocommerce.*` (filtra fácil em collector CT 100)

## Gaps conhecidos

- `WoocommerceUtil` (1541 LOC legacy UltimatePOS) ainda monolito — Service envolve mas não substitui
- Sync orders ainda manual (Controller-triggered) — webhook WC → oimpresso TODO
- UI principal ainda Blade UltimatePOS — MWART para Inertia/React não iniciado

## Referências

- [ADR 0093](../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) Multi-tenant Tier 0
- [ADR 0094](../../memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md) Constituição v2
- [ADR 0155](../../memory/decisions/0155-module-grade-v3.md) Module Grade v3
- [Modules/Woocommerce/CHANGELOG.md](CHANGELOG.md)
