# Office Impresso — Design System

> A brand & UI kit for **Office Impresso** (a.k.a. `oimpresso`), a full Brazilian **ERP nível TOTVS** for gráficas, comunicação visual e oficinas — orçamento, produção, fiscal, estoque, financeiro e BI numa plataforma só. Ponto eletrônico (CLT, Portaria MTP 671/2021) é **um dos módulos** da suíte, não o produto.

This folder is everything an agent (or a designer) needs to mock, prototype or extend the product without touching the running Laravel app.

---

## What this is

`oimpresso` (also written **OI Impresso**) is an Inertia + React + Tailwind 4 product riding on top of **Laravel 13.6 + UltimatePOS v6**. The marketing site, the operational app, and **todos os módulos do ERP** (CRM/Clientes, Orçamentos, Vendas/PDV, Produção/OP, Comunicação visual, Oficina, Estoque/Compras, Fiscal NF-e, Financeiro, RH/Ponto, BI) all share **one single visual language**: shadcn/ui's `new-york` variant on the `slate` base color, with shadcn's default blue (`hsl(221.2 83.2% 53.3%)`) acting as the brand primary.

The brand has a **real graphic logo** (vector, fornecido pelo cliente): an **isometric CMYK cube** — four process-colour sub-cubes (amarelo, magenta, ciano/azul, cinza) — paired with the **“Office Impresso”** wordmark (“Office” em branco, “Impresso” em azul de marca) e a assinatura “Software de Gestão para Comunicação Visual”. Assets em `assets/brand/` (`logo-full.svg`, `logo-mark.svg`, `brand-bg.png`); the `Logo` DS component renders the cube as scalable inline SVG. The brand color is **purple** (`oklch(0.55 0.15 295)`, hue 295 — "roxo médio universal", ADR 0190/0235), and the operational app is set in **IBM Plex Sans** (mono: IBM Plex Mono); the marketing layer falls back to the system sans stack.

> **This is DS v6** — the git source-of-truth design system (ADR 0239 "DS git SSOT", ADR 0249 "DS v6 naming", ADR 0300 token errata). Tokens are authored as **DTCG JSON** (`resources/css/tokens/*.tokens.json`) and compiled by **Style Dictionary** into CSS. Color space is **OKLCH** throughout. An earlier version of this kit captured the legacy shadcn-slate-**blue** tokens from `inertia.css`; those were superseded — the canonical primary is purple. The full token set (Tailwind `@theme`, the `foundations.css` Type RAMP, and the `.cockpit` shell palette) is mirrored verbatim in `colors_and_type.css`.
>
> **Living mirror, not a frozen snapshot.** This claude.ai/design project is a **living mirror** of the git DS, kept in sync by the `/design-sync` loop — **pull** (design→git, with triage) for authoring, **push** (git→design) to re-mirror what git canonized — with a drift sentinel (`ds-mirror-drift`) that flags any divergence. **git remains the SSOT** (ADR 0239); the mirror never competes with it. The "DS v6 frozen snapshot" (`prototipo-ui/cowork/ds-v6`, jun/2026) is kept only as a historical reference. See the transition proposal `memory/decisions/proposals/2026-07-09-ds-transicao-congelado-para-vivo-git-ssot.md` (+ ADR 0315).

### Products in scope

| Surface | Audience | UI kit |
| --- | --- | --- |
| **Marketing site** (`/`, `/pricing`, `/c/contact-us`, etc.) | Prospects evaluating the ERP | [`ui_kits/site/`](ui_kits/site/) |
| **App shell** (`/home`, `/ponto/*`, `/financeiro/*`, …) | Logged-in operators, owners, HR | [`ui_kits/app/`](ui_kits/app/) |

Os módulos do ERP (CRM, Orçamentos, Vendas/PDV, Produção, Comunicação visual, Oficina, Estoque, Compras, Fiscal, Financeiro, RH/Ponto, BI) all sit inside the App Shell — same sidebar, same `ModuleTopNav` pattern, same `KpiCard` / `StatusBadge` / `PageHeader` primitives. Ponto é um módulo entre vários, com vocabulário próprio (marcação, intercorrência, banco de horas).

---

## Sources

Everything here is derived from one repository the user attached:

- **GitHub:** [`wagnerra23/oimpresso.com`](https://github.com/wagnerra23/oimpresso.com) @ `main` (commit `f197e39abc` — kept fresh by the `/design-sync` push loop; git is SSOT)
- Key files read:
  - `CLAUDE.md` — primer for AI agents, project context (PT-BR)
  - `AGENTS.md`, `README.md`, `package.json`, `components.json`
  - `resources/css/inertia.css` — canonical token sheet (this is the source of truth)
  - `resources/js/Components/ui/*.tsx` — shadcn primitives (button, card, badge, input, alert, dialog, select, avatar, separator)
  - `resources/js/Components/Site/*.tsx` — marketing site (Hero, SiteHeader, FeatureGrid, PricingTiers, DashboardMockup, etc.)
  - `resources/js/Components/shared/*.tsx` — operational primitives (PageHeader, KpiCard, StatusBadge, ModuleTopNav, EmptyState)
  - `resources/js/Components/shared/ponto/*.tsx` — Ponto-domain widgets (PresenceStrip, ActivityFeed, AlertInbox)
  - `resources/js/Layouts/AppShell.tsx` — sidebar accordion + topbar layout

---

## Index

- **`README.md`** — this file (start here)
- **`colors_and_type.css`** — every CSS variable: raw tokens (slate scale, accents, type scale, radii, shadows) + semantic tokens (`--fg-1`, `--surface-1`, `--status-success-fg`, …) + element styles (`h1`, `p`, `.tabular`, `code`, …). Light + dark.
- **`SKILL.md`** — Claude Skill manifest — load this folder as a skill in any agent.
- **`components/`** — compiled DS components (`window.OfficeImpressoPontoWR2DesignSystem_019dd0`):
  - **Form primitives** — `Button` (primary/ghost/danger · sm/default/lg · icon · kbd), `Input`/`Select`/`Textarea` (each with uppercase label + help + inline error + readonly/disabled canon), `Switch` (label+sublabel), `Checkbox`, `RadioGroup` (column/row). Focus ring = accent + soft halo; invalid = destructive border + ring.
  - **Overlays** — `Drawer` (+`DrawerSection`) right-side detail/form panel (PT-02): scrim + header badge + title/subtitle + scrolling sections + sticky footer. `Modal` centered confirmation dialog (PT-04, not for detail). Both controlled via `open`/`onClose`.
  - **`StatusBadge`** — domain-mapped pill. `kind` (ERP): `documento` · `fiscal` (NF-e) · `os` · `prioridade` · `payment` · `sla` · `atendimento` · `frescor` · `tipo`; `kind` (módulo Ponto): `intercorrencia` · `rep`. `frescor` takes an optional `rel` (relative-time suffix) and renders a dotted soft pill — recente=verde, fresc=âmbar, frio/distante=vermelho (the Clientes/CRM look the user prefers over production). `tipo` (pj/pf) is a mono pill — PJ violeta, PF esmeralda.
  - **`Avatar`** — initials on a deterministic color (DS v4 canon: hash → 8 palette tokens `--av-c1..c8`). `size` sm/md/lg, no photos.
  - **`TagChip`** — lowercase category chip with a fixed semantic palette (varejo/atacado/corporativo/evento/parceiro/agencia/governo/vip/reincidente); `removable` adds an ✕.
  - **`KpiCard`** — KPI tile: tinted tones (`success/warning/danger/info`) or dark `hero` plate with `spark` sparkline + `unit`.
  - **`KpiFilterCard`** — clickable KPI tile used as a filter toggle (Clientes/CRM strip): icon tile + label + value + sub, `selected` draws the accent ring.
  - **`PageHeader`** — flat index header (DS v4, slot 1 of PT-01): title + tabular `stats` (with `tone` danger/warn) + right-aligned `actions`.
  - **`FsmStepper`** — canonical pipeline indicator: `variant="inline"` (6 dots + current mono label, for table cells) or `"full"` (numbered circles + lines, for drawers). `hue` sets the OKLCH pipeline color (220 blue / 295 roxo).
  - **`DataTable`** — dense balcão table (DS v4): `columns` + `rows` with row `state` (urgent rail / selected bg / archived dim), `mono`/`align:right` cells, and `{primary, sub}` two-line cells. Compose Avatar / FsmStepper / StatusBadge / TagChip as cell nodes.
  - **`Toast`** — fleeting confirmation pill: `tone` default/ok/warn/danger, optional `icon` + `kbd` hint.
  - **`Skeleton`** — shimmer loader: `variant` text/title/caption/avatar/avatar-md/row/card, `width`, `count`.
  - **`EmptyState`** — contextual zero/error state: `variant` default/first/no-results/no-perm/offline/done/filtered/error + `icon`/`title`/`description`/`action` (always WHY + WHAT).
  - **`BulkBar`** — floating multi-select action bar: `count` + `actions` (with `tone:'danger'`) + `onClose`.
  - **`FilterChip`** — active, removable filter pill: `label` + optional `value` + `onRemove`.
  - **`TaskCard`** — Board/Kanban card (ProjectMgmt canon ADR 0070): `task` with `displayId`/`title`/`priority` (p0–p3)/`module`/`owner`/`estimateH`/`storyPoints`/`due`/`isBlocked`/`isOverdue`; `selected` ring; draggable.
  - **`BoardColumn`** — a Kanban column: `status` (backlog/todo/doing/review/done/blocked/cancelled) colors the top border + label, auto `count`, `onDrop` target, empty "vazio" state. Compose TaskCard children → full Board.
  - **`TabBar`** — module sub-tabs with counts (DS v4 moduletopnav, slot 2 of PT-01): underline-active in accent, mono counters — the Clientes "Todos/Clientes/Fornecedores…" row. Pairs with PageHeader.
  - **`Breadcrumb`** — current-page hierarchy trail: `items` `[{label, href?}]`, last is bold current.
  - **`Pagination`** — prev/next + numbers + ellipsis + optional "N–M de T" meta: `page`/`pageCount`/`onChange` (+ `total`/`pageSize`).
  - **`AppSidebar`** — rail de navegação agrupada do shell operacional: switcher de empresa (glyph da mira), grupos com contadores/badges + rodapé "Conformância DS". Nav canônico embutido; passe `active` (key do item) pra destacar a tela. Usado nos templates do nível Norte pra parar de duplicar a sidebar.
  - **`Command`** (premium) — paleta ⌘K: filtro ao vivo + navegação por teclado (↑/↓ · ↵ · esc) + focus-trap. `open`/`onClose`/`groups` (`[{label, items:[{id,label,hint?,kbd?,icon?,onSelect?}]}]`). Ligada na tela Clientes (⌘K abre).
  - **`Chart`** (premium) — data-viz compacto: `type` `area`/`line`/`bar`, `data` (`number[]` ou `{label,value}[]`), tooltip no hover, `color`/`height`/`highlightLast`/`formatValue`. Substitui as barras/sparklines bespoke das telas.
  - **`DataTablePro`** (premium) — grid avançado: header fixo (sticky), **resize de colunas** por arrasto, ordenação + seleção (checkbox) internas, densidade `comfortable`/`compact`, estados urgent/archived. `columns`/`rows`/`height`/`selectable`/`onRowClick`/`onSelectionChange`/`defaultSort`.
  - **Daily-driver overlays & feedback** (os primitivos de uso diário que faltavam — ADR DS v6):
    - **`Tooltip`** — dica contextual sobre hover **e** foco (acessível por teclado). `content`/`side` top/bottom/left/right/`kbd?`/`delay?` envolvendo qualquer `children`. Balão escuro com seta, sem dependências.
    - **`DropdownMenu`** — menu de ações ancorado a um gatilho: abre no clique, fecha no esc / clique-fora / seleção, navegação por teclado (↑/↓ · ↵). `items` (`[{id,label,icon?,kbd?,tone?:'danger',disabled?,separator?,onSelect?}]`) + `trigger` (node ou render-fn) + `align`/`width`.
    - **`Alert`** — banner inline no fluxo da página (aviso fiscal, LGPD, intercorrência, sucesso): fundo tintado 6% + borda 22% no tom — `tone` info/success/warn/danger, `title`/`children`/`icon?`/`action?`/`onClose?`. Nunca pastel sólido.
    - **`Progress`** — progresso determinístico: `variant` `bar` (linha) ou `ring` (anel SVG), `value`/`max`/`tone`/`label`/`showValue`/`size`/`formatValue`. Pra fechamento de mês, banco de horas, upload.
    - **`PeriodBar`** — barra de período para telas de consulta: segmented de presets (Dia/Semana/Mês — janelas rolantes) + campos De/Até (reusa `DatePicker`) sempre visíveis; clicar um preset preenche o intervalo, editar um campo comuta pra `custom`. `value`({from,to,preset})/`onChange`/`presets`/`label`. Padrão do "período" que aparece em toda consulta (financeiro, BI, relatórios).
    - **`DatePicker`** — campo de data com calendário PT-BR (dd/mm/aaaa): grade do mês + navegação + hoje destacado + dia selecionado em accent, `min`/`max`. `value`(Date|ISO|null)/`onChange`/`label`/`placeholder`/`disabled`. Crítico no ERP (ponto, vencimentos, datas em toda tela).
  - **Print-craft primitives** (DS print-craft — a identidade "impresso" como sistema, não enfeite. Grupo "Print-craft" na aba Design System):
    - **`ProofFrame`** — card emoldurado como folha de prova: marcas de corte nos 4 cantos + grid de prova sutil. `cropMarks`/`grid`/`padding`/`radius`. Envolve qualquer conteúdo (ex: o card de prova do PT-07).
    - **`Dimension`** — cota técnica: linha de medida com caps + label mono central. `value` (ex `"3.000 mm"`), `orientation` `h` (preenche largura) / `v` (preenche altura, precisa de pai flex/stretch), `surface` (bg do chip).
    - **`ProofStrip`** — tira de controle de prova: `kind` `density` (degradê de cinzas, calibração) ou `cmyk` (4 tintas de processo). `steps`/`height`/`swatch`.
    - **`RegistrationMark`** — mira de registro: o glyph-assinatura do sistema (anel + 4 ticks). `size`/`color`/`strokeWidth`. Use como marcador de seção, motivo de empty-state ou glyph da marca.
    - **`PlacaVeiculo`** — placa veicular brasileira (módulo Oficina Auto): `padrao` `mercosul` (faixa azul + bandeira) / `antiga` (cinza), `size` sm/md/lg, `placa` (formata e limita a 7 chars), `uf` na faixa, `categoria` particular/comercial/oficial/especial (cor do caractere). Reusável em consulta de veículo, histórico, etiqueta de OS.
- **`assets/`** — raster assets imported from the repo (logo placeholder, hero photo) — see ICONOGRAPHY below.
- **`preview/`** — preview cards for the Design System tab. Don't read these as documentation — read this README.
- **`ui_kits/`**
  - **`site/`** — marketing surfaces (header, hero, features, pricing, footer)
  - **`app/`** — logged-in app (sidebar, page-header, KPI grid, status badges, ponto presence strip, activity feed, empty state)

---

## CONTENT FUNDAMENTALS

The voice of `oimpresso` is **a primary brand asset** alongside the CMYK-cube logo. There is no proprietary type, so tone carries much of the personality.

### Language

- **Português brasileiro, sempre.** UI labels, errors, marketing copy, even ADRs. (Exception: code identifiers — class names, methods — can be English; domain names stay PT.)
- Short sentences. Em-dashes (`—`) used liberally to break thought, not commas.
- Conversational. The product talks **to** you, not **at** you. Treats the reader as a small-business owner, not a corporate buyer.

### Casing

- **Sentence case** for everything: page titles, button labels, menu items.
  - ✅ "Aprovações pendentes", "Começar grátis", "Espelho de ponto"
  - ❌ NOT "Aprovações Pendentes" (Title Case is forbidden)
- **UPPERCASE TRACKED** only for tiny KPI labels and section eyebrows (`text-xs uppercase tracking-wider`). Example: "TUDO NUM LUGAR" above the FeatureGrid heading.
- Status enums like `REP-P`, `NF-e`, `NFC-e`, `MDF-e` keep their canonical mixed casing — they're brand-style legal artifacts.

### Pronouns

- The product addresses the user as **você**, never `tu`, never `o senhor`. ("Pra quem orça, imprime, monta e entrega.")
- First person (`nós`) only when the company is making a promise: "Não cobramos setup nem fidelidade." Otherwise the subject is the product or the user.

### Voice samples (lifted from `Hero.tsx`, `PricingTiers.tsx`, `FeatureGrid.tsx`)

> **O ERP pra quem orça, imprime, monta e entrega.** Cálculo automático por m², ordem de produção em tempo real e fechamento fiscal sem retrabalho. PDV, NF-e, estoque, ponto, financeiro e BI integrados — em uma plataforma só.
>
> Sem cartão de crédito · Suporte humano em português.

> **Pra gráfica, varejo ou serviço que precisa de tudo, sem gambiarra.**

> **Oito módulos. Uma plataforma.** Pare de pular entre 5 sistemas pra fechar o mês. Do orçamento à entrega — o oimpresso integra a operação de ponta a ponta.

> **Preços em reais (R$). Não cobramos setup nem fidelidade. Cancele quando quiser.**

Notes on the sample:

- "Pra" instead of "Para" — colloquial; deliberate.
- "Sem gambiarra" — slang for "no hacks/duct-tape". Aggressive in marketing, never in operational copy.
- Concrete features named ("PDV, NF-e, estoque, ponto, financeiro e BI") — never abstractions like "powerful tools". The reader is technical.
- Negative framing of competitors ("pular entre 5 sistemas") rather than abstract benefits.

### Domain idioms (use the right word)

| Concept | Right | Wrong |
| --- | --- | --- |
| Time-clock punch | **marcação** | "ponto", "registro de ponto" |
| Edit/correction request | **intercorrência** | "ajuste", "correção" |
| Time-bank | **banco de horas** | "h-bank" |
| Employee | **colaborador** | "funcionário", "employee" |
| Sale point | **PDV** (caixa) | "checkout", "register" |
| Production order | **OP** (ordem de produção) | "WO", "ticket" |
| Per-square-metre pricing | **cálculo por m²** | "area pricing" |

### Emoji

- Used **sparingly and only on marketing surfaces**, as inline icons inside large feature cards (FeatureGrid uses 📐 🏭 🛒 📦 🧾 ⏱️ 💳 📊).
- **Never** in operational app screens, never in legal/fiscal copy, never in error messages, never in section headers. The app uses Lucide icons exclusively.

### Numbers, dates, money

- BRL: `R$ 12.480` (period as thousands separator, comma as decimal, single space after `R$`).
- Percent change: `+12%` `-3,5%` (with sign, comma decimal).
- Dates: `dd/mm/aaaa` operational, `15 de outubro` long form.
- Time: `08:42` (24-hour, no AM/PM, monospaced, tabular-figures).
- Big numbers in marketing: write them as round Portuguese phrases — "mais de 200 lojas", not "200+".

### Disclaimers & legalese

Compliance copy cites the law literally — `Art. 66 CLT`, `Portaria 671/2021 Anexo I`, `LGPD Art. 7º`. Never paraphrase the article number.

---

## VISUAL FOUNDATIONS

### Colors

- **Primary:** **roxo (purple)** `oklch(0.55 0.15 295)` — hue 295, the canon accent (ADR 0190). Used as the main saturated color in app chrome: primary buttons, sidebar active item, links, page-header icon plate. The cockpit Tweaks panel lets users re-hue the accent live (default 295). NOTE: the legacy `inertia.css` once shipped shadcn-blue here — that is superseded by DS v6.
- **Neutrals:** warm-grey OKLCH scale (hue \~80-90, e.g. `--text oklch(0.22 0.01 80)`, `--bg oklch(0.985 0.003 90)`). The `.cockpit` shell uses `--bg / --bg-2 / --surface / --border / --text / --text-dim / --text-mute`.
- **Semantic accents** are reserved for **operational status** (OS, fiscal, pagamento, ponto) and dashboard KPIs:
  - `emerald-500/600` → "Aprovada", "Pago", "Presente", positive delta.
  - `amber-500/600` → "Atrasado", "Parcial", warning.
  - `rose-500/600` (= `--destructive`) → "Rejeitada", "Erro", overdue.
  - `blue-500/600` → "Aplicada", "Processando" (informational).
- Surfaces are **almost flat**. Cards live on `--surface-1` (white in light, `slate-800-ish` in dark) with a 1px border in `--border`. There is no layered glassmorphism, no aurora gradient, no neon.

### Type

- **Operational app: IBM Plex Sans** (`--font-sans` inside `.cockpit`), loaded as a webfont. Mono: **IBM Plex Mono** — used for time, codes (REP-P, REP-C), `.env` snippets. The marketing / Tailwind layer falls back to the system sans stack (`ui-sans-serif, system-ui, …`).
- **Type RAMP** (single source of font sizes, `foundations.css` → `--fs-1..9`): 10.5 / 11.5 / 12.5 / 13.5 / 15 / 18 / 22 / 28 / 38 px. Body is `--fs-4` (13.5px); page h1 is `--fs-7` (22px); hero number is `--fs-9` (38px). The `<Text>` primitive maps `size` 1:1 onto this ramp.
- KPI / time values get `font-feature-settings: "tnum"` and `tabular-nums` so columns of numbers don't dance.
- Display sizes only appear on the marketing site (Hero `text-6xl 60px`, leading 1.05, tracking-tight). Operational app caps at `text-2xl` (24px) for `PageHeader`.

### Spacing & rhythm

- Tailwind's default 4-px scale.
- Container max-width: **`max-w-7xl` (1280px)** for marketing pages, full-width within the App Shell main column (`md:w-[calc(100vw-16rem)]` after the 256-px sidebar).
- Section vertical rhythm on marketing: `py-20 sm:py-28` for content sections, `py-16 sm:py-20` for pricing.

### Backgrounds

- **No full-bleed photography on app surfaces.** Marketing has *one* background motif: a soft radial-gradient bloom of the primary at 8% opacity in the top-right of the Hero (`bg-[radial-gradient(ellipse_at_top_right,_var(--color-primary)_0%,_transparent_55%)] opacity-[0.08]`). That's the entire "background flair" budget.
- Repeating patterns: none.
- Stock photography: none. (A legacy hero photo `assets/home-bg.jpg` existed but was unused by any page and has been removed.)

### Animation

- **`framer-motion`** is the only motion library. Default duration is `0.6s` for hero entries, `0.45s` for cards, `0.15s` stagger.
- Easing: framer-motion default cubic.
- All animations respect `useReducedMotion()` — duration drops to 0 when the user has reduced-motion enabled. Honor this in every new component.
- No bounces, no spring overshoot, no parallax. Slide-up + fade is the only entry pattern.
- Hover scale on Pricing tiers is 1.02 (very subtle). Avatars in `PresenceStrip` go to 1.10 on hover.

### Hover states

- Buttons: opacity drop on the primary, e.g. `hover:bg-primary/90`. Outline buttons swap to `hover:bg-accent`.
- Links: underline appears (`hover:underline`, `underline-offset-4`).
- Cards in a grid: border tints to `hover:border-primary/30` or `/50`, plus `hover:shadow-lg`.
- Sidebar nav items: `hover:bg-accent hover:text-accent-foreground`.

### Press / focus

- No scale-down on press.
- **Focus ring is universal:** `focus-visible:ring-[3px] ring-ring/50` + `focus-visible:border-ring`. Honor it everywhere — accessibility is non-negotiable.
- `aria-invalid` triggers `border-destructive` and a destructive-toned ring.

### Borders

- Always **1px solid `--border`** (slate-200 in light, slate-700 in dark).
- Never colored borders except for the **highlighted pricing tier** (`border-primary`) and the **selected KPI filter** (`border-primary ring-1 ring-primary/40`).
- No double borders, no inset borders.

### Shadows

- App primitives (Card, Input, Button outline) use `shadow-xs` or `shadow-sm`.
- Marketing CTAs and the highlighted pricing tier use `shadow-2xl shadow-primary/10` — the shadow is *tinted* with 10% primary, never neutral grey.
- Hover: cards step up to `shadow-lg`. Never `shadow-2xl` on hover.

### Transparency, blur

- Sticky `<SiteHeader>` uses `bg-background/80 backdrop-blur supports-[backdrop-filter]:bg-background/60`. This is the **only** blur in the system.
- Status badges with semantic tone use a 5–10% tinted background — `bg-emerald-500/5 border-emerald-500/20` — never a solid pastel.

### Corner radii

- `rounded-md` (6px) — Buttons, Inputs, status pills inside table rows.
- `rounded-lg` (8px) — small cards inside larger ones, KPI tile inner.
- `rounded-xl` (12px) — `<Card>` (canonical), `<KpiCard>`, `<PresenceStrip>`, `<ActivityFeed>`, sidebar feature blocks.
- `rounded-2xl` (16px) — Pricing tier cards.
- `rounded-3xl` (24px) — soft glow plate behind the hero mockup.
- `rounded-full` — Avatars, badges, presence dots, status indicators.

### Cards — the canonical anatomy

A card is:

1. `bg-card` background.
2. `1px border-border` border.
3. `shadow-sm` resting shadow.
4. `rounded-xl` corners.
5. Default `p-6` padding (`px-6 py-6` via the shadcn `<Card>` shell, `p-4` for compact cards in dense grids like `<KpiCard>`).
6. No drop-shadow inside, no inner glow, no gradient fill.

### Layout rules

- The **App Shell** is fixed: a 256-px (`w-64`) vertical sidebar on the left (desktop), a 56-px topbar (mobile only), and the rest is a scrollable main column.
- Sidebar uses an accordion pattern; the active branch auto-expands.
- Modules can opt into a horizontal tab row (`<ModuleTopNav>`) sitting between the topbar and the breadcrumb. Tabs underline-active in primary, never pill-active.
- Marketing pages have a sticky header (`top-0 z-50`) and a generous footer.

### Density

- Operational pages are **dense by default**. Sidebar items are `py-2` (8px), KPI compacts use `p-3 gap-1`, table rows are tight.
- Marketing pages are **airy**: section padding ≥ `py-20`, generous line-height (`leading-relaxed` on hero copy).

### Motion budget

- One animation per scroll-into-view; no infinite loops outside of explicit "loading" states. (StatusBadge `urgente` does pulse — exception, by design.)
- Toasts (sonner) slide in top-right.

---

## ICONOGRAPHY

The system uses **Lucide React** (`lucide-react@^0.460`) exclusively for UI icons. The repo also vendors `@fortawesome/fontawesome-free` as a leftover from the legacy AdminLTE Blade screens, but new code does not use it.

- **Library:** [lucide.dev](https://lucide.dev) — 1.5px stroke, 24×24 grid, single weight. The sidebar maps backend menu strings to Lucide names via `<Icon name="…">` (a thin wrapper over Lucide).
- **Common icons:** `LogIn`, `LogOut`, `Coffee` (intervalo de almoço), `Clock`, `ArrowUpRight`, `ArrowDownRight`, `Minus`, `ChevronDown`, `ChevronRight`, `Menu`, `LogOut`, `UserCircle2`. See `Components/shared/ponto/ActivityFeed.tsx` for typical mapping.
- **Icon sizes:** 12 / 13 / 14 / 16 / 18 / 20 / 22 px depending on density. Default body is `size-4` (16px).
- **Color:** icons inherit `currentColor`. Use `text-muted-foreground` for chrome icons, `text-primary` for active sidebar, `text-emerald-600` / `text-amber-600` / `text-destructive` for status.
- **Containers:** when an icon needs emphasis, wrap in a `h-9 w-9 rounded-lg bg-primary/10 text-primary` square (PageHeader pattern) or a `rounded-full` ring (ActivityFeed pattern).
- **No SVG inline drawings** outside of the Hero mockup chart and inside the SVG checkmark of pricing tiers (the codebase uses literal `<path>` for that one — preserved here).
- **No PNG icons.** The repo's only PNGs are placeholders we ignore.

### Emoji

Allowed only in marketing feature grids. The current `<FeatureGrid>` uses 📐 🏭 🛒 📦 🧾 ⏱️ 💳 📊 (one per feature card, sized inside an 11×11 rounded-lg primary-tinted plate). Operational app: never.

### Brand mark / logo

The brand mark is the **Office Impresso CMYK cube** — an isometric cube built from four process-colour sub-cubes (amarelo `#F0E62D` / magenta `#EB3088` / ciano-azul `#7DD1EB`–`#235EA9` / cinza `#89869D`), the printing inks of comunicação visual rendered as a 3D symbol. It locks up with the **“Office Impresso”** wordmark (“Office” branco, “Impresso” azul `#2987D0`) over a assinatura.

**Vector assets** (`assets/brand/`):

- **`logo-full.svg`** — lockup oficial completo (cubo + wordmark custom + tagline), tudo em vetor. Wordmark e tagline são brancos/azuis → usar sobre **roxo da marca ou fundo escuro**. Esse é o wordmark exato de marketing.
- **`logo-mark.svg`** — só o cubo, transparente, escalável — favicon, glyph de app, marcador.
- **`brand-bg.png`** — o plano de fundo institucional (roxo com padrão de cubos em linha).

**Componente DS:** `Logo` (grupo Brand) renderiza o cubo como SVG inline escalável; `wordmark`/`tagline` acrescentam o lettering em IBM Plex — o **tratamento de aplicação** para o app (sidebar, headers), distinto do wordmark custom do `logo-full.svg`. The old `assets/logo-small.png` money-bag placeholder is **superseded — do not use it**.

### Fonts

The operational app loads **IBM Plex Sans + IBM Plex Mono** as **self-hosted webfonts** — `colors_and_type.css` declares `@font-face` rules pointing to `assets/fonts/*.woff2` (latin subset, weights Sans 400/500/600/700 + Mono 400/500/600). No Google CDN dependency: the app renders offline and in print with no FOUT. The marketing/Tailwind layer uses the system sans stack. There is no third typeface — don't introduce one without a design decision (ADR).

---

## Caveats — read me

1. **Logo oficial presente (vetor).** The CMYK-cube + "Office Impresso" lockup lives in `assets/brand/` (`logo-full.svg`, `logo-mark.svg`) and as the `Logo` DS component. Wordmark/tagline are light-on-dark — use over the brand purple or a dark surface; on light backgrounds use the cube symbol alone. The legacy money-bag `assets/logo-small.png` is superseded. (Fonts: IBM Plex, self-hosted.)
2. **The marketing site uses 8 emoji icons** in `FeatureGrid`. We've preserved them. If you want a more "enterprise" feel, swap them for Lucide icons in primary-tinted plates — the rest of the system already uses that pattern.
3. **Dark mode is supported** at the token level (every variable has a `.dark` override) — but the site marketing pages haven't been visually QA'd for dark in the source repo. The App Shell is the dark-mode-tested surface.
4. **Pricing copy is tentative.** `FALLBACK_TIERS` in `PricingTiers.tsx` looks finished but is labelled "fallback" — production pulls from `packages` table. Treat the prices as illustrative.
