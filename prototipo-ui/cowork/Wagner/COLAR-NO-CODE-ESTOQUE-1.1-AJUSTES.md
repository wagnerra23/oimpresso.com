# EXPORT Estoque · ONDA 1.1 — aba **Ajustes** (`/stock-adjustments`)

> 1 arquivo = 1 aba = 1 onda = 1 PR (≤300 linhas). Pedido zero-toque; passa no teste do estranho.
> Emitido 2026-09-04 por [CC]. Alvo = protótipo **medido**. Âncora = arquivo real do `main` **lido no turno da medição**.

## BLOCO 1 — Âncora dupla

**Lido no `main` (2026-09-04, árvore `fb2240427978`):**
- `resources/js/Pages/StockAdjustment/Index.tsx` (9.429 B) — a LIST viva
- `resources/js/Pages/StockAdjustment/Index.charter.md` — R-ADJ-001..004 · `bundle_source: estoque-page.jsx`
- `resources/js/Pages/StockAdjustment/Create.tsx` (14.490 B) — lido pra saber o que o Index **não** pode quebrar (contrato do `useForm`, guardas de `permissions`)
- `resources/js/Pages/Estoque/Movimentacao.casos.md` — **só linhas de match** (UC-EST-01, UC-INV-02/03/05) → integral = *não verifiquei*

**Não verificado neste turno:** controllers/rotas, `AppShellV2.tsx`, `Components/shared/PageHeader.tsx`, `Components/ui/*`.

**Alvo medido:** host `oimpresso.com.html`, rota `est-ajustes`, tema dark, após `__oiLazyDone` **e duas leituras iguais** (1023 = 1023). Sonda validada antes do veredito (`font-size:24px`→`24px`; `rgb(1,2,3)`→`rgb(1, 2, 3)`). ✅

## BLOCO 2 — a11y do alvo (A1–A12)

✅ `tbody tr` com `role="button"` + `tabindex="0"` (6/6) · 0 `DIV` clicável anônimo · 0 botão sem nome acessível · checkbox rotulado (`"Selecionar página"`, `"Selecionar AJ-0142"`) · 0 campo sem rótulo · `role="tablist"` correto · **0 cor crua** em `style` inline.

⚠️ 41 `cursor:pointer` sem `role` = **falso-positivo** (`TD/SPAN/B/SMALL` dentro da `tr` já rotulada, herdando cursor).
⚠️ Anel de foco: **inconclusivo** — `.focus()` não dispara `:focus-visible`; medir com teclado real.

❌ **9 `th` sem `scope="col"`** → dono é o **bundle do DS** (`DataGrid`/`DataTablePro`).
❌ **4 `svg` sem `aria-hidden`**, todos dentro de `BUTTON` já nomeado → **bundle do DS** (o `JcIcon` do build daqui **já** emite `aria-hidden`, verificado em `chat-jana.jsx:50`).
❌ **0 `<main>`** no documento (AP9 pede 1) → **host/shell**, fora do módulo.

**Corrigido no build daqui: nada** — nenhum dos 3 defeitos é do módulo. Registrado, não maquiado.

## BLOCO 3 — Alvo medido

**TabBar (autoridade DS → protótipo → produção)**
`<nav class="ds-tabbar jm-tabs" aria-label="Áreas de Estoque" role="tablist">` · h **36px** · `padding: 0 24px` · `gap: 0` · borda inferior **1px `oklch(0.34 0.008 240)`** sangrando ponta a ponta · tab `padding: 0 14px`, **13px**; inativa `500`/`oklch(0.72 0.005 90)`, ativa `600`/`oklch(0.94 0.005 90)` · 5 abas com contador mono (Ajustes = 6).

**Grade** (1600px em página de 1713)
`thead th`: **10px** uppercase, `letter-spacing .5px`, `oklch(0.58 0.005 90)`, sticky `top:0`, `padding 7px 10px` · linha **54px**, corpo **12,5px**, 9 células.
Colunas: `☐` · Ajuste · **Data ↓** · Local · Tipo · Itens (dir.) · Valor ajustado (dir.) · Motivo do ajuste · Lançado por.

**Toolbar** h **48px**: busca (`/`) · Compacto · CSV · Excel · Imprimir · **Colunas** · select de local · `+ Novo ajuste`.

## BLOCO 4 — Delta alvo × `main`

| # | Assunto | `main` hoje | Alvo | Veredito |
|---|---|---|---|---|
| A1 | Cor de status/tipo | `TYPE_PILL` com **cor crua** (`bg-stone-50 text-stone-700`, `bg-destructive-soft`) | `StatusBadge` do DS (tinta 6% + borda 22%) | **pedido** — viola zero-cor-crua e AP7 |
| A2 | Grade | `<table>` à mão: sem seleção, sem ordenação, sem colunas configuráveis | `DataGrid` + `ColumnManager` (sticky head, ordenação, seleção) | **pedido** |
| A3 | Densidade | `h-11` (44px) fixo | 54px + tweak compacto | **pedido** via `Segmented` |
| A4 | Exclusão | `confirm()` nativo | `Modal` do DS (PT-04) | **pedido** |
| A5 | Toolbar | filtros soltos num `Card sticky top-14` | `Toolbar` de 3 zonas + `ToolbarSearch` | **pedido** |
| A6 | Abas do módulo | **não existe** — tela solta, ligada só por breadcrumb | `TabBar` com 5 views | **pedido**, mas ver PARAR SE (rota é de [W]) |
| A7 | Partial reload D-14 | ✅ `router.get(..., only:['rows','filters'])` | protótipo não tem rede | **`main` à frente — NÃO virar pedido** |
| A8 | Permissões | ✅ `permissions.view_purchase_price` mascara com `—`; `permissions.delete` esconde ação | espelhado (`D.can(papel,"preco")`) | **paridade — não mexer** |
| A9 | Vazio | 2 estados distintos (primeiro uso × com filtro) | idem | **paridade** — trocar só o invólucro por `EmptyState` |

## BLOCO 5 — ARQUIVOS

**A EDITAR** — `resources/js/Pages/StockAdjustment/Index.tsx` (**só este**).

**A REUSAR (não recriar)** — `Components/shared/PageHeader.tsx` (canon v3.8) · `Components/ui/{button,card,input,select,table,dialog}.tsx` · tokens de `cockpit.css` + `foundations.css` (`--fs-*`) · `PageHeaderTabs.tsx` se a fileira de abas entrar.

**NÃO TOCAR** — `aplicar()`/partial reload (A7) · matriz de `permissions` · `router.delete` e endpoints · `Create.tsx` (é a onda 1.6) · `Index.charter.md` e `Movimentacao.casos.md` · qualquer arquivo de outro módulo.

**PARAR SE** — passar de 1 PR/300 linhas · não existir `DataGrid`/`ColumnManager` no `main` (**reportar, não portar na mão**) · as abas exigirem **rota nova** `/estoque/*` (decisão de [W]) · precisar de `<main>` extra (AP9).

## BLOCO 6 — Onda
**1.1 · aba Ajustes · view única.** Escopo fecha em `Index.tsx`. Não abre 1.6 (Novo ajuste) no mesmo PR.

## BLOCO 7 — O que a ancoragem NÃO resolve
1. `scope` no `th` e `aria-hidden` no ícone vêm do **bundle do DS** — sem dono hoje.
2. `<main>` ausente é do **host** do Cowork.
3. Foco visível: inconclusivo por limitação da sonda.
4. `Movimentacao.casos.md` lido só por match — INV-1/4/6 **não verificados**.
5. Charter órfão **A.01** (casos sem charter) — item 9.10 de `cowork-inbox/ponte/04-PENDENTES.md`; não é meu pra criar.
6. Sem **T7** → nada aqui é "0 bug" nem "igual ao design".

## BLOCO 8 — Contrato de tela (ADR 0286)
A criar **no repo**: `prototipo-ui/contrato/stock-adjustment-index.contract.json` — seções (PageHeader → TabBar → Toolbar → DataGrid), copy literal ("Ajustes de estoque", "Novo ajuste", "Valor ajustado", "Motivo do ajuste", "Nenhum ajuste de estoque registrado.", "Nenhum ajuste com filtros atuais.") e estados (vazio-primeiro-uso × vazio-com-filtro × sem-permissão-de-preço).

## BLOCO 9 — DoD por máquina
`prototipo-readiness.mjs` · `cowork-ssot-guard.mjs` (R1/R2/R3) · `cowork-mirror-freshness.mjs --absent-local --check-orfaos --check-refs` · gate `design-memory-gate.yml` verde · `design-diff --compare --check` **só no T7**, prod deployada.

## BLOCO 10 — Placar
Âncora lida no turno: **3 integrais + 1 parcial** · alvo medido: **sim** (2 leituras iguais) · a11y: **7 ✅ · 3 ❌ (DS/host) · 2 ⚠️** · corrigido daqui: **0** · deltas: **6 pedido (A1–A6) · 2 paridade/à frente (A7, A8) · 1 invólucro (A9)** · cor crua no alvo: **0** · autoridade de token: **DS `TabBar` → protótipo → produção** ✅ · escrito no git: **não** (ponte = colar 1× ou `cowork-inbox`/Issue) · pacote `sync/` regenerado: **não** (gerador exige arquivos em disco) · blocos **10/10**.

```
node scripts/design-sync/gerar-payload-partes.mjs --root prototipo-ui/cowork --out sync/ --previous sync/bundle.manifest.json
```
