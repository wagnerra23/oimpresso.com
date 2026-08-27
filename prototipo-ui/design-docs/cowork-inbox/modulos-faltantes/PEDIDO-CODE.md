# Pedido pro Claude Code — telas importadas do vivo (ponte, não export)

> Escrito pelo [CC] em 24/08/2026. **Não commitei nada** — as tools de GitHub deste projeto são
> read-only. Este arquivo é o pedido para o Code aplicar no `main`.

## 1. O que veio do Cowork

Seis telas que existiam no vivo (ou eram declaradas nele) e não existiam no protótipo:

| Tela | Rota Cowork | Alvo no repo | Situação no vivo |
| --- | --- | --- | --- |
| Comunicação Visual | `cv` | `resources/js/Pages/ComunicacaoVisual/Index.tsx` | existe (calculadora m²) — charter `status: draft` |
| Voz do Cliente | `voz` | `Modules/VozDoCliente` → `Pages/VozDoCliente/Caixa.tsx` | blade AdminLTE, sem charter |
| Modo Suporte | `suporte` · `suporte-visao` | `Pages/Suporte/{Empresas,Visao}.tsx` + `Log.tsx` (novo) | Empresas/Visao existem; **Log é novo** |
| Vestuário · Etiquetas | `vestuario` · `vest-etiquetas` | `Pages/Vestuario/Etiquetas/Index.tsx` | existe; 2 divergências abertas no SDD |
| Catálogo QR | `catalogo-qr` | `Modules/ProductCatalogue` → `Pages/ProductCatalogue/CatalogueQr.tsx` | blade + easy.qrcode, sem charter |
| Arquivos (DMS) | `arquivos` · `arq-*` | `Pages/Arquivos/Index.tsx` (US-ARQ-013) | **não existe** — `DataController` NO-OP |

Arquivos do build a aplicar (todos em `prototipo-ui/cowork/modulos-faltantes/` no destino):
`comunicacao-visual-page.jsx` · `voz-do-cliente-page.jsx` · `suporte-page.jsx` ·
`vestuario-page.jsx` · `catalogo-qr-page.jsx` · `arquivos-data.jsx` · `arquivos-page.jsx` ·
`modulos-faltantes.css` (+ o host `oimpresso.com.html`, `app.jsx` e `data.jsx` já ligados).

## 2. Lacunas de nav que isto fecha (medidas contra o `main`)

Comparando `AdminSidebarMenu.php` + os 32 `Modules/*/DataController::modifyAdminMenu` com o
`MOCK.MENU`, faltavam: **Comunicação Visual** (`group:'producao'`, order 55), **Voz do Cliente**
(sem group), **Suporte** (`group:'sistema'`, order 90, nasce no core), **Vestuário** (era ghost de
Compras; no vivo é entry `group:'vender'`, order 35, shortcut `G V`) e **Catálogo QR** (ghost de
`__('sale.sale')` no core, ao lado do WooCommerce).

## 3. O que eu peço ao Code

1. **Promover os Contratos de Tela a required.** Os 6 `.contract.json` deste diretório vão pra
   `prototipo-ui/contrato/`. Rodar advisory no `contrato-de-tela.yml` primeiro; promover a required
   quando passar (ADR 0286).
2. **Rodar `scripts/qa/prototipo-readiness.mjs`** depois de aplicar — não mantenho fila manual
   nem retrato de prontidão em arquivo (L-42).
3. **Exceção do `cowork-ssot-guard` (R1)**: este diretório carrega `.md`/`.json` de ponte. Se o
   destino escolhido for dentro de `prototipo-ui/cowork/`, o guard precisa da exceção; o caminho
   limpo é charter → `resources/js/Pages/...`, contrato → `prototipo-ui/contrato/`, e só o build
   ficar em `cowork/`.
4. **Charters novos** (`voz-do-cliente`, `catalogo-qr`, `arquivos`) entram como
   `<Tela>.charter.md` ao lado do componente-alvo, `status: draft` até [W] aprovar
   Non-Goals + Anti-hooks.

## 4. O que depende de [W], não do Code

- **Vestuário D-1** — ligar o hard-block de `vestuario.etiqueta.*` (hoje `authorizeAccess()` só
  loga `permission_check_missing`). Tweak na tela mostra os dois lados.
- **Vestuário D-2** — prévia antes de imprimir: podar a promessa do charter **ou** construir.
- **Arquivos** — a tela mora no Admin Center (US-ARQ-013) ou ganha entry própria? Tweak "Onde mora"
  mostra as duas leituras.
- **Voz do Cliente / Catálogo QR** — qual permissão abre cada tela (hoje herdam acesso).

## 5. O que eu NÃO fiz de propósito

- Não commitei, não abri branch, não abri PR.
- Não gerei arquivo de retrato (manifesto de export, inventário, contagem de rotas) — gero na hora
  lendo o host + `app.jsx`.
- Não escolhi vencedor de divergência aberta de charter.
