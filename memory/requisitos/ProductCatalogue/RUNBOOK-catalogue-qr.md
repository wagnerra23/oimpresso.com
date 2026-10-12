---
slug: productcatalogue-runbook-catalogue-qr
title: "Catálogo QR — Runbook da tela"
type: runbook
module: ProductCatalogue
tela: ProductCatalogue/CatalogueQr
owner: W
status: ativo
last_validated: "2026-10-09"
related_adrs:
  - '0104-processo-mwart-canonico-unico-caminho'
  - '0093-multi-tenant-isolation-tier-0'
  - '0180-sidebar-v3-5-grupos-ghosts-header'
---

# RUNBOOK — Catálogo QR (`/product-catalogue/catalogue-qr`)

> **Tipo:** runbook reproduzível · MWART ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)) · thread `modulos-faltantes/playbook/04`
> **Fonte de design:** `prototipo-ui/cowork/Wagner/catalogo-qr-page.jsx` (`CatalogoQrPage`). Sem alvo medido ainda — o contrato de forma vem depois do alvo.
> **Fronteira:** só a tela admin que gera o QR. A vitrine pública (`/catalogue/{biz}/{local}`, `/show-catalogue/...`) segue Blade e não muda.

## Estado final esperado

`GET /product-catalogue/catalogue-qr` (`ProductCatalogueController::generateQr`) responde Inertia
`ProductCatalogue/CatalogueQr` com os locais comerciais do negócio da sessão, o nome e o logo do negócio e a
base do link (`/catalogue/{business_id}`). O QR é gerado no navegador, como na Blade, pelo
`easy.qrcode.min.js` que o módulo já publica — nenhuma dependência nova.

## 1. Objetivo

Trocar a Blade `catalogue/generate_qr.blade.php` (jQuery + colorpicker + `alert()`) pela tela React, sem mudar o
link gerado nem o fato de o PNG nascer no cliente.

## 2. Pré-condições

- Módulo `productcatalogue_module` na assinatura do negócio (como antes) **e** `product.view` no papel — decisão
  `PERM-CQR` do [W] em 2026-10-07. O dono do negócio passa pelo `Gate::before`.
- Asset `modules/productcatalogue/plugins/easy.qrcode.min.js` publicado (o mesmo que a Blade carregava).

## 3. Passo-a-passo

1. `CatalogueQrService::buildPagePayload()` monta `locais`, `negocio` (`nome`, `logo_url` ou `null`), `link_base`
   e `qr_script`. O id do negócio vem da sessão; a tela só acrescenta `/{location_id}`.
2. A Page carrega o script do QR na primeira geração e desenha no `<div>` de saída.
3. "Baixar imagem" exporta o `canvas` como `qrcode.png`; "Copiar link" copia o mesmo link do QR.

## 4. Tokens CSS

Só tokens do DS. As cores do QR são dado escolhido pelo operador (vão para a imagem), não cor de interface.

## 5. Estados visuais

Formulário · nenhum QR gerado · QR gerado · nenhum local comercial cadastrado · erro ao carregar o gerador.
Sem permissão = 403 do servidor (a tela não chega a abrir).

## 6. Responsividade

Duas colunas (formulário × resultado) a partir de 1024px; uma coluna abaixo disso.

## 7. Atalhos

Nenhum.

## 8. Component contract

Seções `data-contract`: `cabecalho`, `formulario`, `instrucoes`, `saida` — as do
`cowork-inbox/modulos-faltantes/catalogo-qr.contract.json`.

## 9. Testes

`tests/Feature/Sells/CatalogueQrContratoTest.php`, lane `sells-pest.yml` (MySQL). O catálogo é ghost do hub
Vendas (ADR 0180); os testes do módulo em `Modules/ProductCatalogue/Tests` não estão em lane nenhuma.

## 10. Fora desta onda

- Contrato de forma (`governance/design/contracts/catalogo-qr.contract.json`): depois do alvo medido.
- Remover a Blade `generate_qr.blade.php`: no cutover.
- PNG maior para impressão A4: pendência do charter, decisão [W].
