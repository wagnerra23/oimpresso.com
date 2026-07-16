---
slug: sells-index-dsv6-visual-comparison
title: "Sells — Comparativo visual DS v6 da tela /sells (Index · PR3)"
type: visual-comparison
module: Sells
status: approved
approved_by: wagner
approved_at: "2026-06-03"
date: 2026-06-03
canon_reference: prototipo-ui/ds-v6/gabarito-vendas.html
blade_source: resources/views/sell/index.blade.php (legacy fallback)
inertia_target: resources/js/Pages/Sells/Index.tsx
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0104-processo-mwart-canonico-unico-caminho
  - 0107-emendation-0104-visual-comparison-gate-f3
  - 0114-prototipo-ui-cowork-loop-formalizado
  - 0143-fsm-pipeline-live-prod-marco-2026-05-12
  - 0190-primary-button-roxo-universal-295
  - 0235-roxo-295-canon
refs_prs:
  - "#2170 (tokens --stage-* fundação)"
  - "#2165 (referência DS v6 showcase/receita/gabarito)"
  - "#2181 (reuse-mapping kit c-*)"
---

# Sells /sells — Comparativo visual DS v6 (PR3 · re-skin por token)

> ⚠️ **ERRATA 2026-07-16 — descritor de shell errado (registro preservado, não reescrito).**
> A Região 1 (Layout) abaixo descreve o shell como *"AppShellV2 (sidebar light + topnav)"*. **A sidebar
> é PRETA (dark-fixo) nos dois modos desde 2026-05-05** — já era, na data deste round (2026-06-03).
> Lei vigente: [ADR UI-0023](../_DesignSystem/adr/ui/0023-sidebar-dark-fixo-preto-definitivo-supersede-0019.md)
> (supersede UI-0009/0014/0019, que afirmavam "light" e estavam erradas). O descritor **não alterou o
> veredito** deste round ("Paridade · sem mudança de layout"), por isso o corpo aprovado por [W] em
> 2026-06-03 fica como está — trilha do tempo, não regravação.

> **Natureza do PR3:** NÃO é rebuild. O `Index.tsx` (charter v6, 1805 LOC) já tem **paridade
> estrutural ~95%** com o gabarito — o gabarito foi *derivado* desta tela. O delta DS v6 é
> **migração de cor**: hoje a tela usa classes escopadas `.vd-*`/`.os-*` (paleta própria em
> `sells-cowork.css` + alguns `oklch` crus); o gabarito expressa a MESMA tela consumindo os
> **tokens canônicos DS v6** (`--stage-*`, `--pos/neg/warn(+soft)`, `--origin-*`, `--accent`,
> superfícies `bg→sunken→surface→raised`) com flip claro/escuro de fábrica.
>
> **Referência aprovada:** `prototipo-ui/ds-v6/gabarito-vendas.html` (DS v6 aprovado por [W]
> 2026-06-03 — "já está aprovado, adorei"). **Toggle claro/escuro no próprio arquivo.**
>
> **Tokens já em main:** `--stage-*` (PR #2170) · roxo 295 (ADR 0235). **Reuse-map:** 8/11
> componentes do kit já reusam (PR #2181) — esta tela usa Button/Badge/KpiCard/Segmented já.

## Referência visual (o "screenshot" pra aprovar)

- **Abrir:** `prototipo-ui/ds-v6/gabarito-vendas.html` no browser → botão **◐ Escuro / ◑ Claro** alterna os 2 temas.
- É o alvo pixel. A tela atual `/sells` (prod biz=1) é o "antes".

---

## A. Estrutura (8 dimensões)

| # | Dimensão | Index.tsx atual | Gabarito DS v6 | Decisão port (PR3) |
|---|---|---|---|---|
| 1 | **Layout** | AppShellV2 (sidebar light + topnav) · `.wrap`-equivalente via PageHeader v3 · subnav FOCO/Caixa/Faturamento/Comissão · primary "Nova venda" à direita | top sticky + `.wrap` max-1280 pad 22/28/70 · pg-head h1+sub+subnav · primary à direita | **Paridade.** Manter shell/PageHeader v3. Subnav já existe (ADR 0182). Sem mudança de layout. |
| 2 | **Hierarquia visual** | 1 primary "Nova venda" roxo 295 · h1 22-24px · subnav abas | 1 primary `+ Nova venda` accent · h1 23px/600 · subnav underline-active accent | **Paridade.** Alinhar h1 → 23px/600 + sub 12.5px `--text-3` (hoje varia). Subnav active = `border-bottom 2px var(--accent)`. |
| 3 | **Densidade** | KPIs `gap` + cards `.os-kpi`/`.vd-kpi` · tabela linhas densas | kpis grid 4× gap 12px · c-kpi pad 14/16 r-3 · td pad 11/12 · thead 11/12 | **Paridade c/ ajuste fino.** Casar gap-12 KPIs · td 11px/12px · panel `--r-4`. Persona Larissa 1280 (densa) preservada. |
| 4 | **Iconografia** | lucide-react (Folder/ChevronDown…) + emojis canon Cowork no VdNextActionPanel (override #1641) | dots FSM (sem ícone) · ▲▼ delta · ⌕ busca · ◐ tema | **Paridade.** Manter lucide. FSM = dots `.fsm i` (sem emoji na *linha*; emoji canon fica só no drawer NBA, override mantido). |
| 5 | **Estados visuais** | hover linha (`tr:hover`), sel (linha ativa), checkbox, loading (Inertia::defer skeleton), empty, bulk-on | `tr:hover var(--sunken)` · `tr.sel var(--accent-soft)` · bulkbar slide-in · drawer slide | **Paridade.** Migrar hover/sel pra `var(--sunken)`/`var(--accent-soft)` (hoje `.vd-` cor própria). |
| 6 | **Atalhos teclado** | `⌘K` palette · `?` cheat · `J/K` nav · Enter drawer · `/` busca · `B` favoritar · `F2` PDV | (protótipo só visual — sem JS de atalho além de toggle/seleção) | **Manter 100% os atalhos** (gabarito é só visual; comportamento da tela é superior — não regredir). |
| 7 | **Persistência** | `localStorage[oimpresso.sells.b<biz>.*]` (visao, visao_origem, foco) Tier 0 per-business | `localStorage dsv6.theme` (só tema) | **Manter** persistência da tela. Tema segue o cockpit (não criar chave nova de tema na tela). |
| 8 | **Componentes shared** | PageHeader v3 · Sheet (shadcn) · KpiCard · SellsTabsVisao · SaleSheet · BulkActionBar · SellsTabelaUnificada | c-btn/c-pill/c-kpi/c-tabs/c-stage/c-org/SaleSheet (classes CSS) | **Reusa (PR #2181):** c-btn→Button · c-pill→Badge/StatusBadge · c-kpi→KpiCard · c-tabs→Segmented. **Não criar componente novo** — só re-tokenizar os `.vd-*`/`.os-*`. |

## B. Estado da arte (7 dimensões)

| # | Dimensão | Index.tsx atual | Gabarito DS v6 | Decisão port (PR3) |
|---|---|---|---|---|
| 9 | **Tipografia numérica** | `.os-kpi-value` (mono) · spark · delta up/dn | c-kpi b = 24px mono `-0.02em` · label 10px uppercase tracking .06 | **Alinhar:** value 24px mono · label 10px uppercase `--text-4`. Total tabela `var(--mono)` 600. |
| 10 | **Espaçamento numérico** | gap/pad via `.vd-`/`.os-` | kpis gap 12 · c-kpi pad 14/16 · panel r-4 · td 11/12 | **Alinhar** aos números do gabarito (acima). |
| 11 | **Cores semânticas** | `.vd-*`/`.os-*` rose/emerald/amber/blue **escopados** em `.sells-cowork` (paleta própria) + `oklch` cru no Sparkline | **só token:** `--pos/neg/warn(+soft)` · `--origin-OS/CRM/FIN` · `--stage-*` (FSM) · `--accent(+soft)` | **🎯 NÚCLEO DO PR3.** Migrar `.vd-*`/`.os-*` → tokens canônicos. Status pills (paga=`--pos-soft` · pendente=`--warn-soft` · faturada=`--accent-soft` · cancelada=`--neg-soft`). FSM dots `--stage-emerald/green`. Origem `--origin-*`. Ageing `--pos/warn/neg`. **Zero `oklch` cru** (Sparkline → token). |
| 12 | **Microinterações** | hover transition · drawer slide · bulk slide | `tr transition .12s` · bulkbar cubic-bezier slide · sheet `.26s` · backdrop fade | **Paridade.** Casar timings (já próximos). |
| 13 | **Referência aprovada** | — | `gabarito-vendas.html` (✅ [W] aprovou DS v6) | **OK** — referência existe e está aprovada. |
| 14 | **Benchmarks externos** | (tela list+detail) | Linear (list density) · Stripe Dashboard (status pills) · Shopify Admin (bulk bar) | Manter benchmark list+detail. Tela já está no nível; DS v6 só harmoniza cor. |
| 15 | **Persona priorização** | Larissa 1280 ROTA LIVRE · Wagner WR2 · balconista | densa 1280, KPIs grandes, status legível | **Top 3:** (1) flip claro/escuro de fábrica (hoje a tela não flipa bem por usar paleta própria); (2) status pills legíveis em 1280; (3) FSM dots com `--stage-*` (esteira clara). |

---

## Veredito + escopo do PR3

- **Paridade estrutural ~95%** — nenhuma reestruturação de layout/comportamento. Atalhos, drawer, bulk emit, saved views, Tier 0, anti-hooks LGPD: **preservados integralmente**.
- **Delta = re-skin por token** (dimensão 11 é o núcleo): migrar os escopos `.vd-*`/`.os-*` de `sells-cowork.css` (e correlatos) pra consumir os **tokens canônicos DS v6**, eliminando `oklch` cru → a tela passa a flipar claro/escuro pelo cockpit e bate pixel com o gabarito.
- **Sem componente novo** — reuse-map (PR #2181) cobre; os 3 gaps Tier-0 (`c-id`/`c-tl`-unificada/`c-nba`) **não** aparecem nesta tela.
- **Risco:** baixo-médio. É CSS/token, não lógica. Gates: stylelint (zero `oklch` cru / hex novo), Pest browser snapshot (ADR 0108), PR UI Judge, visual-regression. Override de emoji canon Cowork no `VdNextActionPanel` (#1641) **mantido**.

### Arquivos que o PR3 tocaria (estimativa)
- `resources/css/sells-cowork.css` (+ `sells-cowork-show.css`/`-edit.css` se compartilham `.vd-*`) — re-tokenização.
- `resources/js/Pages/Sells/Index.tsx` — só onde há `oklch` cru inline (ex: `Sparkline color=...`) → token; classes já vêm do CSS.
- (possível) `_components/Vd*.tsx` que tenham cor inline.

---

## ⛔ GATE — aguardando [W]

**Status `draft`.** Por ADR 0107/0114, **nenhum Edit em `Index.tsx`/CSS antes de você aprovar o SCREENSHOT.**

**Pra aprovar:** abra `prototipo-ui/ds-v6/gabarito-vendas.html` (toggle claro/escuro) e confirme:
1. É esse o alvo visual pro `/sells`? (sim → vira `status: approved`)
2. Confirma o escopo "re-skin por token, sem rebuild, sem tocar comportamento"?
3. Algum ajuste nas decisões acima (ex: manter alguma cor específica, ou incluir os `-soft` chroma)?

Após teu OK eu: atualizo `status: approved` + assino, abro PR3 (worktree off main, single-intent CSS-token), rodo CI + screenshot real, e só mergeio com teu de-novo-OK no screenshot pós-impl (F3 design-critique ≥80).
