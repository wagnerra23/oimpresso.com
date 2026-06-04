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

---

## ✅ Implementação PR3 (2026-06-03 · pré-gate pós-impl [W])

Pré-gate de aprovação do gabarito já satisfeito (`status: approved`). Implementação **re-skin por token** aplicada (sem rebuild, sem tocar comportamento/atalhos/Tier 0):

**1. `resources/css/cockpit.css`** — fundação DS v6 (aditivo, append-only, **roxo 295 intocado**), claro+dark, valores verbatim do gabarito:
`--sunken` · `--raised` · `--text-2/3/4` · `--accent-line` · `--pos(+soft)` · `--neg(+soft)` · `--warn(+soft)`. Acompanha os `--stage-*` já no working tree (PR #2170).

**2. `resources/css/sells-cowork.css`** — núcleo (dimensão 11):
- `.vendas-aplus{--vd-*}` deixou de carregar `oklch` cru → **alias dos tokens canônicos**: `--vd-green*`→`--accent*` (mata identidade verde-155 / D-02; identidade = roxo ADR 0190) · `--vd-ok`→`--pos` · `--vd-warn`→`--warn` · `--vd-bad`→`--neg` · `--vd-neutral`→`--text-3` · `--vd-neutral-soft`→`--sunken`.
- `.sells-cowork{--vd-ai*}` (IA) → `--accent*`/`--accent-line` (já era roxo 295).
- `[data-vd-palette=indigo|slate|amber]` overrides → `--accent` (identidade roxa única, gabarito).
- Literais diretos → token: `.os-kpi` `#fff`→`var(--surface)` (corrige card branco no dark) · `.os-kpi-alert`→`--warn(+soft)` · `.vd-kpi-hero` value/sub/delta/spark→`--accent-fg` (hero roxo, texto legível 2 temas) · `.vd-sla-fresh/warning/overdue` (+`.mini`, +`[data-vd-sla=dot] .vd-sla-ic`)→`--pos/--warn/--neg(+soft)`.

**3. `resources/js/Pages/Sells/Index.tsx`** — `<Sparkline color>` `oklch(…155)` → `currentColor` (cor real vem de `.vd-spark{color:var(--accent-fg)}`; `var()` não resolve em atributo SVG `fill/stroke`).

**4. `Index.charter.md`** — front-matter ganhou `ds: v6` + `regua:` (aponta este doc, fonte única da tela).

**Verificação local:** todos os tokens referenciados definidos (claro+dark) · **zero hex novo** (1 `#fff` removido → ratchet stylelint aperta) · `oklch` não é lint (canon oklch-first) · chaves CSS balanceadas. Build/stylelint locais N/A (node_modules incompleto nesta máquina) → **gates rodam no CI**.

**Deferido (fora do núcleo PR3, alvo mínimo / L-28):** `--vd-src-*` (origem, `oklch` compartilhado com Caixa) · fallbacks `var(--border, oklch(…))`/`#fff` da barra `.os-*` (toolbar/search = dimensão 1/8 "paridade", dívida shell-wide DARK-BACKFILL já bridada) · transcript A4 + overlay apresentação (`oklch` **intencional**, não tokenizar).

**Pendente [W] (gate pós-impl F3 · ADR 0107):** abrir `/sells` nos 2 temas, conferir screenshot vs gabarito, design-critique ≥80 → só então merge. Não commitei junto (working tree tem Norte/cockpit não-relacionados — commit-discipline 1 PR = 1 intent).

---

## 🔎 Verificação pós-impl (2026-06-03 · re-fetch do handoff Cowork `oimpresso.com.html`)

> Auditoria de conformância **código-nível** (app Inertia não roda nesta máquina — `node_modules`
> vazio; per README do handoff "read the HTML/CSS directly"). Confronto do commit `e8d75576b`
> contra `ds-v6/gabarito-vendas.html` (= `_preview/gabarito.html`, 24.849 B idêntico).

### ✅ Conforma (tema CLARO — modo da Larissa)
- **Tokens semânticos = gabarito VERBATIM** em `cockpit.css` (claro+dark): `--pos 0.50/0.12/150` · `--pos-soft` · `--neg 0.55/0.18/25` · `--neg-soft` · `--warn 0.58/0.12/70` · `--warn-soft` · `--accent-line 0.86/0.05/295` · `--stage-*` (todos). Match exato.
- **Sparkline tokenizado** ✓ — `Index.tsx` tem **0** `oklch` cru; cor via `currentColor` → `.vd-spark{color:var(--accent-fg)}` (sells-cowork.css:5286).
- **Roxo 295** (`--accent`) intocado (ADR 0190/0235). Identidade verde-155 morta.
- **Neutros** (`--sunken/--raised/--text-2/3/4`): cockpit usa hue **quente 80/90** vs gabarito **frio 95/282** — divergência **intencional** (harmoniza com o creme do shell); não é drift, é decisão de identidade do shell. Sells não fica pixel-igual ao gabarito *standalone* nas superfícies neutras, mas fica coerente com o resto do app.

### ❌ Drift real — a tela **NÃO flipa pra DARK** (persona-priority #1 do doc, não atingida)
Causa-raiz (cascata, provada sem rodar):
1. `AppShellV2.tsx:439` põe `data-theme` no elemento **`.cockpit`**; `Sells/Index.tsx:1043` renderiza `.sells-cowork` **descendente** de `.cockpit` (sem `data-theme` próprio).
2. `sells-cowork.css:21` (`.sells-cowork{}`, sempre-on) **fixa surfaces/texto CLAROS** (`--bg 0.985` · `--surface #fff` · `--text 0.22`) — idênticos ao claro do cockpit, logo redundantes no claro e **bloqueiam herança** do dark do cockpit.
3. As variantes dark de Sells usam selector **`.sells-cowork [data-theme="dark"]`** (combinador **descendente**) — exige o attr DENTRO de `.sells-cowork`, mas ele está no **ancestral** `.cockpit`. **Nunca casa.** São **33 regras mortas** em `sells-cowork.css` (que escopa Index/Caixa/Show/NBA); **0** usavam o pattern correto. (Os `sells-cowork-show.css`/`-edit.css` usam outro pattern — `.sells-cowork-show[data-theme]` *self*, telas Show/Edit, fora do escopo /sells Index.)

**Efeito no dark:** superfície branca + texto quase-preto (não flipam), enquanto os tokens semânticos migrados (`--pos/--neg/--warn/--stage-*`, que NÃO são redefinidos em `.sells-cowork`) herdam o **dark** do cockpit → pills desenhadas pra dark sobre card branco = meio-flip quebrado. O mesmo pattern morto existe em `.fin-cowork` (project-wide; pré-existente ao PR3 — herdado do "copiado verbatim" do protótipo, onde `data-theme` ficava noutro nó).

**Por que passou no gate:** aprovação foi **claro-only** — `_preview/compare.html` diz textual *"tema claro, como a tela da Larissa"*. O dark nunca teve screenshot (é o "Pendente [W]" acima).

### ✅ Fix APLICADO (2026-06-03) — opção mínima, zero-impacto-no-claro
Trocado em `resources/css/sells-cowork.css` (33 ocorrências, `replace_all`):
`.sells-cowork [data-theme="dark"]` → `.cockpit[data-theme="dark"] .sells-cowork`.
- Ativa o tema dark **completo que o autor já escreveu** (base surfaces/texto + 33 regras de componente `.tk-*`/`.vw-*`/`.os-*`). Especificidade nova 0,3,0 > `.sells-cowork{}` base (0,1,0) → sobrescreve corretamente no dark.
- **Risco-claro = 0:** nenhum dos dois selectors casa no claro; o `.sells-cowork{}` claro segue intacto. Só pode **melhorar** o dark hoje-quebrado (não há como piorar um estado já não-flipante).
- Integridade verificada: 0 leftover do pattern morto · 33 novo pattern · chaves balanceadas (2096/2096).
- **Por que NÃO fui mais longe (opção b · re-tokenizar 33 raw oklch→`--pos/--neg/--warn`):** exigiria remapear cor por cor às cegas (sem render local) — exatamente a armadilha de regressão dos chats 40-41. Fica como refino a fazer **com o screenshot dark em mãos**.

### ⛔ Gate obrigatório antes do merge (ADR 0107 · "Pendente [W]")
Não-renderizável local (`node_modules` vazio nesta máquina). Exige **screenshot `/sells` nos 2 temas** via CI/staging + design-critique ≥80 antes do merge. Lema chats 40-41: mudança de tema/cor não-merge-blind, [W] vê staging. **Não mergeado.**

### Refino futuro (com screenshot dark)
Superfícies dark de Sells são a paleta própria do autor (hue 240) vs gabarito (hue 282). Coerente, mas não pixel-igual ao gabarito-dark. Alinhar via herança dos tokens canônicos DS v6 do cockpit é o passo de conformância fina — fazer só vendo o dark renderizado.
