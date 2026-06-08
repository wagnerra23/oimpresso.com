# BRIEFING — Modules/ProductCatalogue

> Estado consolidado executivo (1 página) — atualizado por PR que toque o módulo. Skill `brief-update` Tier B regenera automaticamente.

## O que faz

**Catálogo público de produtos** compartilhável via QR code + URL pública (`/catalogue/{business_id}/{location_id}`). Cliente final acessa sem login, vê produtos disponíveis na location com preços, variations, combos e descontos ativos.

## Para quem

Qualquer business multi-tenant que queira expor catálogo sem app/loja online completa. Útil pra:
- Vendedor passando QR no balcão pro cliente ver opções
- WhatsApp/Instagram bio link
- Cardápio impresso com QR (Repair, Vestuario, ComunicacaoVisual)

## Diferenciais hoje

- ✅ **Zero Entities próprios** — reusa `App\Product`/`App\Business`/`App\Category`/`App\Discount` do core (SoC brutal [ADR 0094 §5](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md))
- ✅ **Multi-tenant Tier 0 herdado** — `App\Product` já tem `business_id` filtro no core
- ✅ **Suporta produtos complexos** — single + variable (com variations) + combo
- ✅ **Discount calculado em runtime** — filtra by `business_id` + `location_id` + janela temporal
- ✅ **QR code generation** integrada (admin gera QR pra location)
- ✅ **Padrão UltimatePOS Install/Uninstall** ([ADR 0011](../../decisions/0011-alinhamento-padrao-jana.md)) — herda `BaseModuleInstallController`

## Particularidade arquitetural

**Sem Entities próprios.** Diferente da maioria dos módulos (Jana, Repair, AssetManagement), ProductCatalogue é puro **VIEW agregadora** sobre Products do core. Cobertura multi-tenant é herdada automaticamente do `App\Product` (que já tem `business_id` global scope). Isso explica por que:

- Não tem pasta `Modules/ProductCatalogue/Entities/`
- Test de multi-tenant foca em **rotas públicas** (não em CRUD via Eloquent), validando que slug `business_id=1` não vaza catálogo de `business_id=99` no endpoint `/catalogue/...`
- Anti-enumeration: ID numérico per-business é guessable; mitigação atual = endpoint exige business_id explícito, mas slug amigável (US-PCAT-W01 backlog) reduziria fingerprinting

## Gaps conhecidos

- 🟡 Frontend Blade legacy (não migrou pra Inertia/React MWART)
- 🟡 `business_id` numérico exposto na URL pública (enumeration risk — mitigado por validação server-side mas UX ruim)
- 🟡 Sem rate limit no endpoint público — DoS possível
- 🔒 Sem slug amigável (feature wish)
- 🔒 Sem embed widget JS
- 🔒 Sem analytics

## Métricas

- 6 User Stories (todas ✅ done — núcleo legacy estável)
- 3 Controllers + **0 Entities** (caso especial)
- 2 Tests Feature (Wave B scaffold + Wave I-W public catalogue security)

## Próximos passos sugeridos (sem prazo — feature wish)

1. Slug amigável por business (UX + SEO + reduz enumeration)
2. Rate limit no `/catalogue/...` endpoint (Laravel `throttle` middleware)
3. Migrar `catalogue.index` pra Inertia/React quando MWART migrar Sells/Products

## Refs

- [SPEC.md](SPEC.md)
- [Modules/ProductCatalogue/](../../../Modules/ProductCatalogue/)
