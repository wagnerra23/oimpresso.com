---
id: requisitos-product-catalogue-briefing
module: ProductCatalogue
status: producao
status_nota: "Vitrine pública em Blade (/catalogue, /show-catalogue) e tela admin do QR em Inertia (/product-catalogue/catalogue-qr). Uso real por empresa não medido."
updated_at: "2026-11-11"
owner: W
---

# BRIEFING — Modules/ProductCatalogue

> Estado consolidado executivo (1 página). Medido em `747e0aa` (2026-11-11). Os números abaixo vêm com o comando que os reproduz; não os copie para outro documento.

## O que faz

**Vitrine pública de produtos** aberta por QR code ou link (`/catalogue/{business_id}/{location_id}`). O cliente final entra sem login e vê, por categoria, os produtos vendidos naquele local, com preço, variações, combos e selo de desconto ativo. Clicar num produto abre o detalhe num modal (`/show-catalogue/{business_id}/{product_id}`).

A vitrine termina na consulta: não tem carrinho, pedido nem botão de WhatsApp.

## Para quem

Empresa que quer mostrar produtos sem loja online: QR no balcão, link na bio, cardápio impresso.

## Como funciona hoje

- **Sem tabela própria.** Lê `products`, `business_locations`, `categories`, `discounts` e `selling_price_groups` do núcleo. A migration do módulo só grava a versão dele na tabela `system`.
- **Isolamento por empresa vem do Repository, não do model.** `App\Product` **não** tem global scope de `business_id` (`app/Product.php` usa só `LogsActivity`). Cada consulta da vitrine filtra a empresa de forma explícita em `Modules/ProductCatalogue/Repositories/ProductCatalogueRepository.php`: listagem `:41`, detalhe `:73`, local `:97`, descontos `:107` (linhas em `747e0aa`).
- **Só produto à venda.** Listagem e detalhe aplicam `active()` (`is_inactive = 0`) e `ProductForSales()` (`not_for_selling = 0`). O detalhe aberto pelo modal também exige que o produto seja vendido naquele local.
- **Limite de acesso.** As duas rotas públicas têm `throttle:30,1` (30 requisições por minuto por IP, `Modules/ProductCatalogue/Routes/web.php:15`). As rotas admin têm `throttle:60,1` (`:24`).
- **Tela do QR (admin) em Inertia** desde o F5 de 2026-10-27 (#9997): `resources/js/Pages/ProductCatalogue/CatalogueQr.tsx`. Acesso: assinatura com `productcatalogue_module` **e** `product.view` no papel (`CatalogueQrService::authorizeAccess`). A entrada no menu é um atalho do hub Vendas.
- **Vitrine segue em Blade** (`Modules/ProductCatalogue/Resources/views/catalogue/`), sem tela Inertia.

## Testes

Contados em `747e0aa`:

| Onde | Arquivos | Roda em lane? |
|---|---|---|
| `tests/Feature/Sells/Catalog*Contrato*.php` (vitrine: situação, XSS, desconto; tela do QR) | 4 | Sim, `sells-pest.yml`. Run `43081420123` (main, 2026-11-11): situação, XSS e QR passaram. O de desconto entrou com o #10779 e não tinha run concluída na medição. |
| `tests/Browser/ProductCatalogue/CatalogueQrBrowserTest.php` | 1 | `ci.yml` |
| `Modules/ProductCatalogue/Tests/Feature/*.php` | 7 | Não. A entrada numa lane está no lote 2 da auditoria (#10783, #10784, #10785, abertos). |

Para recontar: `git ls-files 'tests/Feature/Sells/Catalog*Contrato*.php' 'tests/Browser/ProductCatalogue/*' 'Modules/ProductCatalogue/Tests/Feature/*.php'` e `grep -n Catalog .github/workflows/sells-pest.yml`.

## Gaps conhecidos

- 🟡 `business_id` numérico exposto na URL pública (enumeração). Sem slug amigável.
- 🟡 As rotas públicas não conferem se a empresa assinou `productcatalogue_module`; só a tela do QR confere. Se isso é intencional não está decidido (decisão [W]).
- 🟡 Vitrine sem SDD e sem `.casos.md` (a tela Blade fica fora do regime de casos). O SDD fica com [M] e [L] em par (decisão [W] 2026-11-12, registrada no [SPEC](SPEC.md)).
- 🟡 O detalhe monta os preços de grupo (atacado) no payload (`CatalogueService.php:72-89`), mas nenhuma view da vitrine os mostra (`git grep allowed_group_prices -- Modules/ProductCatalogue/Resources` = 0). Mostrar ou cortar é decisão em aberto.
- 🔒 Embed widget JS, analytics, OG tags (feature wish, sem sinal).

## Refs

- [SPEC.md](SPEC.md) · [SCOPE.md](SCOPE.md) · [RUNBOOK-catalogue-qr.md](RUNBOOK-catalogue-qr.md)
- [Modules/ProductCatalogue/](../../../Modules/ProductCatalogue/)
