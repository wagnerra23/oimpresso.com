---
id: resources-js-pages-productcatalogue-catalogueqr-charter
page: /product-catalogue/catalogue-qr
component: resources/js/Pages/ProductCatalogue/CatalogueQr.tsx (hoje catalogue/generate_qr.blade.php)
related_prototype: prototipo-ui/cowork/modulos-faltantes/catalogo-qr-page.jsx
related_contrato: prototipo-ui/contrato/catalogo-qr.contract.json
related_casos: catalogo-qr.casos.md
owner: wagner
status: draft
parent_module: ProductCatalogue
related_adrs: [93, 180]
tier: C
charter_version: 1
mission: "Dar ao dono da loja um QR por local comercial que abre o catálogo público no celular do cliente — pronto pra imprimir e colar no balcão."
---

# Page Charter — /product-catalogue/catalogue-qr (DRAFT)

> **Status:** draft criado pelo [CC] na onda O4. [W] aprova **Non-Goals + Anti-hooks** antes de `status: live`.
> Fato do repo: a entry é **ghost do hub Vendas** (ADR 0180) — `ProductCatalogue/DataController::modifyAdminMenu()` é **NO-OP** de propósito. O QR real é gerado no cliente por `modules/productcatalogue/plugins/easy.qrcode.min.js`; o link é `/catalogue/{business_id}/{location_id}`.

## Mission

O catálogo já existe e é público. Falta o caminho físico até ele: um QR bonito, com o nome da loja, que o cliente aponta no balcão.

## Persona-alvo

Wagner/Rita (escritório) preparam e imprimem. Larissa só usa o resultado colado no balcão.

## Goals — faz

- Escolher o local comercial (é dele que saem preço e estoque do catálogo).
- Ajustar cor do QR, título e subtítulo; incluir ou não o logo do negócio.
- Gerar o QR, ver o link, baixar PNG 256×256 e copiar o link.
- Dizer, sem rodeio, que o catálogo é público.

## Non-Goals — NÃO faz

- ❌ NÃO edita produto, preço ou estoque (isso é o catálogo/produtos).
- ❌ NÃO publica/despublica o catálogo (o link já é público por desenho do módulo).
- ❌ NÃO encurta URL nem hospeda imagem no servidor (o PNG é gerado no cliente).
- ❌ NÃO cadastra local comercial aqui (leva pra Configurações › Locais).
- ❌ NÃO volta a ter entry própria de sidebar (é ghost do hub Vendas).

## Automation hooks (faz)

- Monta o link a partir do negócio + local escolhido; preenche o título com o nome do negócio.

## Anti-hooks (NÃO faz automaticamente)

- ❌ NÃO gera QR sozinho ao abrir a tela (o operador clica).
- ❌ NÃO liga o logo quando o negócio não tem logo cadastrado.
- ❌ NÃO troca o link do QR já impresso quando o local muda (QR antigo segue valendo pro local antigo).

## UX targets

- Cabe em 1280px; duas colunas (forma × resultado) e uma coluna abaixo disso.
- Contraste do QR é leitura, não estética: a tela avisa quando a cor escolhida é clara.
- Estados: sem local escolhido · gerado · sem local cadastrado · carregando · erro · sem-permissão.

## Pendências antes de `status: live`

- [ ] [W] aprova Non-Goals + Anti-hooks.
- [ ] Definir a permissão que abre a tela (hoje herda o acesso a Vendas).
- [ ] Confirmar se o PNG deve ganhar tamanho maior pra impressão A4 (hoje 256 px).
