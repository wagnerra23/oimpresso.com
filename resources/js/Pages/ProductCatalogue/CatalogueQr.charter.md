---
id: resources-js-pages-productcatalogue-catalogueqr-charter
page: /product-catalogue/catalogue-qr
component: resources/js/Pages/ProductCatalogue/CatalogueQr.tsx
related_prototype: prototipo-ui/cowork/Wagner/catalogo-qr-page.jsx
owner: wagner
status: draft
last_validated: "2026-10-09"
parent_module: ProductCatalogue
related_adrs: [93, 104, 180, 358]
tier: C
charter_version: 1
related_runbook: memory/requisitos/ProductCatalogue/RUNBOOK-catalogue-qr.md
related_us: [US-PCAT-003]
---

# Page Charter — /product-catalogue/catalogue-qr (DRAFT)

> **Status:** draft. Thread `modulos-faltantes/playbook/04`. Proposto pelo [CC] em
> `cowork-inbox/modulos-faltantes/catalogo-qr.charter.md`; este é o charter ao lado do `.tsx`.
> `GET /product-catalogue/catalogue-qr` responde Inertia; a Blade `catalogue/generate_qr.blade.php` fica no repo
> até o cutover. Casos: [`CatalogueQr.casos.md`](./CatalogueQr.casos.md).
> A entry é **ghost do hub Vendas** (ADR 0180): `DataController::modifyAdminMenu()` do módulo é NO-OP de propósito.

## Mission

Dar ao dono da loja um QR por local comercial que abre o catálogo público no celular do cliente — pronto pra
imprimir e colar no balcão.

## Goals

- Escolher o local comercial (é dele que saem preço e estoque do catálogo).
- Ajustar cor do QR, título e subtítulo; incluir ou não o logo do negócio.
- Gerar o QR, ver o link, baixar o PNG 256×256 e copiar o link.
- Dizer, sem rodeio, que o catálogo é público.

## Non-Goals

- ❌ NÃO edita produto, preço ou estoque.
- ❌ NÃO publica/despublica o catálogo (o link já é público por desenho do módulo).
- ❌ NÃO encurta URL nem hospeda imagem no servidor (o PNG é gerado no navegador).
- ❌ NÃO cadastra local comercial aqui (leva pra Configurações › Locais).
- ❌ NÃO volta a ter entry própria de sidebar (é ghost do hub Vendas).

## Automation hooks

- Monta o link a partir do negócio da sessão + local escolhido; preenche o título com o nome do negócio.

## Anti-hooks

- ❌ NÃO gera QR sozinho ao abrir a tela (o operador clica).
- ❌ NÃO liga o logo quando o negócio não tem logo cadastrado.
- ❌ NÃO escolhe o negócio do link no navegador: a base `/catalogue/{business_id}` vem do servidor, pela sessão.

## Acesso

Assinatura com `productcatalogue_module` (como antes) **e** `product.view` no papel — decisão `PERM-CQR`,
[W] 2026-10-07. Sem isso, 403 do servidor.

## UX targets

- Cabe em 1280px; duas colunas (forma × resultado) e uma coluna abaixo de 1024px.
- Contraste do QR é leitura: a tela avisa que cor clara em fundo branco o celular não lê.

## Pendências antes de `status: live`

- [ ] [W] aprova Non-Goals + Anti-hooks e o screenshot.
- [ ] Confirmar se o PNG deve ganhar tamanho maior pra impressão A4 (hoje 256 px).
