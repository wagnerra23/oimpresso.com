# CHANGELOG — Modules/ProductCatalogue

> Append-only. Mais novo no topo. Datas YYYY-MM-DD.

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
