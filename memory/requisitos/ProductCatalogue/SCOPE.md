---
module: ProductCatalogue
purpose: "Vitrine pública read-only de produto por business e location, acessível sem login via URL ou QR code — zero entidade própria, zero escrita, subordinada ao hub Vendas."
migracao_ui: "pendente — tem Blade servido, sem duvida de escopo; fila em module-surface --migracao"
contains:
  - "DataController"
  - "InstallController"
  - "ProductCatalogueController"
  - "CatalogueService"
  - "CatalogueQrService"
  - "FormatoVitrine"
  - "ProductCatalogueRepository"
  - "ProductCatalogueHealthCommand"
# Não depende de Jana, Forja nem KB (medido em 2026-11-11, git grep em 58 arquivos
# versionados do módulo + resources/js/Pages/ProductCatalogue: Forja 0, KB 0; Jana em 5
# linhas, todas comentário "padrão Jana/Repair"; 0 `use Modules\<outro>`). O
# not_contains antigo apontava "→ Modules/KB" e "→ Modules/Forja" (boilerplate do lote
# de 2026-05-05) e o catalog-graph lia isso como dependência.
not_contains:
  - 'Cadastro, preço e estoque de produto: núcleo (App\Product, ProductController)'
  - 'Regra de desconto: núcleo (App\Discount, tela de descontos)'
  - "Entrada no menu: hub Vendas (app/Http/Middleware/AdminSidebarMenu.php), o DataController::modifyAdminMenu do módulo é NO-OP"
db_tables_owned: []
trust_required: L4
owner: wagner
permission_prefix: productcatalogue.*
charter_adr: 0080
related_adrs:
  - 0079-constituicao-oimpresso-7-camadas-governanca
  - 0080-trust-tiers-operacional-audit-findings
url_prefixes:
  - /product-catalogue/* (catalogue-qr + install)
  - /catalogue/* (catalogo publico por business)
  - /show-catalogue/* (catalogo publico por business)
drift_alerts:
  - "A vitrine é deste módulo (decisão [W] D3, 2026-10-01): as rotas antigas /officeimpresso/catalogue e /officeimpresso/show-catalogue só redirecionam para cá (OfficeimpressoController)."
---

# Modules/ProductCatalogue

## Missão

Catálogo & menu público.

## Trust level

**L4** — ver [TRUST-TIERS.md](../../governance/TRUST-TIERS.md).

## Quando NÃO é tocado

Ver `not_contains[]` no frontmatter. Em dúvida, consulte
[ARCHITECTURE.md](../../governance/ARCHITECTURE.md).

---

- **v1.0.0** (2026-05-05) — SCOPE.md inicial. Gerado em batch via Fase 3.4 do ADR 0079.
- **v1.1.0** (2026-11-11) — `contains` com Services, Repository e Command; `not_contains` com as fronteiras reais (núcleo e hub Vendas) no lugar do boilerplate KB/Forja, que o catalog-graph lia como dependência; `db_tables_owned: []`; drift da vitrine herdada do Officeimpresso.
