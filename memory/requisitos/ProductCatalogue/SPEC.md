---
id: requisitos-product-catalogue-spec
module: ProductCatalogue
version: "1.1.0"
last_updated: "2026-11-11"
owner: wagner
status: ativo
na_justified:
  D6.b: "ProductCatalogue é catálogo público read-only — p99 OTel <500ms ainda não exportado (instrumentação OTel project-wide pendente). Performance dominada por queries simples em `App\\Product` com cache de imagens — sem otimização específica necessária no estado atual."
  D9.b: "ProductCatalogue é módulo read-only sem operações assíncronas — só renderização pública via Controller. Sem jobs/Horizon. failed_jobs N/A por design."
na_justified_v3:
  D5: "ProductCatalogue é catálogo público QR/URL read-only acoplado a Modules/Vestuario (cliente real = Larissa ROTA LIVRE biz=4) e ao núcleo comum (App\\Product) — ADR 0094 §5 SoC brutal proíbe duplicar Models do core; ADR 0121 trata módulos verticais como produtos vendáveis com cliente próprio, mas catálogo é VIEW agregadora compartilhada entre todos os verticais (não tem cliente nominal próprio — herda do vertical que vende). ADR 0105 (cliente como sinal qualificado) descarta atribuir cliente fictício; D5 N/A justificado pois métrica adoção mora no vertical pai (Vestuario=biz_4_rota_livre_prod, ComunicacaoVisual=backlog_hipotese)."
related_adrs: [0011-alinhamento-padrao-jana, 0093-multi-tenant-isolation-tier-0, 0094-constituicao-v2-7-camadas-8-principios, 0105-cliente-como-sinal-guiar-sem-mandar, 0121-oimpresso-modular-especializado-por-vertical, 0153-module-grade-rubrica-v1, 0154-module-grade-v2-na-justificado, 0155-module-grade-v3-sub-dimensoes-gate-ci, 0156-module-grade-v3-errata-otel-helper-na-justified]
---

<!-- schema-allowlist: o backlog usa ids US-PCAT-W01..W05 (feature wish), fora do padrão US-<MOD>-NNN que o validador de seções exige. -->

# SPEC — Modules/ProductCatalogue

> Catálogo append-only de User Stories do módulo da vitrine pública de produtos (QR code + link). Núcleo herdado do UltimatePOS v6 ([ADR 0011](../../decisions/0011-alinhamento-padrao-jana.md)), **sem tabela nem model próprio**: lê `App\Product`, `App\Business`, `App\BusinessLocation`, `App\Category`, `App\Discount` e `App\SellingPriceGroup` do núcleo.

## Estado

Medido em `747e0aa` (2026-11-11).

- ✅ Em produção (3 Controllers, 0 Entities, 0 tabelas próprias).
- ✅ Isolamento por empresa feito no Repository: `App\Product` **não** tem global scope de `business_id`. Cada consulta filtra a empresa com `where('business_id', ...)` em `Modules/ProductCatalogue/Repositories/ProductCatalogueRepository.php` (`:41` listagem, `:73` detalhe, `:97` local, `:107` descontos).
- 🟡 A vitrine pública (`/catalogue`, `/show-catalogue`) é Blade. A tela admin do QR (`/product-catalogue/catalogue-qr`) é Inertia desde o F5 de 2026-10-27 (#9997).
- 🟡 A vitrine não tem SDD (ver Pendências).

## User stories

### US-PCAT-001 · Vitrine pública por empresa e local (`/catalogue/{business_id}/{location_id}`)

> owner: W · status: done · type: story

**Implementado em:** `Modules/ProductCatalogue/Routes/web.php` · `Modules/ProductCatalogue/Http/Controllers/ProductCatalogueController.php` · `Modules/ProductCatalogue/Repositories/ProductCatalogueRepository.php` · `Modules/ProductCatalogue/Resources/views/catalogue/index.blade.php` · verificado@747e0aa (2026-11-11) — o filtro de empresa, local, produto ativo e "não vender" está no Repository (`listProductsByLocation`), não no Controller

**Testado em:** `tests/Feature/Sells/CatalogoPublicoSituacaoContratoTest.php` (lane `sells-pest.yml`)

**Aceite:** sem login, lista por categoria só os produtos da empresa da URL, ligados ao local, ativos e à venda; local de outra empresa dá 404; mais de 30 requisições por minuto por IP dão 429.

### US-PCAT-002 · Detalhe público do produto (`/show-catalogue/{business_id}/{product_id}`)

> owner: W · status: done · type: story

**Implementado em:** `Modules/ProductCatalogue/Http/Controllers/ProductCatalogueController.php` · `Modules/ProductCatalogue/Services/CatalogueService.php` · `Modules/ProductCatalogue/Resources/views/catalogue/show.blade.php` · verificado@747e0aa (2026-11-11) — a resposta é um fragmento de modal que a vitrine carrega por AJAX; aberta direto, sai sem layout

**Testado em:** `tests/Feature/Sells/CatalogoPublicoSituacaoContratoTest.php` · `tests/Feature/Sells/CatalogoPublicoXssContratoTest.php` (lane `sells-pest.yml`)

**Aceite:** abre produto ativo e à venda da empresa da URL (simples, variável e combo); produto de outra empresa, desativado ou "não vender" dá 404; com `location_id`, só abre produto vendido naquele local; script gravado na descrição não executa.

### US-PCAT-003 · Tela admin que gera o QR do catálogo

> owner: W · status: done · type: story

**Implementado em:** `resources/js/Pages/ProductCatalogue/CatalogueQr.tsx` · `Modules/ProductCatalogue/Services/CatalogueQrService.php` · `Modules/ProductCatalogue/Http/Controllers/ProductCatalogueController.php` · verificado@747e0aa (2026-11-11) — Inertia desde o F5 de 2026-10-27; contrato em `resources/js/Pages/ProductCatalogue/CatalogueQr.casos.md`

**Testado em:** `tests/Feature/Sells/CatalogueQrContratoTest.php` (lane `sells-pest.yml`) · `tests/Browser/ProductCatalogue/CatalogueQrBrowserTest.php`

**Aceite:** os UC-CQR do `CatalogueQr.casos.md`. A base do link vem da empresa da sessão; sem `productcatalogue_module` na assinatura ou sem `product.view`, 403.

### US-PCAT-004 · Instalar, atualizar e desinstalar o módulo (padrão ADR 0011)

> owner: W · status: todo · type: story

**Implementado em:** _pendente_ — o código existe (`Modules/ProductCatalogue/Http/Controllers/InstallController.php`, 4 ações: index, install, uninstall, update), mas nenhum teste numa lane cobre a instalação

### US-PCAT-005 · Selo de desconto ativo na vitrine

> owner: W · status: done · type: story

**Implementado em:** `Modules/ProductCatalogue/Repositories/ProductCatalogueRepository.php` · `Modules/ProductCatalogue/Services/CatalogueService.php` · `Modules/ProductCatalogue/Resources/views/catalogue/partials/discount_badge.blade.php` · verificado@747e0aa (2026-11-11) — desconto fixo sai em dinheiro desde o #10779; o critério que escolhe o desconto do selo na listagem está em revisão no #10800

**Testado em:** `tests/Feature/Sells/CatalogoPublicoDescontoContratoTest.php` (lane `sells-pest.yml`)

**Aceite:** só desconto ativo, da empresa e do local, dentro da janela `starts_at ≤ agora ≤ ends_at`, por prioridade; porcentagem com "%", valor fixo em dinheiro; desconto de outra empresa no mesmo local não gera selo.

### US-PCAT-006 · Isolamento entre empresas na vitrine pública (Tier 0)

> owner: W · status: done · type: story

**Implementado em:** `Modules/ProductCatalogue/Repositories/ProductCatalogueRepository.php` · verificado@747e0aa (2026-11-11) — o isolamento é o `where('business_id', ...)` explícito de cada consulta; `App\Product` não tem global scope

**Testado em:** `tests/Feature/Sells/CatalogoPublicoSituacaoContratoTest.php` (lane `sells-pest.yml`; casos "produto da empresa 99 não aparece na vitrine da 98" e "produto da empresa 99 dá 404 pela URL da 98", verdes na run `43081420123`)

**Aceite:** produto, local e desconto de uma empresa nunca aparecem pela URL de outra; produto de outra empresa dá 404 no detalhe. As URLs são numéricas (`/catalogue/{business_id}/...`), não existe slug.

> O `Modules/ProductCatalogue/Tests/Feature/PublicCatalogueSecurityTest.php`, citado aqui antes, não roda em lane nenhuma em `747e0aa`; a entrada dele numa lane está no lote 2 da auditoria (#10783, #10785).

## Pendências

- **SDD da vitrine:** não existe. A matriz §3 do `TEAM.md` não tem linha para requisito ou SDD de vitrine pública; a mais próxima é "Blade views legacy (UltimatePOS)", com ✅ para [M] e [L] (em par). Decisão [W] 2026-11-12: o SDD fica com [M] e [L] em par. Ainda não foi escrito.
- **Assinatura nas rotas públicas:** só a tela do QR confere `productcatalogue_module`; `/catalogue` e `/show-catalogue` não conferem. Se é intencional é decisão [W].

## Backlog (feature wish — sem sinal qualificado ADR 0105)

- 🔒 US-PCAT-W01: Slug amigável por empresa (em vez de `business_id` numérico — UX + SEO)
- 🔒 US-PCAT-W02: Embed widget JS (`<script src="...">`) pra incorporar em site do cliente
- 🔒 US-PCAT-W03: Compartilhamento WhatsApp/Instagram com OG tags otimizadas
- 🔒 US-PCAT-W04: Analytics de visualização (page views + produto mais clicado)
- 🔒 US-PCAT-W05: Vitrine pública Blade → Inertia/React (skill `mwart-process`). A tela admin do QR já migrou (US-PCAT-003); falta a vitrine.

## Models reusados (núcleo)

- `App\Product` — **sem** global scope de `business_id`; o Repository filtra a empresa em toda consulta
- `App\Business`, `App\BusinessLocation` — local buscado com `where('business_id', ...)`
- `App\Category` — `Category::forDropdown($business_id, 'product')`
- `App\Discount` — filtro explícito por `business_id` + `location_id`
- `App\SellingPriceGroup` — filtro explícito por `business_id`

## Decisão arquitetural

**Não criar Entities próprios em Modules/ProductCatalogue/Entities/** — a vitrine é uma leitura sobre os produtos do núcleo. Model duplicado violaria SoC ([ADR 0094 §5](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md)). Quando uma feature exigir estado próprio (ex.: analytics de visualização), criar Entity nova alinhada à ADR 0011.

## Refs

- [Modules/ProductCatalogue/Http/Controllers/ProductCatalogueController.php](../../../Modules/ProductCatalogue/Http/Controllers/ProductCatalogueController.php)
- [Modules/ProductCatalogue/Routes/web.php](../../../Modules/ProductCatalogue/Routes/web.php)
- [ADR 0093 Multi-tenant Tier 0](../../decisions/0093-multi-tenant-isolation-tier-0.md)
- [ADR 0011 Alinhamento padrão Jana/Repair](../../decisions/0011-alinhamento-padrao-jana.md)
- [BRIEFING.md](BRIEFING.md)
