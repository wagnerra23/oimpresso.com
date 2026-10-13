---
sessao: "04b"
titulo: Catálogo QR — adendo: tela React atrás de chave MWART desligada
autor: "[CL]"
data: 2026-10-13
thread: 04-catalogo-qr.md
pr: 9166
---

# _saida-04b · Catálogo QR atrás da chave

O `_saida-04` (2026-10-09) descreve a tela React servida direto em `GET /product-catalogue/catalogue-qr` (#9103).
O [#9166](https://github.com/wagnerra23/oimpresso.com/pull/9166), mergeado em 2026-10-13, mudou isso:

- A tela React passa a ficar atrás de `mwart.product_catalogue_qr` (`MWART_PRODUCT_CATALOGUE_QR`, padrão
  **desligada**; `MWART_PRODUCT_CATALOGUE_QR_BIZ` restringe por empresa), como pede o ADR 0104.
- Com a chave desligada, produção volta a servir a Blade `catalogue/generate_qr`, o estado anterior ao #9103.
- A autorização (módulo na assinatura + `product.view`) segue antes dos dois caminhos.
- Novo UC-CQR-11 (`CatalogueQrContratoTest`, lane `sells-pest`): chave desligada responde Blade; chave ligada só para
  outra empresa também; com a empresa na lista, responde a tela React.
- O teste `CatalogoRedirectTest` do Officeimpresso passou a ligar a chave antes de exigir a tela React (#9179).

## Fica para o [W]
Ligar a chave em produção (cutover) e depois remover a Blade.
