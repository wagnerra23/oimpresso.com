---
id: requisitos-productcatalogue-catalogueqr-visual-comparison
title: "Comparacao design x producao — ProductCatalogue/CatalogueQr"
module: ProductCatalogue
tela: ProductCatalogue/CatalogueQr
owner: W
status: rascunho
inertia_target: resources/js/Pages/ProductCatalogue/CatalogueQr.tsx
last_updated: "2026-11-10"
last_validated: "2026-11-10"
---

# Comparação design × produção — `ProductCatalogue/CatalogueQr`

> **Âncora computada** (`node scripts/design/ancora.mjs ProductCatalogue/CatalogueQr --staging prototipo-ui/cowork`):
> `prototipo-ui/cowork/Wagner/catalogo-qr-page.jsx`, declarada em `related_prototype` do charter.

## Como foi medido (2026-11-10)

- **Protótipo:** espelho do git servido por `http.server` na raiz do worktree, rota `catalogo-qr`, 1280×800.
- **Produção:** `https://oimpresso.com/product-catalogue/catalogue-qr`, empresa 1 (o link gerado termina em
  `/catalogue/1/1`), em iframe da mesma origem com 1280×800. O código medido é o de `main` antes deste PR.
- **Os dois lados:** a mesma sonda de `getComputedStyle` + `getBoundingClientRect`, e o papel `title`/`primary` do
  `scripts/design/design-diff.mjs --probe`. Tema escuro nos dois para cores de interface.
- **Não medido:** tema claro em produção, porque a conta usada está no escuro. Comportamento do "Baixar imagem" além
  do tamanho do canvas. As 4 cores vêm do que a tela pinta; a variação de ±1 em cada canal entre os dois navegadores
  usados é do conversor, e o valor no código é o mesmo.

## Veredito por item

Colunas "Protótipo" e "Produção" são o medido antes das correções deste PR.

| # | Item | Protótipo | Produção | Veredito |
|---|---|---|---|---|
| 1 | Cor inicial do QR | índice 1, roxo | índice 0, preto | **Corrigido** para a do protótipo |
| 2 | 2ª cor do QR | `rgb(121, 91, 191)` | `rgb(91, 63, 168)` | **Corrigido**. Afeta o PNG |
| 3 | Demais cores (preto, azul, pink) | `rgb(17,17,17)` · `#235EA9` · `#EB3088` | iguais | Igual |
| 4 | Swatch | 28×28, raio 7 px, borda de token | 28×28, círculo, sem borda | **Corrigido** |
| 5 | Swatch ativo | borda accent + halo de 3 px | só borda, sem halo | **Corrigido** |
| 6 | Formulário | 2 colunas (Título e Subtítulo lado a lado, x97 e x420) | 1 coluna | **Corrigido** |
| 7 | Proporção das seções a 1280 | 668 : 494 (1,352) | 474 : 474 (1,000) | **Corrigido** para `1.15fr / .85fr` |
| 8 | Quebra para 1 coluna | abaixo de 1101 px | entre 1100 e 1024 px, pela largura do conteúdo | **Corrigido** para 1100 px. Charter ajustado |
| 9 | `h2` da seção | 13,5 px / 600 | 14 px / 600 | **Corrigido** |
| 10 | Padding da seção | 16 px | 20 px | **Corrigido** |
| 11 | Instruções | chip numérico, sem marcador, 12,5 px | `list-decimal`, 14 px | **Corrigido** |
| 12 | `h1` | 22 px / 600 | 22 px / 600 | Igual |
| 13 | Raio da seção | 12 px | 12 px | Igual |
| 14 | Fundo e borda da seção (escuro) | `oklch(0.3 0.008 240)` · `oklch(0.34 0.008 240)` | iguais | Igual |
| 15 | Altura dos campos | 30 px | 30 px | Igual |
| 16 | Botão "Gerar QR code" | 30 px de altura, 12,5 px | 36 px de altura, 14 px | **Listado**: tamanho do `Button` do DS, não da tela |
| 17 | Cor do botão primário (escuro) | `rgb(121, 91, 191)` | `rgb(167, 136, 241)` | **Listado**: token do DS, afeta todas as telas |
| 18 | Título e subtítulo do QR | card HTML, fora do desenho | desenhados no canvas, entram no PNG | **Listado**: muda o arquivo baixado, decisão [W] |
| 19 | Link gerado | dentro do card, 11 px mono | fora do canvas, em `<code>`, 12 px mono | **Listado**: vai junto com o item 18 |
| 20 | Tamanho declarado do PNG | "256 × 256 px · PNG" | selo igual, mas o canvas real tem **299 × 359 px** | **Listado**: o selo não bate com o arquivo. Ver a pendência do charter sobre o tamanho do PNG |
| 21 | Espaço entre as seções | 14 px | 24 px | **Listado**: sem token de 14 px |
| 22 | Selos da saída | `StatusBadge` | `Badge` | **Listado** |
| 23 | Sublabel do logo | "uploads/business_logos — o logo entra no meio do QR" | "O logo entra no meio do QR." | **Listado**: o caminho técnico não ajuda o operador |
| 24 | Estados `carregando`, erro de locais e `sem-acesso` | desenhados | sem tela (locais vêm no payload; sem acesso é 403) | **Listado** |
| 25 | Scroll horizontal a 1280 | 0 | 0 | Igual |

## Contrato

`governance/design/contracts/catalogo-qr.contract.json` nasceu da proposta do Cowork, com `fonte` e `alvo` apontando os
caminhos reais e só os estados que a tela tem. Validado por `scripts/contrato-de-tela.mjs`: 4 seções com âncora e copy
presentes.

## Não tocado neste PR

As frases "preço e estoque saem do local" esperam a decisão D3 do [W]. O espelho `prototipo-ui/cowork/**` é só leitura.
