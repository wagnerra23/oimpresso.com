---
module: ProductCatalogue
na_justified:
  D5: "ProductCatalogue é utilitário cross-business read-only sem cliente externo direto — todos businesses (Vestuario/ComunicacaoVisual/OficinaAuto) compartilham core `App\\Product` table do UltimatePOS. Não há cliente piloto especializado pra catálogo público — vertical-agnostic por design. Cliente sinal qualificado ([ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)) ainda não emergiu pra demandar especialização vertical; mantém-se núcleo comum ([ADR 0094 §SoC](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md) + [ADR 0121 modular especializado](../../decisions/0121-oimpresso-modular-especializado-por-vertical.md)). D5 (cliente piloto vertical) N/A justificado até sinal real."
  D6.b: "ProductCatalogue é catálogo público read-only — p99 OTel <500ms ainda não exportado (instrumentação OTel project-wide pendente). Performance dominada por queries simples em `App\\Product` com cache de imagens — sem otimização específica necessária no estado atual."
  D9.b: "ProductCatalogue é módulo read-only sem operações assíncronas — só renderização pública via Controller. Sem jobs/Horizon. failed_jobs N/A por design."
related_adrs: [0011, 0093, 0094, 0105, 0121, 0153, 0154, 0155, 0156]
---

# SPEC — Modules/ProductCatalogue

> Catálogo append-only de User Stories aprovadas pro módulo de catálogo público de produtos (compartilhável via QR code + URL pública). Núcleo herdado UltimatePOS v6 ([ADR 0011](../../decisions/0011-alinhamento-padrao-jana.md)) — **sem Entities/Models próprios**; reusa `App\Product`, `App\Business`, `App\BusinessLocation`, `App\Category`, `App\Discount` do core (cobertura multi-tenant herdada).

## Estado

- ✅ Em produção (legacy UltimatePOS, 3 Controllers, 0 Entities)
- ✅ Multi-tenant Tier 0 herdado do core (`App\Product` já tem `business_id` filtro)
- 🟡 Frontend Blade only — sem MWART/Inertia ainda
- ⏸️ Backlog feature-wish: Embed widget JS, slug amigável vs ID numérico

## Tabela de US

| ID | Título | Status | Owner | Notas |
|---|---|---|---|---|
| US-PCAT-001 | Catálogo público por business + location (`/catalogue/{business_id}/{location_id}`) | ✅ done | [W] | `ProductCatalogueController@index`, filtra `Product::where('business_id', $bid)` + `product_locations` |
| US-PCAT-002 | Detalhe de produto público (`/show-catalogue/{business_id}/{product_id}`) | ✅ done | [W] | `ProductCatalogueController@show`, suporta variations + combo + variable types |
| US-PCAT-003 | Geração de QR code do catálogo (admin) | ✅ done | [W] | `ProductCatalogueController@generateQr` em `/product-catalogue/catalogue-qr` |
| US-PCAT-004 | Install/uninstall via BaseModuleInstallController (padrão ADR 0011) | ✅ done | [W] | `InstallController` 3 rotas (index/install/uninstall/update) |
| US-PCAT-005 | Discount ativo aplicado no catálogo (priority desc, starts_at ≤ now ≤ ends_at) | ✅ done | [W] | `Discount::where('business_id', ...)->where('location_id', ...)->where('is_active', 1)` |
| US-PCAT-006 | Multi-tenant isolation public catalogue test (Tier 0) | ✅ done | [Claude] | `Tests/Feature/PublicCatalogueSecurityTest.php` — slug biz=1 não vaza biz=99 + anti-enumeration (Wave I-W 2026-05-16) |

## Backlog (feature wish — sem sinal qualificado ADR 0105)

- 🔒 US-PCAT-W01: Slug amigável por business (em vez de `business_id` numérico — UX + SEO)
- 🔒 US-PCAT-W02: Embed widget JS (`<script src="...">`) pra incorporar em site cliente
- 🔒 US-PCAT-W03: Compartilhamento WhatsApp/Instagram com OG tags otimizadas
- 🔒 US-PCAT-W04: Analytics de visualização (page views + produto mais clicado)
- 🔒 US-PCAT-W05: Migração Blade → Inertia/React (skill `mwart-process`)

## Models reusados (herdados core)

- `App\Product` — global scope `business_id` já existe (escopo Tier 0 herdado)
- `App\Business`, `App\BusinessLocation` — multi-tenant Tier 0 herdado
- `App\Category` — `Category::forDropdown($business_id, 'product')` filtro explícito
- `App\Discount` — filtro explícito por `business_id` + `location_id`
- `App\SellingPriceGroup` — herdado

## Decisão arquitetural

**Não criar Entities próprios em Modules/ProductCatalogue/Entities/** — catálogo é VIEW agregadora sobre Products do core. Adicionar Models duplicados violaria SoC brutal ([ADR 0094 §5](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md)). Quando feature exigir state próprio (ex: catalog_views analytics), criar Entity nova alinhada ADR 0011.

## D7 LGPD — privacidade e retenção (Wave 11 — 2026-05-16)

ProductCatalogue é catálogo público READ-ONLY — não captura PII de visitantes (sem form, sem checkout, sem comments). PII só aparece tangencialmente em logs de erro do Controller (ex: query params com identificadores) ou descrições de produtos custom (raro, mas possível).

| Eixo | Estado | Como |
|---|---|---|
| **PiiRedactor logs** | ✅ aplicado | `Modules\ProductCatalogue\Services\CatalogueLogger` envolve `Log::error()` em rotas públicas com `PiiRedactor::redactString()` antes de logar URL/payload. Aplica nos 3 controllers (index/show/generateQr). |
| **LogsActivity Models** | ✅ herdado core | `App\Product` (linha 11) e `App\Category` (linha 13) já usam `Spatie\Activitylog\Traits\LogsActivity` no core UltimatePOS — auditoria de mudança CRUD já gravada em `activity_log`. **NÃO duplicar trait** no módulo (proibição Tier 0: não modificar core UltimatePOS). |
| **Retention** | ✅ política | `Modules/ProductCatalogue/Config/retention.php` define `products_inactive_days = 1825` (5 anos pós soft-delete `App\Product` `is_inactive=1`) — alinhado CTN Art. 195 (fiscal 5 anos) + LGPD Art. 16 (eliminação após finalidade). Purge job placeholder (US futura). |
| **Anti-enumeration** | ✅ done | `Tests/Feature/PublicCatalogueSecurityTest.php` (US-PCAT-006 Wave I-W) cobre slug biz=1 não vaza biz=99 + 404 anti-enumeration. |
| **Embasamento legal** | LGPD Art. 7º, 16 + CTN Art. 195 | Catálogo público não armazena PII visitante. Activity log de admin já compreendido pelo core. |

Refs ADR: [0093](../../decisions/0093-multi-tenant-isolation-tier-0.md), [0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md), [0153](../../decisions/0153-modulos-vendor-cobertura-multi-tenant.md), [0154](../../decisions/0154-pii-redactor-aplicacao-modulos.md).

## Refs

- [Modules/ProductCatalogue/Http/Controllers/ProductCatalogueController.php](../../../Modules/ProductCatalogue/Http/Controllers/ProductCatalogueController.php)
- [Modules/ProductCatalogue/Services/CatalogueLogger.php](../../../Modules/ProductCatalogue/Services/CatalogueLogger.php) (Wave 11 D7 LGPD)
- [Modules/ProductCatalogue/Config/retention.php](../../../Modules/ProductCatalogue/Config/retention.php) (Wave 11 D7 LGPD)
- [Modules/ProductCatalogue/Routes/web.php](../../../Modules/ProductCatalogue/Routes/web.php)
- [ADR 0093 Multi-tenant Tier 0](../../decisions/0093-multi-tenant-isolation-tier-0.md)
- [ADR 0011 Alinhamento padrão Jana/Repair](../../decisions/0011-alinhamento-padrao-jana.md)
- [BRIEFING.md](BRIEFING.md)
