# CHANGELOG — Modules/ProductCatalogue

> Append-only. Mais novo no topo. Datas YYYY-MM-DD.

## [Wave 27 — Polish ≥90 D2/D9] — 2026-05-17

### Adicionado
- **`Tests/Feature/Wave27SaturationTest.php`** (8 cenários, 0.32s avg) — saturação polish ≥90:
  - D2 ArchitectureTest cumulativo (Wave 16 12 cenários + Wave 23 8 cenários ≥20)
  - D9 spans completos: 3 spans canon `product_catalogue.*` em 2 Services
  - D9 span attributes: `business_id` Tier 0 em todos os spans (defesa rota pública QR)
  - D9 OtelHelper preserva exception em `product_catalogue.*` (fail-loud)
  - D9 imports canon `App\Util\OtelHelper` (zero duplicação)
  - D2 module boundary: Services/Repository/Controller dentro `Modules\ProductCatalogue`
  - D6 HealthCommand `--detail` canon (NUNCA `--verbose` Symfony reserved)
  - D2 Controller Blade explicito — usa `view()` não `Inertia::render` (defer N/A pra catálogo QR público)

### Alterado
- **`Console/Commands/ProductCatalogueHealthCommand.php`** — adicionado flag `--detail` no signature (alinha `.claude/rules/commands.md` — NUNCA `--verbose`).

### Não alterado (intencional — já saturado)
- D4 Architecture (W16): Controller magro + 2 Services + Repository.
- D9.a OTel (W17): `buildIndexPayload`/`buildShowPayload`/`buildQrPayload` wrapped em `OtelHelper::spanBiz`.
- D7.c retention.php (W25): preservado.
- D6 defer: N/A — Controller usa Blade view() pra rota pública QR; `Inertia::defer` aplica só a páginas Inertia/React.

### Referências
- ADR 0093 Multi-tenant Tier 0 · ADR 0094 Constituição §5 SoC · ADR 0101 Tests biz=1
- ADR 0155 Module Grade v3 · ADR 0159 Polish series
- `.claude/rules/commands.md` (Tier 0 — `--detail` NUNCA `--verbose`)

## [Wave 25 — Polish D7.c retention compliance] — 2026-05-16

### Adicionado — D7.c rubrica governance v3 (+1 arquivo)
- **`Config/retention.php`** novo — declaração canônica LGPD pra catálogo público QR:
  - 1 entity própria: `product_catalogue_version` 1095d (3 anos — versão histórica do catálogo QR exibido publicamente; pós 3y purga).
  - Entidades core UltimatePOS (`products`/`categories`/`discounts`/`business_locations`) explicitamente NÃO declaradas — retention é responsabilidade do core, não deste módulo (separação SoC brutal ADR 0094 §5).
  - `strategy='hard_delete'`, `notice_period_days=30`.

### Por que ter retention.php se ProductCatalogue não tem PII direta?
- Documental: LGPD Art. 16 aplica a dado pessoal; dado de produto é dado da empresa, mas `product_catalogue_version` acumula versões históricas (limite pra cleanup ad-hoc).
- Telemetria: spans `product_catalogue.build_*_payload` via OpenTelemetry podem capturar IP do scanner QR (PII indireta em sample bursty) — gerido pelo collector central, não aqui.
- Audit Wave 25 D7.c: rubrica governance v3 exige `retention.php` canônico em todo módulo `functional_horizontal` pra fechar a dimensão.

### Não alterado (intencional — já saturado)
- D4 Architecture: Controller magro + 2 Services (CatalogueService + CatalogueQrService) + Repository pattern desde W16.
- D9.a OTel: `buildIndexPayload` + `buildShowPayload` wrapped em `OtelHelper::spanBiz` desde W17 (hot path catálogo público QR — múltiplas queries Repository).
- D1 Multi-tenant: Repository filtra `business_id` em toda query (defesa em profundidade — rota pública sem auth pode tentar enumerar tenants).

### Referências
- ADR 0093 Multi-tenant Tier 0 (defesa em profundidade rota pública)
- ADR 0094 Constituição §5 SoC brutal (retention de core ≠ retention de módulo)
- ADR 0101 Tests biz=1
- ADR 0155 Module Grade v3 (D7.c saturated)
- ADR 0159 Wave 25 polish (level `backlog_hipotese` mantido — uso pontual)
