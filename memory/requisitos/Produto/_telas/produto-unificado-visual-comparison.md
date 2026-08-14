---
id: produto-unificado-visual-comparison
tela: /products/unificado
component: resources/js/Pages/Produto/Unificado/Index.tsx
prototipo: prototipo-ui/cowork/prototipos/produto/produto-unificado-v2.dc.html
owner: wagner
status: draft
last_validated: "2026-08-14"
parent_module: Produto
related_us: [US-PROD-023]
related_adrs: [0104, 0107, 0114, 0239, 0282, 0299]
---

# Comparativo visual — Catálogo Unificado (`/products/unificado`)

> **Fonte de design:** `prototipo-ui/cowork/prototipos/produto/produto-unificado-v2.dc.html`
> (espelhado do Cowork em 2026-08-14 — era **LIVE-ONLY**, servido em `127.0.0.1:8792` e
> nunca tinha descido pro git; o espelho é cópia byte-a-byte do que o servidor entrega,
> escrita por `cp` a partir do `curl`, **nunca transcrita**).
>
> **Por que este arquivo nasce agora:** [M] apontou a URL do protótipo e pediu cópia fiel —
> *"Isso vale para hierarquia, tipografia, bordas, e demais elementos que compõe a tela do
> protótipo. Precisa ser exatamente a cópia."* A tela era a única da família Produto sem
> comparativo visual (as 8 irmãs têm `_telas/produto-*-visual-comparison.md`).

---

## 0 · O achado que muda a natureza do trabalho — os tokens JÁ são os mesmos

O protótipo carrega dois CSS do bundle Cowork. Medidos em 2026-08-14:

| Arquivo do bundle | Tamanho | O que é |
|---|---|---|
| `styles.css` | 417 B | só um `@import "./colors_and_type.css"` — **zero regra visual** |
| `colors_and_type.css` | 19.917 B | os tokens DS v6, com o cabeçalho declarando *"copied verbatim from that generated output — do not invent values"* |

Comparação mecânica dos **38 tokens** que o protótipo declara em `.cockpit` contra
`resources/css/tokens/_generated-cockpit-light.css` do repo:

```bash
comm -23 proto_cockpit.txt repo_cockpit.txt   # → saída VAZIA
```

**Zero divergência.** `--accent: oklch(0.55 0.15 295)`, `--bg`, `--surface`, `--text-dim`,
`--border-2`, `--font-sans`, todos idênticos. A rampa `--fs-1..9` (10.5 · 11.5 · 12.5 · 13.5 ·
15 · 18 · 22 · 28 · 38) também bate exatamente com `_generated-foundations-light.css`.

**Consequência prática, e é o que autoriza a abordagem:** este **não** é um caso de "pacote
Cowork novo" — a proibição Tier 0 de *copiar o `styles.css` inteiro antes de customizar*
([proibicoes.md §Design System](../../../proibicoes.md)) existe pra bundle que traz paleta
própria. Aqui não há paleta a importar: o protótipo e o app já bebem da mesma fonte.
A infidelidade é **100% de anatomia de componente e de markup**, não de cor.

E o `AppShellV2` já monta a página dentro de `className="cockpit"`
([AppShellV2.tsx:490](../../../../resources/js/Layouts/AppShellV2.tsx)), então os tokens
resolvem sem nenhum import novo.

---

## 1 · Inventário dos componentes do protótipo

O protótipo importa 13 componentes do bundle DS (`OfficeImpressoDesignSystem_d7f886`) e usa
outros 3 via `NS.*` no script. Para cada um, o que o repo já tem:

| Componente DS | Repo tem equivalente FIEL? | Veredito |
|---|---|---|
| `PageHeader` | ✅ `@/Components/PageHeader` — `text-[22px] font-bold tracking-tight leading-snug` | **consumir** — é o mesmo h1 22px/700/1.375 que o protótipo produz depois do override `.cockpit h1` |
| `TabBar` | ✅ `@/Components/shared/PageHeaderTabs` — underline `var(--accent)`, pill `color-mix(--accent-soft 50%)`, badge mono `10.5px/1.4` `min-w-[18px]` | **consumir** — fidelidade travada por `tests/pageHeaderTabsFidelity.spec.tsx` |
| `KpiFilterCard` | ❌ | portar |
| `DataTable` | ⚠️ `@/Components/shared/DataTable` usa `text-xs` + `bg-muted/30`; o DS usa `10.5px` + `var(--bg-2)` + padding `9px 12px`/`10px 12px` | portar local (ver §4) |
| `Drawer` + `DrawerSection` | ⚠️ a tela usa `ui/sheet` (Radix) | portar `DrawerSection`; manter `Sheet` como casca |
| `EmptyState` | ⚠️ `shared/EmptyState` não tem as 8 variantes (`first`/`no-results`/`error`…) | portar local |
| `FilterChip` | ❌ (a tela hand-rola um `Chip`) | portar |
| `DropdownMenu` | ❌ no vocabulário DS | portar |
| `Alert` | ❌ | portar |
| `Input` · `Select` · `Button` | ⚠️ shadcn ≠ anatomia DS (`controlStyle` = `13px/1.4`, padding `7px 10px`, ring `0 0 0 3px var(--accent-soft)`) | portar local |
| `Skeleton` | ❌ (a tela hand-rola dois skeletons) | portar |
| `Toast` · `Command` | ❌ | portar |

**Onde nascem:** `resources/js/Pages/Produto/_components/` — domínio de 1 módulo só, que é
o que a [rule `components.md`](../../../../.claude/rules/components.md) manda. **Não** viram
uma segunda biblioteca DS em `Components/ui/`: isso duplicaria papel de componente canon e
cairia no detector `component-registry-check --roles`.

---

## 2 · As 15 dimensões (ADR 0107 · gate F1.5)

| # | Dimensão | Protótipo | Tela no [PR #5756](https://github.com/wagnerra23/oimpresso.com/pull/5756) | Δ |
|---|---|---|---|---|
| 1 | **Hierarquia** | header sticky (PageHeader + TabBar) → KPI → filtros → chips → tabela → paginação | breadcrumb + h1 + nav → KPI → toolbar → tabela → paginação | 🟡 mesma ordem, chrome diferente |
| 2 | **Tipografia h1** | 22px / 700 / 1.375 | `text-[22px] font-bold leading-snug` | ✅ idêntico |
| 3 | **Escala de corpo** | `.cockpit` reescala `--fs-1..5` → 10 · 11 · 12 · 14 · 16px; tabela 14px, `td small` 11px, `th` 12px | Tailwind `text-sm`/`text-[11px]`/`text-[12px]` ad-hoc | ❌ escala não portada |
| 4 | **Bordas** | card `1px var(--border)` + `radius 12px` + `var(--sh-1)`; linha `1px var(--border-2)` | `rounded-md` (6px) + `border-border` + `shadow-sm` | ❌ raio 12 vs 6 |
| 5 | **Densidade** | `data-d` compact 5px / normal / comfy 16px no `td` | `style={{height}}` 36/44/56 na `tr` | 🟡 mecanismo diferente |
| 6 | **Cor** | tokens `.cockpit` | mesmos tokens via classe Tailwind | ✅ |
| 7 | **KPI strip** | 5 × `KpiFilterCard` — tile 36px, label `--fs-1` 600 ls .06em, valor `--fs-6`, `grid-template-columns:repeat(5,1fr)` gap 12 | `Grid min="sm"` (auto-fit) + `KpiFiltro` local, valor `text-lg` | 🟡 grid fluido ≠ 5 colunas fixas |
| 8 | **Filtros** | 3 `DropdownMenu` (Categoria · **Situação** · **Estoque**) + contagem + busca 300px à direita | 1 `Select` (Categoria) + contagem + busca | ❌ faltam 2 filtros |
| 9 | **Chips** | `FilterChip` pill `radius 99`, `accent 12%`, borda `accent 32%`, 11.5px | `Chip` retangular `rounded` + `bg-muted/40` | ❌ |
| 10 | **Tabela** | 8 colunas · `min-width 1014px` · `table-layout:fixed` · ellipsis por célula · `th` 12px uppercase `--bg-2` | 7 colunas (falta **Situação**), sem largura mínima | ❌ |
| 11 | **Estados** | `Skeleton variant=row count=8` · `EmptyState` error/first/no-results · `Alert` info em Tabelas | 2 skeletons locais · bloco de vazio manual · sem Alert | 🟡 |
| 12 | **Drawer** | 760 · header badge SKU · 4 seções (Preço/margem · **BOM** · **Especificações** · **Consumo OS 30d**) | 760 · 2 seções (Preço/margem · Estoque) | ❌ |
| 13 | **Paginação** | `« ‹ n/total › »` + `Por página` + "Mostrando X–Y de N" + 2 botões de atalho (⌨ e ?) | idêntico, **sem** os 2 botões de atalho | 🟡 |
| 14 | **Teclado** | `J/K` · `↵` · `/` · `⌘K` · `N` · `?` · `Esc` + modal de atalhos | só `/` | ❌ |
| 15 | **Foco de linha** | cursor `J/K` pinta a linha com `accent 7%` + rail `inset 3px accent` | `role=button` + `Enter`/`Espaço`, sem cursor | ❌ |

---

## 3 · Achados de DADO — onde a tela mente hoje

Estes não são de aparência, e são os mais caros:

### 3.1 · O KPI "Populares · 30d" contradiz a coluna "30d" — `[must]`

`vendas30d()` soma de verdade (`transaction_sell_lines` × 30 dias) e alimenta os cards
`populares` / `sem_giro`. Mas a linha da lista sai com `'uses30' => 0` chumbado
([ProdutoUnificadoController.php:327](../../../../app/Http/Controllers/ProdutoUnificadoController.php)).

Na tela: o card diz *"N produtos com 30+ saídas"*, você clica, e **todas** as linhas mostram
`0` na coluna `30d`. O operador vê o card e a lista discordando na mesma tela.

### 3.2 · Estoque nunca é conhecido — `[must]`

`'stockQty' => null` fixo (`:326`). Toda linha que controla estoque mostra `—`. O `—` é
honesto (não afirma zero), mas a coluna inteira é decorativa.

### 3.3 · "Balcão" e "Esta tabela" mostram o mesmo número, sem dizer por quê

Decisão D11 (2026-08-13) mandou **reduzir a leitura** até sair a ADR de schema do
multiplicador — correto. Mas a decisão vive só em comentário de código: na tela as duas
colunas exibem o mesmo valor calado. O protótipo resolve com um `Alert` tone=info:
*"Multiplicador aguarda decisão de schema"*. **Portar o Alert** é o conserto — não mexer no
cálculo (ADR `arq/0001-selling-price-multiplier` segue aberta).

---

## 4 · Divergência estrutural registrada, NÃO consertada aqui

`@/Components/shared/DataTable` e `shared/EmptyState` **não** são portes fiéis do DS v6:
usam escala Tailwind (`text-xs`, `bg-muted/30`) onde o DS usa valores literais
(`10.5px`, `var(--bg-2)`, padding `9px 12px`). Isso é dívida **cross-tela** — alinhar aqueles
componentes mexeria em toda tela que os consome.

**Fica registrado como achado, e não entra neste PR.** Aqui a tela ganha componentes locais
em `Pages/Produto/_components/` com a anatomia DS verbatim; a reconciliação de
`shared/DataTable` com o DS é decisão [W] com gate visual próprio.

---

## 5 · Veredito

**Reprovado** contra o protótipo antes deste PR. As dimensões 3, 4, 8, 9, 10, 12, 14, 15
divergem, e os três achados de dado (§3) fazem a tela afirmar coisas que os dados não
sustentam.

Aprovação visual de [W] é sobre **screenshot**, não sobre esta tabela
([ADR 0107](../../../decisions/0107-emendation-0104-visual-comparison-gate-f3.md)).

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-08-14 | [M+C] | Arquivo criado. Protótipo v2 espelhado do Cowork (era LIVE-ONLY). Medição de tokens proto×repo → 0 divergências. 15 dimensões + 3 achados de dado + divergência `shared/DataTable` registrada como dívida cross-tela. |
