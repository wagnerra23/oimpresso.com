# PageHeader Canon v3.1 — Template Oficial

> **Status:** proposto · pending Larissa biz=4 validação 7d
> **Origem:** [ADR 0189](../../../decisions/0189-pageheader-canon-v3-1-cadastro-roxo.md) + [ADR 0190](../../../decisions/0190-primary-button-roxo-universal-295.md) (primary universal)
> **Supersedes parcialmente:** [ADR 0180](../../../decisions/0180-pageheader-canon-3-zonas.md), [ADR 0182](../../../decisions/0182-pageheader-canon-hue-per-grupo.md)
> **Protótipo visual:** [prototipo-ui/prototipos/pageheader-canon-v3/](../../../../prototipo-ui/prototipos/pageheader-canon-v3/)
> **Diário evolutivo:** [PageHeader-LEARNINGS.md](./PageHeader-LEARNINGS.md)
> **Última atualização:** 2026-05-25 (ADR 0190 primary universal)

---

## 1. Visão geral

Template canônico pra TODAS as Index Inertia/React do oimpresso (~80 telas). Família **Modern SaaS**
(slate cool + system fonts + density compact) com primary **roxo médio** `oklch(0.55 0.15 295)` no
escopo Cadastro (hue per grupo de ADR 0182 fica em modo de espera até decisão "universal vs grupo").

**3 blocos verticais separados** (gap 12px):
1. Header card (3 zonas L · C · R)
2. KPI strip card (4 cards branco frio em grid)
3. Conteúdo card (lista, tabela, kanban etc)

## 2. Estrutura HTML semântica

```html
<main>
  <!-- BLOCO 1 — Header -->
  <header class="page-header-card" role="banner">
    <div class="os-page-h">
      <div class="os-page-h-l">
        <h1>{title}</h1>
        <p>{subtitle com tabular-nums}</p>
      </div>
      <nav class="os-page-h-nav" aria-label="Sub-navegação">
        <a class="os-tab" aria-current="page">{label}<span class="os-tab-count">{n}</span></a>
        <!-- ... -->
      </nav>
      <div class="os-page-h-r">
        <button class="btn icon-only" aria-label="Mais ações" aria-haspopup="menu">⋮</button>
        <button class="btn primary">+ {Novo X}</button>
      </div>
    </div>
  </header>

  <!-- BLOCO 2 — KPI strip -->
  <div class="kpi-strip" role="group" aria-label="Indicadores principais">
    <button class="kpi-card active" aria-pressed="true">
      <span class="kpi-label">{LABEL}</span>
      <span class="kpi-value">{value}</span>
      <span class="kpi-hint">{hint}</span>
    </button>
    <!-- ... 4 cards total -->
  </div>

  <!-- BLOCO 3 — Conteúdo -->
  <div class="content-card">
    {lista, tabela, kanban, etc}
  </div>
</main>
```

## 3. Tokens canon (CSS variables `:root`)

> **Regra dura — fonte:** AppShellV2 carrega `IBM Plex Sans` globalmente. Canon REJEITA herança.
> SEMPRE forçar via `style={{ fontFamily: 'ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif' }}` no `<header>` do canon. Anti-padrão AP16 (LEARNINGS sessão 2026-05-25).

```css
:root {
  /* superfícies */
  --bg-page:        #f8fafc;   /* slate-50 */
  --bg-card:        #ffffff;
  
  /* texto */
  --text-strong:    #0f172a;   /* slate-900 */
  --text-base:      #334155;   /* slate-700 */
  --text-dim:       #64748b;   /* slate-500 */
  --text-faint:     #94a3b8;   /* slate-400 */
  
  /* bordas */
  --border-soft:    #e2e8f0;   /* slate-200 */
  --border-mid:     #cbd5e1;   /* slate-300 */
  
  /* PRIMARY ROXO MEDIO UNIVERSAL (hue 295) — ADR 0190
     Aplica-se a TODOS os módulos, independente do grupo.
     NÃO confundir com SIDEBAR_GROUP_HUE no shared.ts — aquele é APENAS
     pra agrupamento visual do sidebar (header de grupo, ícones), NÃO
     pro primary das telas. ADR 0190 (2026-05-25) supersede pattern
     hue-per-grupo do primary que vinha de ADR 0182. */
  --primary:                oklch(0.55 0.15 295);
  --primary-dark:           oklch(0.45 0.15 295);
  --primary-soft:           oklch(0.96 0.03 295);   /* bg active/hover light */
  --primary-light:          oklch(0.88 0.08 295);
  --primary-text-on-soft:   oklch(0.35 0.15 295);   /* texto em bg soft */
  
  /* semântica */
  --rose-text:   #be123c;
  --rose-bg:     #fff1f2;
  --amber-text:  #b45309;
  --emerald-text:#047857;
  
  /* tipografia — ATENÇÃO crítica:
     AppShellV2 (Tailwind config) define IBM Plex Sans GLOBALMENTE.
     PageHeader canon REJEITA herança — sempre FORÇAR via inline style
     OU via classe `.page-header-canon` que reseta font-family.
     Anti-padrão AP16 (PageHeader-LEARNINGS.md sessão 2026-05-25). */
  --font-stack:    ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
  --fs-h1:         16px;
  --fs-sub:        12px;
  --fs-tab:        12.5px;
  --fs-btn:        12.5px;
  --fs-kpi-val:    22px;
  --fs-kpi-label:  11px;
  
  /* dimensões */
  --btn-h:         32px;
  --card-radius:   8px;
  --btn-radius:    5px;
  --gap-blocos:    12px;
  --page-h-px:     24px;   /* padding horizontal header */
  --page-h-py:     14px;   /* padding vertical header */
  --kpi-px:        20px;
  --kpi-py:        14px;
}
```

## 4. Componentes — specs detalhadas

### 4.1 BLOCO 1 — Header card (canon v3.2 final · 2026-05-25)

> **Pattern consolidado após Wagner iterar 6 vezes (PRs #1457 → #1478):**
> `bg-background border border-border rounded-t-lg overflow-visible` no card +
> `pt-6 px-6 pb-3.5` no inner flex.

**JSX canon (copiar-colar pra outras Index):**

```jsx
<header
  className="bg-background border border-border rounded-t-lg overflow-visible"
  role="banner"
>
  <div className="flex items-center gap-4 pt-6 px-6 pb-3.5 min-h-[60px]">
    {/* Zona L · identidade · flex-1 min-w-0 */}
    {/* Zona C · subnav inline · hidden md:flex shrink-0 self-stretch ml-2 */}
    {/* Zona R · actions · flex-shrink-0 flex items-center gap-1.5 */}
  </div>
  {/* mobile fallback nav (md:hidden) — só se houver subnav */}
</header>
```

**CSS canon equivalente (pra projetos sem Tailwind):**

```css
.page-header-card {
  background: var(--bg-card);                    /* white */
  border: 1px solid var(--border-soft);          /* slate-200 */
  border-top-left-radius: var(--card-radius);    /* 8px */
  border-top-right-radius: var(--card-radius);   /* 8px */
  border-bottom-left-radius: 0;                  /* RETA — conecta visualmente com BLOCO 2 KPI */
  border-bottom-right-radius: 0;                 /* RETA */
  overflow: visible;                              /* dropdown ⋮ escapa */
  margin-bottom: var(--gap-blocos);              /* gap 12px pro BLOCO 2 */
}
.os-page-h {
  display: flex;
  align-items: center;
  gap: 16px;                                      /* gap-4 entre zonas */
  padding: 24px 24px 14px;                        /* pt-6 px-6 pb-3.5 — espelha Vendas */
  min-height: 60px;
}
```

**Razão do `rounded-t-lg` (não `rounded-lg` nem `rounded-none`):**
- Vendas canon Cowork é flat puro (`border-radius: 0`)
- Card fechado 4 cantos arredondados criava "salto" visual com BLOCO 2 KPI abaixo
- Meio-termo: topo curvo (estética card) + bottom reta (conecta com KPI strip via gap 12px)
- Wagner aprovou após inspecionar `/sells` (PR #1478)

**Padding decisão final:**
- 24px top + 24px laterais — folga "respira" igual Vendas
- 14px bottom — underline da tab ativa (`-mb-px`) pousa na border-bottom do card
- Bottom MENOR que top é proposital (pattern canon LEARNINGS Decisão #1)

### 4.2 Zona L — Identidade

```css
.os-page-h-l { flex: 1; min-width: 0; }
.os-page-h-l h1 {
  font-size: var(--fs-h1);
  font-weight: 600;
  color: var(--text-strong);
  letter-spacing: -0.01em;
  line-height: 1.4;
}
.os-page-h-l p {
  font-size: var(--fs-sub);
  color: var(--text-dim);
  margin-top: 2px;
  font-variant-numeric: tabular-nums;
}
.os-page-h-l .alert {
  color: var(--rose-text);
  font-weight: 500;
}
```

### 4.3 Zona C — SubNav inline com tabs

```css
.os-page-h-nav {
  display: flex;
  align-items: center;
  gap: 0;
  align-self: stretch;
  margin-left: 8px;
}
.os-tab {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 6px 12px;
  font-size: var(--fs-tab);
  font-weight: 400;
  color: var(--text-dim);
  background: transparent;
  border-bottom: 2px solid transparent;
  margin-bottom: -1px;          /* underline morde linha base */
  cursor: pointer;
  text-decoration: none;
  transition: color 120ms cubic-bezier(0.4, 0, 0.2, 1), border-color 120ms;
}
.os-tab:hover { color: var(--text-base); }
.os-tab[aria-current="page"] {
  color: var(--text-strong);
  font-weight: 500;
  border-bottom-color: var(--primary);
}
.os-tab-count {
  display: inline-block;
  margin-left: 4px;
  padding: 1px 6px;
  font-size: 11px;
  font-weight: 500;
  color: var(--text-faint);
  background: #f1f5f9;
  border-radius: 9px;
  font-variant-numeric: tabular-nums;
}
.os-tab[aria-current="page"] .os-tab-count {
  color: var(--primary-text-on-soft);
  background: var(--primary-soft);
}
```

**Regras de naming:**
- Tabs com nomes ≤ 8 chars: usar nome completo (`Todos`, `Clientes`, `Caixa`, `Vendas`)
- Tabs ≥ 9 chars: abreviar com ponto (`Fornec.`, `Repr.`) ou usar sinônimo completo curto (`Equipe` em vez de `Funcionários`)
- SEMPRE adicionar `title="{nome completo}"` pra screen reader + tooltip
- Counter à direita do label sempre que existir filtragem por categoria

### 4.4 Zona R — Actions

```css
.os-page-h-r {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-left: auto;
}
.btn {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 0 11px;
  height: var(--btn-h);
  font-size: var(--fs-btn);
  font-weight: 400;
  color: var(--text-base);
  background: var(--bg-card);
  border: 1px solid var(--border-soft);
  border-radius: var(--btn-radius);
  cursor: pointer;
  font-family: inherit;
  transition: background 120ms, border-color 120ms;
}
.btn:hover { background: var(--bg-page); border-color: var(--border-mid); }
.btn.icon-only {
  width: var(--btn-h);
  padding: 0;
  justify-content: center;
}
.btn.primary {
  background: var(--primary);
  color: #fff;
  border-color: var(--primary-dark);
  font-weight: 500;
}
.btn.primary:hover {
  background: var(--primary-dark);
  border-color: var(--primary-dark);
}
.btn.primary:active { transform: scale(0.97); transition: transform 60ms; }
```

**Hierarquia Zona R (apenas 2 botões):**
1. **`⋮` overflow** (icon-only ghost-outline) — abre Radix DropdownMenu com ações secundárias
2. **Primary** roxo médio — ação principal `+ Novo {entidade}`

### 4.5 Overflow `⋮` button (estilo — GHOST PURO)

> **Regra dura:** overflow `⋮` é GHOST puro — `bg: transparent` + `border: 0` (NÃO outline, NÃO soft).
> Anti-padrão AP17 (LEARNINGS sessão 2026-05-25 — usar `variant="outline"` shadcn quebra).
>
> Em React: usar `variant="ghost"` + **forçar `className="border-0"`** se shadcn ghost ainda aplicar border.

```css
.btn.icon-only-ghost {
  width: var(--btn-h);
  height: var(--btn-h);
  padding: 0;
  background: transparent;
  border: 0;
  color: var(--text-dim);
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: var(--btn-radius);
  transition: background 120ms cubic-bezier(0.4, 0, 0.2, 1), color 120ms;
}
.btn.icon-only-ghost:hover {
  background: hsl(var(--accent));
  color: var(--text-strong);
}
```

```jsx
{/* React equivalente */}
<DropdownMenuTrigger asChild>
  <button
    type="button"
    aria-label="Mais ações"
    aria-haspopup="menu"
    className="inline-flex items-center justify-center h-8 w-8 rounded-md bg-transparent border-0 text-muted-foreground hover:bg-accent hover:text-foreground transition-colors"
  >
    <MoreVertical className="h-4 w-4" />
  </button>
</DropdownMenuTrigger>
```

### 4.6 Overflow menu (estrutura interna padrão)

```jsx
<DropdownMenu>
  <DropdownMenuTrigger>⋮</DropdownMenuTrigger>
  <DropdownMenuContent align="end" className="min-w-[220px]">
    {filters.length > 0 && (
      <>
        <DropdownMenuLabel>Filtros</DropdownMenuLabel>
        <DropdownMenuItem>
          <Search /> Filtros avançados
          {activeFiltersCount > 0 && <Badge>{activeFiltersCount}</Badge>}
        </DropdownMenuItem>
        <DropdownMenuSeparator />
      </>
    )}
    {dataActions.length > 0 && (
      <>
        <DropdownMenuLabel>Dados</DropdownMenuLabel>
        <DropdownMenuItem><Upload /> Importar</DropdownMenuItem>
        <DropdownMenuItem><Download /> Exportar CSV</DropdownMenuItem>
        <DropdownMenuSeparator />
      </>
    )}
    {configActions.length > 0 && (
      <>
        <DropdownMenuLabel>Configuração</DropdownMenuLabel>
        <DropdownMenuItem><Layers /> Grupos de {entidade}</DropdownMenuItem>
      </>
    )}
  </DropdownMenuContent>
</DropdownMenu>
```

**3 seções canon:** Filtros / Dados / Configuração — sempre nessa ordem, separadas por `<DropdownMenuSeparator />`.

### 4.6 KPI strip card

```css
.kpi-strip {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 1px;
  background: var(--border-soft);   /* gap visual = border */
  border: 1px solid var(--border-soft);
  border-radius: var(--card-radius);
  overflow: hidden;
  margin-bottom: var(--gap-blocos);
}
.kpi-card {
  background: var(--bg-card);
  padding: var(--kpi-py) var(--kpi-px);
  display: flex;
  flex-direction: column;
  gap: 4px;
  cursor: pointer;
  transition: background 120ms;
  text-align: left;
  border: none;
  font-family: inherit;
}
.kpi-card:hover { background: var(--bg-page); }
.kpi-card[aria-pressed="true"] {
  background: var(--primary-soft);
}
.kpi-card.alert {
  background: var(--rose-bg);
}
.kpi-label {
  font-size: var(--fs-kpi-label);
  font-weight: 500;
  color: var(--text-faint);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.kpi-value {
  font-size: var(--fs-kpi-val);
  font-weight: 600;
  color: var(--text-strong);
  font-variant-numeric: tabular-nums;
  letter-spacing: -0.015em;
  line-height: 1.1;
}
.kpi-card.alert .kpi-value { color: var(--rose-text); }
.kpi-card[aria-pressed="true"] .kpi-value { color: var(--primary-text-on-soft); }
.kpi-hint {
  font-size: 11.5px;
  color: var(--text-dim);
  font-variant-numeric: tabular-nums;
}
```

**KPI strip rules:**
- SEMPRE 4 cards (não 3, não 5) — grid `repeat(4, 1fr)`
- Cards são CLICÁVEIS (filtro click-to-apply) — usar `<button>` semântico, não `<div>`
- `aria-pressed="true"` quando o filtro do card está ativo
- Cards "alert" usam bg rose pra chamar atenção (uso parcimonioso — máx 1 alert por strip)
- Em viewport `< 768px`: cair pra grid 2×2 via media query

## 5. Estados completos

| Componente | Estado | Visual |
|---|---|---|
| Tab | default | `text-dim` + border transparent |
| Tab | hover | `text-base` |
| Tab | focus-visible | outline 2px primary + offset 2px |
| Tab | active (aria-current=page) | `text-strong` + weight 500 + border-bottom primary |
| Counter | default | bg slate-100 + text-faint |
| Counter | dentro tab active | bg primary-soft + text-primary-on-soft |
| Botão | default | bg card + border soft + text-base |
| Botão | hover | bg page + border mid |
| Botão | focus-visible | outline 2px primary + offset 2px |
| Primary | default | bg primary + text white + border primary-dark |
| Primary | hover | bg primary-dark |
| Primary | active (press) | scale(0.97) 60ms |
| Primary | disabled | opacity 0.5 + cursor not-allowed |
| KPI card | default | bg card |
| KPI card | hover | bg page |
| KPI card | active (filtro aplicado) | bg primary-soft + value text primary |
| KPI card | alert | bg rose + value text rose |

## 6. Responsive

```css
/* mobile-first */
.os-page-h { flex-wrap: wrap; }
.os-page-h .os-page-h-nav {
  order: 3;
  flex-basis: 100%;
  margin-top: 0.5rem;
  border-top: 1px solid var(--border-soft);
  padding-top: 0.25rem;
  overflow-x: auto;
}

@media (min-width: 768px) {
  .os-page-h { flex-wrap: nowrap; }
  .os-page-h .os-page-h-nav { order: 0; flex-basis: auto; margin-top: 0; border-top: none; padding-top: 0; }
}

@media (max-width: 767px) {
  .os-tab { min-height: 44px; }   /* touch target WCAG 2.5.5 */
  .kpi-strip { grid-template-columns: repeat(2, 1fr); }
}
```

## 7. Acessibilidade (WCAG 2.1 AA)

- [x] `<header role="banner">` + 1 `<h1>` por página
- [x] `<nav aria-label="Sub-navegação">` distinto do nav principal (sidebar)
- [x] `aria-current="page"` na tab ativa
- [x] `aria-pressed="true"` no KPI card ativo (toggle filter)
- [x] `aria-haspopup="menu"` no overflow `⋮`
- [x] `aria-label="Mais ações"` no `⋮`
- [x] `title="{nome completo}"` em tabs abreviadas
- [x] Contrast ratio ≥ 4.5:1 em todos textos
- [x] Touch targets ≥ 44×44px em mobile
- [x] Focus visible em todos elementos focáveis
- [x] `font-variant-numeric: tabular-nums` em métricas
- [x] `prefers-reduced-motion: reduce` — sem transições

## 8. Quando usar este template

**USE quando:**
- Tela é Index/List/Browse de uma entidade (Cliente, Produto, Venda, OS, Cobrança, etc)
- Tem ≥ 2 sub-views da mesma entidade (filtro por tipo, por status, por período)
- Tem ≥ 1 ação primária (criar registro novo)
- Tem ≥ 3 KPIs do dataset (contadores, totais, alertas)

**NÃO USE quando:**
- Tela é Form (Create/Edit) — usa modo FOCO sem SubNav (ADR 0182 PT-04)
- Tela é Show/Detail de 1 registro — usa header simplificado com breadcrumb
- Tela é Dashboard puro sem entidade principal — usa DashboardHeader (template separado, futuro)
- Tela é Print/Relatório — usa @media print (header degrada)

## 9. Migration plan

### Wave 1 (piloto · 2-3 dias)
- `Cliente/Index` (`/contacts`) — biz=4 Larissa daily
- `Financeiro/Cobranca/Index` (`/financeiro/cobranca`) — biz=1 Wagner daily

### Wave 2 (Grupo Cadastro · 1 semana)
- Cliente/Show, Cliente/Edit, Cliente/Map
- Produto/Index, Produto/Show, Produto/Edit
- 8-10 telas total

### Wave 3 (Grupos Finanças + Comercial · 2 semanas)
- Financeiro/* (~12 telas)
- Sells/* (~5 telas)
- Crm/* (~3 telas)

### Wave 4 (restantes · 3-4 semanas)
- Fiscal, Sistema, Estoque, Pessoas, Atendimento, Equipe (~50 telas)

**Gates por wave:**
- Visual regression baseline assinada por Wagner
- Pest browser tests passando
- Lighthouse CI ≥ 90 perf + 100 a11y
- Smoke prod 7 dias sem regressão reportada

## 10. Decisões em aberto

- [ ] **Roxo 295 é só do Cadastro ou universal?** Trigger: feedback Larissa biz=4 após 7d
- [ ] **Padrão de tabs counter quando > 5 sub-views** (overflow `Mais (N)` dropdown vs scroll-x horizontal)
- [ ] **Dark mode** — específica tokens dark depois de validar light com cliente
- [ ] **Skeleton loading** — quando inserir e que dimensões
- [ ] **Sticky behavior** — header gruda no topo quando rolar? Com shadow sutil?

Discussão dessas decisões vai em [PageHeader-LEARNINGS.md](./PageHeader-LEARNINGS.md) → vira ADR quando bater.

## 10b. Ícones — preferência canon (LEARNINGS decisão #2 · 2026-05-25)

> **Atual:** `lucide-react` (1400+ ícones, ~20KB tree-shakeable)
> **Preferência longo prazo:** **Phosphor Icons** (`@phosphor-icons/react`)
> **Interim:** Lucide com 5 fixes técnicos abaixo

### Por que Phosphor é preferência

6 weights (thin/light/regular/bold/fill/duotone), 9000+ ícones, calibração pixel-perfect em 16px, visual Linear/Notion-tier. Bundle ~50KB tree-shakeable (+30KB vs Lucide — aceitável).

Pontuação ponderada vs alternativas em [LEARNINGS Decisão #2](./PageHeader-LEARNINGS.md):

| Biblioteca | Total ponderado |
|---|---|
| Phosphor Icons | **9.4** |
| Heroicons (limitado 300) | 8.1 |
| Lucide (atual) | 8.0 |
| Tabler | 8.0 |

### Regra dura pra ícones em qualquer canon

Quando renderizar ícone em tamanho ≤16px (todas zonas L/C/R do PageHeader):

1. **`size` múltiplo de 4** — preferir 16, 20, 24. EVITAR 13, 14 (pixel snap ruim em DPR=1)
2. **`strokeWidth={1.75}`** em viewBox 24×24 — nunca `2` (= subpixel borra em DPR=1)
3. **`vector-effect: non-scaling-stroke`** sempre (atributo SVG ou inline style)
4. **Cor firme** — `oklch(0.40 0 0)` ou `currentColor` herdando text-foreground. NUNCA chroma <0.05 (lavado)
5. **`className="shrink-0"`** sempre em flex containers (evita squish)

### Snippet canon Lucide (interim, antes de migrar pra Phosphor)

```tsx
import { Plus, Funnel, MagnifyingGlass } from 'lucide-react';

// ❌ Anti-padrão (atual em prod, borra)
<Plus size={14} className="text-muted-foreground" />

// ✅ Canon
<Plus
  size={16}
  strokeWidth={1.75}
  className="shrink-0"
  style={{
    color: 'oklch(0.40 0 0)',
    vectorEffect: 'non-scaling-stroke',
  }}
/>
```

### Snippet canon Phosphor (preferência longo prazo)

```tsx
import { Plus, Funnel, MagnifyingGlass } from '@phosphor-icons/react';

<Plus size={16} weight="regular" className="shrink-0" />
<Funnel size={16} weight="regular" className="shrink-0" />
<MagnifyingGlass size={14} weight="bold" className="shrink-0" />  // bold só pra ênfase
```

### Migration plan (quando Wagner aprovar)

1. `pnpm add @phosphor-icons/react`
2. Codemod automático: `lucide-react` → `@phosphor-icons/react` (nomes 95% coincidem · Phosphor usa `MagnifyingGlass` em vez de `Search`, `Funnel` em vez de `Filter`)
3. Wave por wave (mesmas 4 waves de ADR 0189 §9)
4. Manter Lucide como dep até último import migrar — depois remover

## 11. Componente React `<PageHeader>` (skeleton — implementar Wave 1)

```tsx
// resources/js/Components/PageHeader/PageHeader.tsx
interface PageHeaderProps {
  title: string;
  subtitle?: React.ReactNode;
  tabs?: PageHeaderTab[];
  primary?: PageHeaderAction;
  overflow?: PageHeaderOverflowSection[];
  kpis?: PageHeaderKpi[];
}

// Uso:
<PageHeader
  title="Clientes"
  subtitle={<>31 cadastrados · 4 ativos · <strong className="alert">2 com saldo</strong></>}
  tabs={[
    { key: 'all', label: 'Todos', count: 31, current: true },
    { key: 'customer', label: 'Clientes', count: 22 },
    { key: 'supplier', label: 'Fornec.', fullName: 'Fornecedores', count: 5 },
    { key: 'employee', label: 'Equipe', fullName: 'Funcionários', count: 3 },
    { key: 'representative', label: 'Repr.', fullName: 'Representantes', count: 1 },
  ]}
  overflow={[
    { label: 'Filtros', items: [{ label: 'Filtros avançados', icon: Search, badge: 3 }] },
    { label: 'Dados', items: [{ label: 'Importar', icon: Upload }, { label: 'Exportar CSV', icon: Download }] },
    { label: 'Configuração', items: [{ label: 'Grupos de clientes', icon: Layers }] },
  ]}
  primary={{ label: 'Novo cliente', icon: Plus, href: '/contacts/create?type=customer' }}
  kpis={[
    { label: 'Total cadastrados', value: 31, hint: 'desde 2024', active: true },
    { label: 'Ativos', value: 4, hint: 'com OS aberta' },
    { label: 'Com saldo', value: 2, hint: 'R$ 79.263,30', alert: true },
    { label: 'Novos este mês', value: 22, hint: 'desde dia 1' },
  ]}
/>
```

## 12. Histórico de versões

| Versão | Data | Mudança | ADR |
|---|---|---|---|
| v1 | 2026-04-XX | PageHeader canon 3 zonas inicial | [0180](../../../decisions/0180-pageheader-canon-3-zonas.md) |
| v2 | 2026-05-21 | Hue per grupo (cadastro=202, financas=145, etc) | [0182](../../../decisions/0182-pageheader-canon-hue-per-grupo.md) |
| **v3.1** | **2026-05-24** | **Modern SaaS + roxo médio 295 + KPI separado + ⋮ overflow + bloco fechado + tabs abreviadas** | **[0189](../../../decisions/0189-pageheader-canon-v3-1-cadastro-roxo.md)** |

Iterações INTRA-versão (sem ADR formal) ficam em [PageHeader-LEARNINGS.md](./PageHeader-LEARNINGS.md).
