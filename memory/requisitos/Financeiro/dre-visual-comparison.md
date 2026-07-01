---
slug: dre-visual-comparison
title: "Financeiro — Comparativo visual da tela DRE gerencial"
type: visual-comparison
module: Financeiro
status: approved
approved_by: wagner
approved_at: 2026-05-20
date: 2026-05-20
canon_reference: prototipo-ui/cowork/financeiro-telas-extras.jsx (TelaDRE linha 361-483)
blade_source: n/a (existe `Pages/Financeiro/Relatorios/Index.tsx` errado — DRE como tab shadcn, sem hierarquia)
inertia_target: resources/js/Pages/Financeiro/Dre/Index.tsx (novo)
service_new: Modules/Financeiro/Services/DreService::montar(businessId, periodoTipo, anchorMes)
controller_new: Modules/Financeiro/Http/Controllers/DreController::index()
stories: US-FIN-014 (split — DRE vira US-FIN-014a; Resumo+Fluxo agregador continua US-FIN-014b)
related_adrs: [ui/0114, 0093, 0104, 0107, 0109]
screenshot_aprovado: 2026-05-20 — Wagner aprovou screenshot canon TelaDRE (mensagem chat com print da render renderizada do `financeiro-telas-extras.jsx`)
---

# Comparativo visual — Financeiro · DRE gerencial

> **Tipo de tela:** demonstração de resultado hierárquica (header / items / subtotals com highlight) + 2 cards bottom (Margem operacional + Top categorias receita)
> **Persona alvo:** Wagner [W] — dono, decisão estratégica mensal · Eliana [E] — financeiro, fecha mês. Desktop ≥1024px. Leitura de DRE em <60s pra responder "deu lucro este mês?"
> **Refs:**
> - Tela atual ERRADA: [`resources/js/Pages/Financeiro/Relatorios/Index.tsx`](../../../resources/js/Pages/Financeiro/Relatorios/Index.tsx) (linha 180-311 `DrePanel`) — usa shadcn `Card`+`tfoot` plano, 5 colunas, sem hierarquia, sem % RL, sem Δ%, sem cards bottom. Tab dentro de `/financeiro/relatorios`.
> - Canon Cockpit: [`prototipo-ui/cowork/financeiro-telas-extras.jsx`](../../../prototipo-ui/cowork/financeiro-telas-extras.jsx) — `TelaDRE` linha 361-483 (aprovado [W] Cowork 2026-05-20 — fonte canônica per `_BACKUP-NAO-USAR/README-AVISO.md`)
> - Charter: a criar em `resources/js/Pages/Financeiro/Dre/Index.charter.md` (F3)
> - ADRs: [ui/0114](../../decisions/0114-prototipo-ui-cowork-loop-formalizado.md), [0093 multi-tenant Tier 0](../../decisions/0093-multi-tenant-isolation-tier-0.md), [0104 MWART processo canônico](../../decisions/0104-processo-mwart-canonico-unico-caminho.md), [0107 F1.5 visual-comparison gate](../../decisions/0107-emendation-0104-visual-comparison-gate-f3.md), [0109 Claude Design plugin](../../decisions/0109-claude-design-plugin-integrado-processo-mwart.md)

## Resumo executivo

A tela DRE de hoje está **aplicada errada** — vive como TAB em `/financeiro/relatorios` (junto com Fluxo+Resumo), usando `Card/CardHeader/CardContent` shadcn genérico com tabela 5-colunas plana (Mês / Receita / Despesa / Resultado / Comparativo). Isso é uma **DRE comparativa mensal** (4 meses lado a lado) — útil mas NÃO é o que canônico do Cowork pediu: o canon é uma **DRE hierárquica clássica** (Receita bruta → Deduções → Receita líquida → Custos → Lucro bruto → Despesas → Resultado operacional) com indent + tipos de linha + subtotal destacado em preto.

Esta reaplicação:
- **Cria** rota+controller+service+page dedicados `/financeiro/dre` (NÃO mais tab — espelha o que Onda 22-26 fez com drawer canon parity).
- **Refatora backend** `RelatoriosController::montarDre()` (linha 131-207) pra retornar `linhas[]` hierárquicas com `tipo` (h/i/subtotal), `indent`, `kind` (rec/ded), `v` (valor mês corrente), `prev` (mês anterior), `highlight` (subtotal final preto).
- **Adiciona** toggle período Mês/Trim/Ano/12m, exportação PDF/Excel inline, coluna `% RL`, coluna `Δ%` vs mês anterior, barra inline por item.
- **Deprecra** tab DRE em `Relatorios/Index.tsx` (decisão Wagner 2026-05-20 — manter Fluxo+Resumo na Relatorios; DRE sai como tela first-class).

Backend usa modelos que JÁ EXISTEM (`Modules/Financeiro/Models/Titulo.php`, `Categoria.php`) — **sem migration**. Mapping `fin_categorias.tipo` ('receita' / 'despesa') + `fin_categorias.codigo` (hierárquico 1.1.01) → `DRE_LINES[]` é a peça nova.

## Tabela comparativa — 8 dimensões

### 1. Layout

| Aspecto | Hoje (`Relatorios.DrePanel`) | Canon Cockpit (`TelaDRE`) | Decisão MWART |
|---|---|---|---|
| **Rota** | `/financeiro/relatorios` (tab `?tab=dre` implícito) | tela dedicada single-purpose | `Route::get('/financeiro/dre', [DreController::class, 'index'])->name('financeiro.dre')` |
| **Page header (`os-page-h`)** | "Relatórios · Financeiro" + sub "DRE gerencial, fluxo de caixa projetado vs realizado e resumo do período" | Título `Financeiro · DRE / Relatórios` + sub `Maio 2026 · ROTA LIVRE · caixa unificado` (canon do screenshot 2026-05-20) | `<h1>Financeiro <span class="fin-hero-title-sub">· DRE / Relatórios</span></h1>` + `<p>{periodLabel} · {businessName} · caixa unificado</p>` — espelha `Unificado/Index.tsx:956-962` literal |
| **Topnav contextual módulo (`os-page-h-r`)** | n/a — só botão Export CSV | 7 botões canon: `🔍 Buscar ⌘K · ✦ Resumir mês · ☑ Fechamento · ▶ Apresentar · ⟳ Conciliar · 📁 Plano de contas · ⬇ download · ➕ Novo lançamento` | **Inline copy do padrão Unificado** ([Unificado/Index.tsx:963-1043](../../../resources/js/Pages/Financeiro/Unificado/Index.tsx:963)) — 7 botões `<button class="os-btn ghost\|primary">`. **NÃO é componente compartilhado hoje** (Fluxo herdou versão reduzida). Extrair `<FinModuleTopnav>` reusável vira **US-FIN-TOPNAV-COMPONENT** (backlog separado — não bloqueia esta entrega). DRE F1 = copy-paste do bloco da Unificado, adaptando handlers (Resumir mês → narrativa DRE em vez de Unificado) |
| **Header do Card DRE** | n/a | Label `text-[10px] uppercase tracking-widest` "DEMONSTRAÇÃO DE RESULTADO" + título `text-[16px] font-semibold` "Maio 2026" | Conforme canon — repete o período no card pra contexto local |
| **Body grid** | 3 cards stacked: KPIs `fin-stats` 3-col + tabela meses + tabela despesas-cat | 1 Card grande tabela hierárquica + grid bottom 2-col (Margem + Top categorias) | `<TelaDRE>` puro — 1 Card 6-col table + grid 2-col bottom |
| **Sidebar** | UPos legacy AppShellV2 — entrada "Relatórios" no submenu Financeiro | mesma sidebar | Adicionar entrada "DRE" no submenu Financeiro via `DataController::modifyAdminMenu()` — mesma posição usada por Fluxo (PR #358 padrão) |
| **Breakpoints** | shadcn responsive | desktop only ≥1024px (persona Wagner+Eliana) | Desktop only F1 (per `mwart-quality`) |

### 2. Hierarquia visual

| Aspecto | Hoje | Canon Cockpit | Decisão MWART |
|---|---|---|---|
| **Ação primária** | Inputs De/Até + botão Aplicar | Toggle período Mês/Trim/Ano/12m | Pill toggle inline `bg-stone-100/80 rounded-md p-0.5 border border-stone-200` — botão ativo `bg-white shadow-sm font-medium` |
| **Ação secundária** | `<a>` Export CSV no header global | Botões `Exportar PDF` + `Excel` inline no header do Card | 2 botões `h-8 px-3 rounded-md border border-stone-200 text-[12.5px] text-stone-700 hover:bg-stone-50` |
| **Tipografia tabela** | `text-sm font-mono` em valores | `text-[12.5px] num` (tabular-nums) + `font-medium` em items + `font-semibold` em headers + `text-[14px] font-bold` em subtotal | Espelhar canon literal |
| **Indentação** | Ausente — todas linhas no mesmo nível | `style={{ paddingLeft: 24 + indent*16 }}` em items | Manter literal — indent: 1 = 40px, indent: 2 = 56px |
| **Subtotal highlight** | Único `tfoot` "Total" | Subtotais intermediários `border-y-2 border-stone-200 bg-stone-50` + 1 subtotal final `bg-stone-900 text-white` (Resultado operacional) | 3 subtotais: Receita líquida (bg-stone-50), Lucro bruto (bg-stone-50), Resultado operacional (`highlight: true` → bg preto) |

### 3. Densidade

| Aspecto | Decisão MWART |
|---|---|
| Padding header (h) | `pl-6 pr-2 py-2` (28px / 8px) |
| Padding item (i) | `pl-{24+indent*16} pr-2 py-1.5` (1.5 = 6px — mais denso) |
| Padding subtotal | `pl-6 pr-2 py-2.5` (10px — destacado verticalmente) |
| Linhas por viewport | Espera ~16-20 linhas em 1280px (denso, leitura financeira clássica) |
| Gap topo do Card | `px-6 pt-4` (pattern canon `fin-cowork`) |
| Bottom grid gap | `gap-4` entre 2 cards |

### 4. Cor e semântica

| Aspecto | Decisão MWART |
|---|---|
| Header (h) recursivo | `font-medium text-stone-900` |
| Item (i) | `text-stone-600` em label; `text-stone-700` em valor; `text-stone-400` em % RL e prev |
| Subtotal intermediário | `text-emerald-700` em valor positivo, `text-rose-700` em negativo |
| Subtotal highlight (Resultado op.) | `bg-stone-900 text-white` (mesmo "Saldo hoje" do TelaFluxo — mantém família visual) |
| Δ% emerald | `text-emerald-700` (header) / `text-emerald-600` (item, 11.5px) / `text-emerald-400` (subtotal highlight) |
| Δ% rose | mesmo padrão em rose |
| Barra inline emerald | `bg-emerald-400` (item positivo) |
| Barra inline rose | `bg-rose-400` (item negativo) |
| Trilho barra | `bg-stone-100 rounded-full h-1` |

### 5. Interação / atalhos

| Aspecto | Decisão MWART |
|---|---|
| Toggle período | Click pill → router.get com `?periodo=mes|trim|ano|12m` (preserveScroll) |
| Click linha item | F1: nenhum (read-only); F2 (US-FIN-019): abre drawer drill-down títulos da categoria |
| Click linha header | F1: nenhum; F2: collapse/expand grupo (UX explorer) |
| Click subtotal highlight | F1: nenhum; F2: explica fórmula em tooltip |
| Exportar PDF | F1: chamada endpoint `/financeiro/dre/export-pdf?periodo=mes` — backend usa `dompdf` (já no projeto) |
| Exportar Excel | F1: chamada endpoint `/financeiro/dre/export-xlsx?periodo=mes` — `maatwebsite/excel` (já no projeto) |
| Atalho teclado | F1: nenhum; F2: `1/2/3/4` alterna Mês/Trim/Ano/12m |
| Filtro centro-de-custo | Backlog F2 (US-FIN-DRE-CC) — multi-tenant ROTA LIVRE não usa CC ainda |

### 6. Estado vazio / loading

| Aspecto | Decisão MWART |
|---|---|
| Sem títulos no período | Card visível mas linhas com valor R$ [redacted Tier 0]; subtotal Resultado operacional fica R$ [redacted Tier 0] highlight neutro (cinza claro `bg-stone-700` em vez de preto?) → **decidir em Q5** |
| Sem `fin_categorias` mapeadas | Banner amber no topo: "Mapeie categorias na ordem hierárquica pra ver DRE detalhada" + CTA `/financeiro/categorias` |
| Loading | SSR Inertia padrão; F2: skeleton de 18 linhas (`<TableRowSkeleton>` Cockpit V2) |

### 7. Multi-tenant Tier 0 ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md))

| Aspecto | Decisão MWART |
|---|---|
| Service input | `DreService::montar(int $businessId, string $periodoTipo = 'mes', ?string $anchorMes = null): array` — `business_id` 1º arg sempre |
| Models | `Titulo`, `Categoria` já têm `BelongsToBusiness` global scope |
| Controller | `business_id` lido de `session('user.business_id')` (consistente com `RelatoriosController` linha 46); NUNCA aceitar via query |
| Pest test obrigatório | cross-tenant: `Titulo` biz=1 + biz=99 + auth biz=1 → assert `linhas[]` só contém valor de biz=1; mapping categorias biz=99 NÃO vaza |

### 8. Performance

| Aspecto | Decisão MWART |
|---|---|
| Query strategy | 1) `Titulo` GROUP BY (`competencia_mes`, `categoria_id`, `tipo`) WHERE `business_id` AND `competencia_mes` IN ([mes_atual, mes_anterior]) AND `status != cancelado` — 1 query (com mês_anterior junto pra Δ%); 2) categorias agrupadoras: `Categoria` WHERE `business_id` AND `tipo` IN (receita/despesa) — 1 query |
| Mapping hierárquico | Em PHP no Service (não SQL): `Titulo` rows → bucket por `categoria.codigo` prefix (`1.1.*` = receita bruta items, `2.1.*` = custos diretos items, etc) → monta `linhas[]` ordenada |
| Cache | F1: nenhum (dataset ~50-500 títulos × 2 meses, agregação rápida); F2: Redis 5min se p95 ≥ 400ms com tenant grande |
| p95 target | <250ms (Service + render) com 2k títulos no período |
| Defer Inertia | `Inertia::defer(fn () => $this->buildDrePayload(...))` opcional — DRE não é abaixo da dobra, deferir não ajuda muito (skill `inertia-defer-default` recomenda só queries pesadas) — **F1 sem defer** |

---

## §F1.5 Critique — score esperado

**Score: 86 / 100** (estimado pelo canon TelaDRE + ausência de gaps visuais explícitos).

Pontos perdidos:
- **−5** ausência de filtro por centro de custo (ROTA LIVRE não usa CC, mas Wagner já comentou que vai usar quando tiver 3 vendedores)
- **−4** sem comparativo "Realizado vs Orçado" (Wagner pediu informalmente — backlog US-FIN-DRE-ORC)
- **−3** sem export ZIP (PDF+Excel+CSV num zip único — Eliana pede)
- **−2** sem persistência de "última visualização" (volta sempre em Mês — Wagner queria 12m default)

**Aprovado pra F3 com gate ≥80.** Próximo passo: Wagner aprova screenshot canon `TelaDRE` (não esta tabela) + Q1-Q8 abaixo.

---

## §Decisões abertas pro Wagner (BLOQUEIA F3)

> 9 questões (Q1..Q7 + Q8a/Q8b). Cada uma tem **recomendação minha**. Wagner aprova/contesta cada uma.

### Q1 — Mapping `fin_categorias.codigo` → linhas DRE: como?

**Recomendação:** mapping declarativo em `DreService` (PHP array constante):

```php
const DRE_TEMPLATE = [
    // type, label, kind, indent, source (regex em fin_categorias.codigo OU fixed)
    ['type' => 'h',        'label' => 'Receita operacional bruta', 'kind' => 'rec', 'source' => 'codigo LIKE "1.1.%" AND tipo="receita"'],
    ['type' => 'i_group',  'source' => 'codigo LIKE "1.1.%" GROUP BY categoria_id', 'indent' => 1],
    ['type' => 'h',        'label' => '(−) Deduções', 'kind' => 'ded', 'source' => 'codigo LIKE "1.9.%" AND tipo="receita"'],
    ['type' => 'i_group',  'source' => 'codigo LIKE "1.9.%"', 'indent' => 1],
    ['type' => 'subtotal', 'label' => 'Receita líquida', 'calc' => 'sum(prev_h_receita) - sum(prev_h_deducoes)'],
    ['type' => 'h',        'label' => '(−) Custos diretos', 'kind' => 'ded', 'source' => 'codigo LIKE "2.1.%" AND tipo="despesa"'],
    ['type' => 'i_group',  'source' => 'codigo LIKE "2.1.%"', 'indent' => 1],
    ['type' => 'subtotal', 'label' => 'Lucro bruto', 'calc' => 'receita_liquida - custos_diretos'],
    ['type' => 'h',        'label' => '(−) Despesas operacionais', 'kind' => 'ded', 'source' => 'codigo LIKE "2.2.%" AND tipo="despesa"'],
    ['type' => 'i_group',  'source' => 'codigo LIKE "2.2.%"', 'indent' => 1],
    ['type' => 'subtotal', 'label' => 'Resultado operacional', 'calc' => 'lucro_bruto - desp_operacionais', 'highlight' => true],
];
```

**Risco:** ROTA LIVRE biz=4 hoje provavelmente não tem `fin_categorias.codigo` populado com hierarquia (1.1.01, 2.1.01, etc). Cheque rápido em prod antes — se não, vira US-FIN-DRE-SEED (seed hierarquia padrão Comunicação Visual no `Database/Seeders/CategoriaSeeder.php`).

**Alternativa rejeitada:** mapping editável pelo usuário em tela `/financeiro/dre/mapping` — adia F3 em 8h+ e ROTA LIVRE não precisa ainda.

### Q2 — % RL é sobre Receita Líquida ou Receita Bruta? + sinal?

**Recomendação:** **% sobre Receita Líquida COM SINAL do numerador**. Subtotal "Receita líquida" = base = 100%. Itens acima e abaixo divididos por essa base, **preservando sinal** (deduções/custos/despesas mantém `-`; receitas mantém `+` implícito).

Confirmado no screenshot canon 2026-05-20:
- `Receita operacional bruta R$ [redacted Tier 0]` → `109.3%` (positivo)
- `(−) Deduções R$ −1.260` → `-9.3%` (negativo com sinal explícito)
- `(−) Custos diretos R$ −5.180` → `-38.1%`
- `(−) Despesas operacionais R$ −7.042` → `-51.8%`
- `Receita líquida R$ [redacted Tier 0]` → `100.0%` (sempre 100% positivo — denominador)
- `Resultado operacional R$ [redacted Tier 0]` → `10.1%`

**Razão:** Wagner é dono que vai consumir DRE pra decisão; padrão de mercado (Conta Azul / Treasy / Omie) usa RL como denominador. SAP/Totvs idem. **Sinal preservado** é importante porque "deduções foram 9.3% da RL" é leitura direta — sem o sinal, fica ambíguo se é positivo ou negativo.

### Q3 — Δ% vs mês anterior literal ou média 3m?

**Recomendação:** **Mês anterior literal em F1** (igual canon — `prev` field). Média 3m vira `?compare=avg3m` em F2 (US-FIN-DRE-COMPARE).

**Razão:** Canon mostra "Abr/2026" literal. Simples, claro. Média 3m exige UX adicional (label coluna muda) e Wagner ainda não pediu.

### Q4 — Toggle período Mês/Trim/Ano/12m — todos em F1?

**Recomendação:** **F1 entrega só "Mês"** funcional; pills Trim/Ano/12m **renderizam mas com tooltip "Em breve"** (opacity-50 disabled). Trim+Ano+12m entram em F2 como US-FIN-DRE-PERIODOS — cada um exige decisão de agregação (Trim = soma 3 meses? média? último mês?).

**Razão:** "Mês" cobre 80% dos casos. Apresentar a UI completa ajuda usuário a entender o que vem; clicar e ver tooltip é honesto sobre estado.

**Alternativa rejeitada:** F1 com 4 períodos funcionais — dobra esforço (cada um precisa Pest + decisão UX) e atrasa entrega.

### Q5 — Subtotal final "Resultado operacional" SEM dado: highlight preto ou neutro?

**Recomendação:** **Sempre highlight preto** (`bg-stone-900 text-white`), valor = R$ [redacted Tier 0] Mensagem visual: "DRE existe, só não há movimento". Se valor for negativo, texto fica `text-rose-400` (em cima do preto) — mantém família.

**Razão:** Família visual constante reduz "uncanny valley". Estado vazio fica reservado pro banner amber (sem categorias mapeadas).

### Q6 — Card "Margem operacional" — meta 12% hardcode ou config tenant?

**Recomendação:** **Hardcode 12% em F1** (igual margem mínima R$ [redacted Tier 0]k do Fluxo). F2 vira config `business_settings.dre_margem_meta` (decimal default 12.00).

**Razão:** Mesma decisão do Q3 do Fluxo — 12% é número de Comunicação Visual benchmark. ROTA LIVRE não tem opinião. Configurável depois.

### Q7 — Card "Top categorias receita" — vem do mesmo mapping DRE ou query independente?

**Recomendação:** **Mesma query** (`Titulo` GROUP BY categoria_id WHERE tipo=receita) já feita no Service. Pega top 3 por `SUM(valor_total)`. Sem N+1.

**Razão:** Reaproveitar — dados idênticos aos items "Receita operacional bruta" do DRE. Garante consistência (se DRE diz Banner = R$ [redacted Tier 0] card também diz R$ [redacted Tier 0]).

### Q8a — Topnav contextual: copy-paste de Unificado ou extrair `<FinModuleTopnav>` shared agora?

**Recomendação:** **F1 = copy-paste inline** do bloco `os-page-h-r` da [`Unificado/Index.tsx:963-1043`](../../../resources/js/Pages/Financeiro/Unificado/Index.tsx:963) (7 botões + handlers locais adaptados). **Extrair `<FinModuleTopnav>` shared = US-FIN-TOPNAV-COMPONENT** (backlog separado, ~2h trabalho).

**Razão:**
- Skill `commit-discipline`: 1 PR = 1 intent. Misturar refactor de componente shared com entrega DRE infla escopo.
- Hoje só Unificado usa todos 7 botões; Fluxo usa header reduzido. Antes de extrair, precisa decidir: o componente toma `actions={['buscar','resumir','fechamento','apresentar','conciliar','plano-contas','novo']}` opt-in OU é tudo-ou-nada? Decisão UX adicional.
- Quando DRE em prod → 2 telas duplicam → refactor vira óbvio (Refactor of three).

**Handlers DRE F1:**
- `Resumir mês` → narrativa exec compute-based do DRE (Margem op vs meta, top crescimentos, top quedas) — reutiliza padrão `<FinMonthResume>` mas com payload DRE
- `Fechamento` → mesmo `<FinChecklistFechamento>` (já tem passo "Conferir DRE")
- `Apresentar` → modo fullscreen mostrando só a tabela DRE (sem topnav, sem sidebar) — Wagner em call investidor/contador
- `Conciliar` / `Plano de contas` / `Novo lançamento` → `router.visit()` mesmo padrão Unificado
- `Buscar ⌘K` → reusa `setPaletteOpen` Cmd palette do Unificado
- Download icon-only → handler Exportar (atalho rápido — duplica os botões "Exportar PDF / Excel" do Card só por consistência canon; pode ser stub `setPaletteOpen` em F1 igual Unificado linha 1024)

### Q8b — Manter ou deprecar `Pages/Financeiro/Relatorios/Index.tsx`?

**Decisão Wagner já tomada (2026-05-20):** **Manter Relatorios com Fluxo+Resumo, deprecar tab DRE.**

**Detalhamento técnico:** tab DRE em `Relatorios/Index.tsx` (linha 168) vira link redirect:
```tsx
<TabBtn active={false} onClick={() => router.get('/financeiro/dre')}>DRE Gerencial →</TabBtn>
```
ou some completamente da Relatorios e fica só Fluxo+Resumo (2 tabs). **Sugestão:** sumir da Relatorios (Fluxo já é tela dedicada `/financeiro/fluxo`, então Relatorios fica só "Resumo do período" — vira candidata futura a virar parte do Dashboard, mas isso é F3+).

**Outras decisões implícitas:**
- Backend `RelatoriosController::montarDre()` (linha 131-207) e `csvDre()` (linha 371) ficam vivos enquanto rota `/financeiro/relatorios?tipo=dre` (CSV) ainda for chamada. Recomendo deprecar após F3 do `DreController` ter o seu próprio `exportCsv`/`exportPdf`/`exportXlsx`.

---

## §Próxima ação após Wagner aprovar Q1..Q8b + screenshot canon (✓ aprovado 2026-05-20)

[CL] executa em **4 PRs sequenciais (1 onda cada, ≤300 linhas)**:

### PR A — docs (este artefato + charter) ~30min
1. `memory/requisitos/Financeiro/dre-visual-comparison.md` ← este arquivo, virá `status: approved` após Wagner
2. `resources/js/Pages/Financeiro/Dre/Index.charter.md` ← Mission/UX targets/Anti-hooks

### PR B — backend (DreController + DreService + Pest) ~90min
3. `Modules/Financeiro/Services/DreService.php` — `montar(int $businessId, string $periodoTipo = 'mes', ?string $anchorMes = null): array` retornando `linhas[]` + `meta` (período label, %RL base, top categorias receita)
4. `Modules/Financeiro/Http/Controllers/DreController.php` — `index()`, `exportPdf()`, `exportXlsx()`, `exportCsv()`
5. `Modules/Financeiro/Tests/Feature/DreControllerTest.php` Pest — cross-tenant biz=1 vs biz=99 + smoke 200 + props shape + linha types + subtotals calc
6. `Modules/Financeiro/Routes/web.php` — 4 rotas (`dre.index`, `dre.export-pdf`, `dre.export-xlsx`, `dre.export-csv`)

### PR C — frontend (Page + sidebar) ~75min
7. `resources/js/Pages/Financeiro/Dre/Index.tsx` — espelha `TelaDRE` canon literal:
   - `os-page-h fin-page-h` + topnav contextual 7 botões (copy-paste inline de Unificado:956-1043, adaptando handlers)
   - Card "DEMONSTRAÇÃO DE RESULTADO · Maio 2026" com toggle Mês/Trim/Ano/12m + Exportar PDF/Excel
   - Tabela 6-col hierárquica (h/i/subtotal/highlight)
   - Bottom grid 2-col: `<FinDreMargemCard>` + `<FinDreTopCategoriasCard>`
8. `Modules/Financeiro/Http/Controllers/DataController.php::modifyAdminMenu()` — entrada "DRE" no submenu Financeiro entre Fluxo e Categorias
9. Reutilizar componentes existentes onde couber: `<FinChecklistFechamento>` (mesmo do Unificado), `<FinMonthResume>` (extender payload pra DRE) — não duplicar.

### PR D — cleanup Relatorios + smoke prod ~45min
9. `resources/js/Pages/Financeiro/Relatorios/Index.tsx` — remove tab DRE (`TabId` vira `'fluxo' | 'resumo'`, `DrePanel` deletado, props `dre` sai)
10. `Modules/Financeiro/Http/Controllers/RelatoriosController.php` — remove `montarDre()` + `csvDre()` (manter só `montarFluxo` e `montarResumo`)
11. Smoke prod: `curl -sv https://oimpresso.com/financeiro/dre` retorna 200; redirect tab antigo `?tab=dre` → 302 `/financeiro/dre`

**Esforço total estimado (10x IA-pair [ADR 0106](../../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md)): ~3h45 [CL]** (PR C subiu 15min por causa topnav contextual inline copy + handlers DRE-specific).

Screenshot canon **já aprovado 2026-05-20** ([gate F1.5 ADR 0107](../../decisions/0107-emendation-0104-visual-comparison-gate-f3.md) cumprido). Falta só Wagner aprovar Q1..Q8b.

---

**Quem aprova Q1..Q8b:** Wagner. Após aprovação, esta doc vira `status: approved` e [CL] dispara PR A.

## §Backlog gerado por esta doc

- **US-FIN-TOPNAV-COMPONENT** — extrair `<FinModuleTopnav>` shared (consome `actions={[...]}` opt-in) e migrar Unificado + DRE pra usá-lo. Trigger: quando 3ª tela precisar dos mesmos 7 botões (Refactor of three).
- **US-FIN-DRE-CC** — filtro por centro de custo na DRE. Trigger: Wagner contratar 2º vendedor com comissão por CC.
- **US-FIN-DRE-ORC** — comparativo Realizado vs Orçado por linha DRE. Trigger: Wagner começar a fazer orçamento mensal formal.
- **US-FIN-DRE-PERIODOS** — Trim/Ano/12m funcionais (em F1 ficam disabled). Trigger: Wagner pedir "queria ver DRE do ano".
- **US-FIN-DRE-SEED** — `CategoriaSeeder::seedHierarquiaCV()` se ROTA LIVRE biz=4 não tiver `fin_categorias.codigo` hierárquico mapeado. Trigger: descobrir em F3 que biz=4 está sem o mapping.
- **US-FIN-DRE-COMPARE** — `?compare=avg3m` na URL pra trocar Δ% vs mês anterior por Δ% vs média 3 meses. Trigger: Wagner pedir contexto sazonal.
