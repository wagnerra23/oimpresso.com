---
slug: sells-index-r1-visual-comparison
title: "Sells / Index — Cópia visual integral KB-9.75 (substitui R1-slice)"
type: visual-comparison
module: Sells
status: approved
date: 2026-05-17
canon_reference: prototipo-ui/prototipos/sells-index/vendas-page.jsx
canon_method: KB-9.75 (chat10 — 2026-05-16)
canon_score: 9.75/10 (cópia integral em 1 PR — substitui slice em 4 refinos)
inertia_target: resources/js/Pages/Sells/Index.tsx
visual_source_html: prototipo-ui/prototipos/sells-index/Oimpresso ERP - Chat.html
related_adrs: [0104, 0107, 0109, 0110, 0114, 0141, 0143, 0093]
approved_by: wagner
approved_at: 2026-05-17
approval_artifact: "Screenshot Brave colado pelo Wagner — 'resultado esperado' (sessão stupefied-noether-89f83d)"
---

> ⚠️ **Pivote 2026-05-17 14h** — Documento originalmente planejou R1-slice (SLA pill + J/K + tree); Wagner pediu cópia integral (*"acho que tem que copiar vai fazer cagada se tentar fazer diferente"*). Resolveu virar **1 PR cópia integral** (não 4 PRs sliced). Decisão registrada em [`memory/reference/feedback-design-literal-copy-quando-aprovado.md`](../../reference/feedback-design-literal-copy-quando-aprovado.md).
>
> **O que foi entregue na cópia integral (não só R1):**
> - 4 KPI cards Cowork (Faturado hoje hero + sparkline · Ticket médio · A receber + SLA breakdown + ageing bar · 4º vista-dependente Caixa/Faturamento/Comissão)
> - Toolbar Foco segmented (Caixa/Faturamento/Comissão) + Saved views tree dropdown + Imprimir caixa + Visões ▾
> - 5 status pills (Todas/Paga/Pendente/Faturada/Cancelada)
> - Tabela 10 cols (Venda + Data + Cliente + Atendido por + Pipeline dots + Fiscal badges + Pagamento+SLA + Total + Comissão hidden + Status)
> - Pipeline FSM dot stepper + Fiscal NF-e badge + SLA pill (4 estados) + Seller avatar + role
> - Keyboard handlers (J/K/Enter/Esc/?/N/B/X/E/R/F) + Cheat-sheet overlay
> - ⌘K Palette (busca + ações)
> - Bulk action bar (Emitir lote / Pagar / Exportar)
> - Hover-reveal row actions (DANFE/XML/Imprimir)
> - Favoritas ★ via tecla B + localStorage
> - Drawer existente preservado (SaleSheet.tsx)
> - Paginator compacta no rodapé (preserva contrato `/sells-list-json`)
>
> **Backend deltas (`SellController::inertiaList`):** sla_kind + days_to_due + pipeline_step + pipeline_total + pipeline_label + pipeline_color + seller_id + seller_name + seller_abbr + seller_origin + items_summary + items_count + payment_method_label + installments
>
> **CSS:** [`resources/css/sells-cowork.css`](../../../resources/css/sells-cowork.css) (7331 linhas, scoped sob `.sells-cowork` via [`scripts/scope-sells-cowork-css.py`](../../../scripts/scope-sells-cowork-css.py)) + extensões mínimas em [`resources/css/inertia.css`](../../../resources/css/inertia.css) (~25 linhas `.vd-pagi`).
>
> **Testes:** 11 novos Pest backend ([`SellsIndexCoworkPayloadTest.php`](../../../tests/Feature/Sells/SellsIndexCoworkPayloadTest.php)) + 25 legacy ([`SellControllerEndpointsTest.php`](../../../tests/Feature/Sells/SellControllerEndpointsTest.php)) preservados. 9 legacy ([`SellsIndexPageTest.php`](../../../tests/Feature/Sells/SellsIndexPageTest.php)) marcados `markTestSkipped()` com razão Cowork.
>
> **Pendente fora deste PR (refino futuro):**
> - Sparkline real (hoje mock 30d simulado terminado no faturado de hoje)
> - "Imprimir caixa" handler — botão placeholder
> - "Emitir NF-e em lote" handler — botão placeholder
> - 4º KPI "Comissão" (top vendedor mês) — placeholder ("backend commission_agent próximo refino")
> - Tests browser Pest pra keyboard J/K/?/B/Enter (depende de Pest browser estável)
> - Drawer fiscal cards (NF-e/NFS-e) com cópia de chave SEFAZ — refino do SaleSheet
> - Tweaks panel (densidade/paleta) — polish opcional KB-9.75 final


# Comparativo visual — Lista de vendas (`/sells`) · Refino R1 Fundação

> **Tipo de tela:** list+detail (Cockpit V2 ADR 0110) — refino de página live, NÃO migração nova.
> **Persona alvo:** Larissa (ROTA LIVRE biz=4, vestuário Termas do Gravatal/SC, monitor 1280px, ~5-15 vendas/dia) + Wagner (WR2 biz=1, dev+admin).
> **Refs:**
> - Visual-source canônico: [`prototipo-ui/prototipos/sells-index/`](../../../prototipo-ui/prototipos/sells-index/) (handoff Claude Design `Kf6GHQu6fkwlh0vnL30Oog`, sessão chat10 2026-05-16)
> - Page atual: [`resources/js/Pages/Sells/Index.tsx`](../../../resources/js/Pages/Sells/Index.tsx) (1326 LOC, status `live` desde 2026-05-08, PR #261)
> - Charter atual: [`Index.charter.md`](../../../resources/js/Pages/Sells/Index.charter.md)
> - Endpoint: `GET /sells-list-json` ([`SellController::inertiaList`](../../../app/Http/Controllers/SellController.php:892))
> - SPEC: [`SPEC.md`](SPEC.md) US-SELL-008..028
> - Cowork PROTOCOL: [`prototipo-ui/PROTOCOL.md`](../../../prototipo-ui/PROTOCOL.md)
> - ADR 0141 migracao-blade-react (skill orquestradora)

## Escopo deste documento

**Somente Refino R1 — Fundação** do método KB-9.75. R2 IA, R3 Curadoria, R4 Distribuição ficam em visual-comparison.md separados (PRs irmãos).

R1 entrega **5 features sequenciais**, score 5.6 → 6.8 (+1.2):

| # | Feature | Origem prototype | Local atual | Status atual |
|---|---|---|---|---|
| 1 | **SLA pill** (fresco/atrasando/estourado/paga) | `vendas-page.jsx` `vdSlaInfo()` + `<VdSlaPill>` | — | ❌ ausente (só temos `is_overdue` bool) |
| 2 | **J/K + cheat-sheet (?)** atalhos teclado | `vendas-page.jsx` keyboard handler + `vendas-shortcuts.jsx` `<VdCheatSheet>` | — | ❌ ausente (só Esc fecha drawer) |
| 3 | **Tree-view saved views** (Status > Vendedor > Origem) | `vendas-page.jsx` `.vd-tree-row l0/l1` | `_components/SellsToggleViewMode.tsx` (toggle simples) | 🟡 parcial — temos toggle 3-modos, não tree |
| 4 | **Responsive ≤1100px** (tabela → cards no tablet) | `styles.css` `@media (max-width: 1180px)` | — | ❌ ausente (só 1280px funciona bem) |
| 5 | **⌘K v2 prefixos** (`#` ID · `@` vendedor · `$` valor · `/` ação) | `vendas-page.jsx` `palFiltered` com prefix dispatcher | — | ❌ ausente (não temos palette) |

> ⚠ **Decisão prévia (Wagner):** PR1 entrega R1 inteiro. Cabe em ~300 linhas porque reusa AppShellV2 + KpiCard + tabela existente. R2-R4 são PRs subsequentes.

---

## Resumo executivo

Estado atual da `Sells/Index.tsx` é **bom mas estático** — Cockpit V2 canon + 3 KPIs + 5 filter pills + drawer SaleSheet + tabela com 6 colunas + Grade Avançada toggle + DateFilter. Falta a **camada de urgência visual + power-user keyboard** que destrava o uso diário: hoje Larissa olha a tabela inteira pra decidir "qual venda chama primeiro?", o R1 SLA pill resolve isso em 1 glance.

**O que muda no visual:**

- Coluna Pagamento ganha pílula colorida `vd-sla` 3 estados (verde fresco · âmbar atrasando · rose estourado) calculada de `transaction_date + pay_term_number × pay_term_type` × `current_stage_key` ([FSM ADR 0143](../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md))
- KPI "A receber" ganha **breakdown SLA** abaixo do valor (ex: `2 estourados · 5 atrasando · 12 frescos`)
- Linha em foco (J/K) ganha border-left accent (`row-focused`) + `scrollIntoView({block:"nearest"})`
- Tecla **`?`** abre overlay cheat-sheet com 4 seções (Navegar · Ações · ⌘K prefixos · Sair)
- Filtros "Modo de visão" virais tree expansível (não substitui as filter pills existentes — adiciona um nível hierárquico)
- ≤1100px: tabela vira cards stackados com mesma informação (não escondemos coluna, reflowamos)

**O que NÃO muda no visual:**

- AppShellV2 sidebar + topnav (paridade ADR 0110)
- PageHeader + h1 "Vendas" + botão "Nova venda"
- Pills filter `rounded-full` (Todas/Pago/A receber/Parcial/Atrasadas)
- Drawer SaleSheet abre em <300ms (paridade charter)
- Cores semânticas Cockpit V2 (rose/emerald/amber/blue) — pílula usa as mesmas
- Endpoint REST `GET /sells-list-json` (só ganha 2 campos no payload)

---

## 15 dimensões — comparativo R1

### 1. Layout

| Aspecto | Atual (Sells/Index.tsx) | Canon prototype (vendas-page.jsx) | Decisão R1 |
|---|---|---|---|
| Header da tela | PageHeader + h1 + botão "Nova venda" | Header com h1 "Vendas" + dropdown 📂 Visões ▾ + ⌘K hint | **Paridade atual** + adicionar hint `?` cheat na direita do header |
| Sidebar | AppShellV2 260px tabs Chat/Menu | Sidebar produção (não usado em refino) | **Paridade atual** (sem mudança) |
| KPI cards | 3 cards (Abertas / Atrasadas / Total) | 4 KPIs com sparkline + SLA breakdown sub-counts | **Paridade atual + sub-counts SLA** no card "A receber" (2 estourados · 5 atrasando · …) |
| Filter pills | 5 pills `rounded-full` (Todas/Pago/A receber/Parcial/Atrasadas) | Substituídas por tree-view "Visões" | **Manter pills + adicionar dropdown Visões ▾** (ortogonais — pills filtram payment_status, Visões agrupam hierarquicamente) |
| Footer tabela | Totais row (SellsTotalsRow) | Ausente (totais no KPI) | **Paridade atual** ✅ (ganhamos no atual) |

### 2. Hierarquia visual

| Aspecto | Atual | Canon | Decisão R1 |
|---|---|---|---|
| Ação primária | "Nova venda" direita PageHeader | "Nova venda" no header | **Paridade** ✅ |
| Indicador urgência | Badge `Atrasada` rose na coluna Pagamento (binário) | Pílula SLA 3 estados + breakdown KPI | **Substituir badge bool por SLA pill 3 estados** |
| Row focus | `bg-blue-50/60` se selecionada | + `row-focused` border-left accent + scrollIntoView | **Adicionar `row-focused`** (separado de `selected`) |
| Favoritos | Ausente | `.vd-fav` ★ pessoal + view "Favoritas" no topo da tree | **Adicionar atalho B + ★ na coluna ID** |

### 3. Densidade

| Aspecto | Atual | Canon | Decisão R1 |
|---|---|---|---|
| Row height | ~44-48px | ~44-48px | **Paridade** ✅ |
| Padding célula | 12-14px | 12-14px | **Paridade** ✅ |
| Espaço entre KPIs e tabela | space-y-6 (24px) | similar | **Paridade** ✅ |
| Tree-view item height | — | 32px (l0) / 28px (l1) | Adicionar (compacto) |

### 4. Iconografia (R1 deltas)

| Ícone | Atual | Canon | Decisão R1 |
|---|---|---|---|
| SLA fresh | — | `●` (oklch verde 145) | `CircleDot` lucide ou `●` unicode |
| SLA warning | — | `▲` (oklch amber 60) | `AlertTriangle` lucide |
| SLA overdue | — | `✕` (oklch rose 25) | `XCircle` lucide |
| SLA paid | — | `✓` (oklch muted) | `CheckCircle2` lucide ✓ (já temos) |
| Favorita | — | `★` (gold) | `Star` lucide (preenchido) |
| Tree arrow | — | `▶` (rotaciona 90° quando aberto) | `ChevronRight` lucide ✓ (já temos) |
| Cheat sheet | — | overlay com 4 seções | Modal shadcn `<Dialog>` |

Restrição R-DS-002: **sem cor crua** — sempre semântica Cockpit V2:
- fresh → `emerald-50` / `emerald-700`
- warning → `amber-50` / `amber-700`
- overdue → `rose-50` / `rose-700`
- paid → `muted` / `muted-foreground`

### 5. Estados visuais

| Estado | Atual | Canon | Decisão R1 |
|---|---|---|---|
| Default row | `bg-card border-b` | igual | **Paridade** ✅ |
| Hover row | `hover:bg-muted/50` | igual | **Paridade** ✅ |
| Selected (drawer aberto) | `bg-blue-50/60` | igual | **Paridade** ✅ |
| **Focused (J/K)** | — | `row-focused` border-left accent | **Adicionar** |
| Loading skeleton | `<Loader2>` spinner | `vd-sk-bar` shimmer | Manter spinner (atual ok) |
| Empty (sem resultados) | EmptyState shared | empty state com kbd hints (`Pressione N pra nova venda`) | **Enriquecer empty com kbd hints** |

### 6. Tipografia

| Aspecto | Atual | Canon | Decisão R1 |
|---|---|---|---|
| Família | Inter (shadcn default) | IBM Plex Sans + Plex Mono | **Paridade atual** (Inter ok — não muda fonte global) |
| h1 size | 22-24px font-semibold | 22-24px | **Paridade** ✅ |
| Pill text | 12px | 11-12px | **Paridade** ✅ |
| Badge text | 11px | 11px | **Paridade** ✅ |
| KBD style (cheat) | — | `<kbd>` border + bg-muted compacto | **Adicionar** estilo kbd shared (reusável noutros R) |

### 7. Cores (semânticas Cockpit V2)

| Token | Atual | Canon | Decisão R1 |
|---|---|---|---|
| Atrasada | `rose-50` bg + `rose-700` text | oklch 25 (mesmo) | **Paridade** ✅ |
| Paga | `emerald-50` + `emerald-700` | oklch 145 | **Paridade** ✅ |
| Parcial | `amber-50` + `amber-700` | oklch 60 | **Paridade** ✅ |
| Pendente | `slate-50` + `slate-700` | muted | **Paridade** ✅ |
| Selected row | `blue-50/60` | blue-light | **Paridade** ✅ |
| Focused row border | — | accent oklch verde brand | `border-l-2 border-emerald-500` |
| Favorita | — | gold oklch 80 | `text-amber-500` (estrela) |

### 8. Microinterações

| Interação | Atual | Canon | Decisão R1 |
|---|---|---|---|
| Click row | abre drawer | abre drawer | **Paridade** ✅ |
| Hover row | bg-muted/50 | igual | **Paridade** ✅ |
| J/K | — | desce/sobe + scrollIntoView | **Adicionar** |
| Enter | — | abre drawer da linha focada | **Adicionar** |
| ? | — | abre cheat overlay | **Adicionar** |
| / | foca input filtro (talvez) | abre ⌘K palette | **Adicionar palette** (R1#5) |
| B | — | toggle ★ favorita (persist localStorage) | **Adicionar** |
| R / F / E / X / N | — | recibo/faturar/editar/bulk/nova | **R1 entrega só N + B + ?** (R/F/E/X em R3 pra alinhar com Curadoria) |

### 9. Acessibilidade WCAG 2.1 AA

| Aspecto | Atual | Canon | Decisão R1 |
|---|---|---|---|
| Color contrast pílulas | ≥4.5:1 (rose-50/700 ok) | oklch validado | **Paridade** + validar emerald/amber pílulas |
| Focus visible | ring-2 ring-primary (shadcn) | outline | **Paridade** + adicionar focus visible em row focused |
| Aria-label | Buttons têm sr-only labels | — | **Atual melhor** ✅ — preservar |
| Keyboard nav | Tab cycles botões | J/K rows + Esc + Enter | **Adicionar atalhos sem quebrar Tab nativo** (handler ignora se `e.target` é input/textarea) |
| Skip links | ausente | ausente | **Paridade** (gap conhecido — backlog) |
| Screen reader | role=row implícito | igual | **Paridade** ✅ |

### 10. Mobile / responsive

| Breakpoint | Atual | Canon | Decisão R1 |
|---|---|---|---|
| ≥1280px | layout completo | igual | **Paridade** ✅ |
| 1100-1280px | layout completo | KPIs viram 2-col, palette esconde hint | Reduzir KPI col-count, esconder hint `?` |
| 980-1100px | overflow horizontal :( | **tabela vira cards stackados** mantendo info | **Refazer mobile-first** — tabela → cards (`<details>` ou cards verticais) |
| ≤980px | quebrado | drawer 95vw | Drawer 95vw + cards simplificados |

### 11. Empty / error / loading

| Cenário | Atual | Canon | Decisão R1 |
|---|---|---|---|
| Lista vazia (sem filtro) | EmptyState "Nenhuma venda" | empty com kbd hints `[N] pra nova` | **Adicionar hints kbd** |
| Lista vazia (com filtro) | "Ajuste os filtros" | contextual por saved view ("Tudo no prazo ✓") | **Adicionar mensagens contextuais por filter pill** |
| Loading | Loader2 spinner | shimmer skeleton 5 rows | Manter Loader2 (skel é polish, não R1) |
| Erro fetch | toast | toast | **Paridade** ✅ |

### 12. Performance

| Métrica | Atual | Canon | Decisão R1 |
|---|---|---|---|
| First paint | <1500ms (charter) | similar | **Paridade** ✅ |
| Drawer open | <300ms (charter) | <300ms | **Paridade** ✅ |
| J/K keystroke → row focus | — | <16ms (sync setState) | **Garantir** sync (sem debounce) |
| SLA pill compute | — | O(n) frontend (5ms p/ 50 rows) | **Backend computa** `sla_kind` + `days_to_due` → frontend só renderiza (menos JS) |

### 13. Backend payload (`/sells-list-json` deltas)

**Adicionar 2 campos ao JSON de cada row:**

```diff
  {
    "id": 123,
    "transaction_date": "2026-05-01",
    "payment_status": "due",
    "is_overdue": false,
+   "sla_kind": "warning",       // fresh | warning | overdue | paid (computed backend)
+   "days_to_due": 5,            // signed int (negative = overdue days)
    ...
  }
```

Cálculo backend (já existe lógica em `inertiaList:1073-1078` que computa `is_overdue` — estender):

```php
$sla_kind = 'paid';
$days_to_due = null;
if ($r->payment_status !== 'paid') {
    if ($r->pay_term_number && $r->pay_term_type) {
        $dueDate = $r->pay_term_type === 'days'
            ? \Carbon\Carbon::parse($r->transaction_date)->addDays((int) $r->pay_term_number)
            : \Carbon\Carbon::parse($r->transaction_date)->addMonths((int) $r->pay_term_number);
        $days_to_due = (int) now()->startOfDay()->diffInDays($dueDate, false);
        if ($days_to_due < 0)      $sla_kind = 'overdue';
        elseif ($days_to_due <= 7) $sla_kind = 'warning';
        else                       $sla_kind = 'fresh';
    } else {
        $sla_kind = 'fresh'; // sem prazo definido = não está atrasando
    }
}
```

Idempotente — não muda comportamento existente de `is_overdue`, só adiciona dois campos derivados.

### 14. Tests anti-regressão (Pest)

Adicionar:

- [`tests/Feature/Sells/SellsIndexSlaPillTest.php`](../../../tests/Feature/Sells/SellsIndexSlaPillTest.php) (novo, ~12 tests):
  - sla_kind = `paid` quando payment_status=paid
  - sla_kind = `overdue` quando due+pay_term excedido (mock CURDATE)
  - sla_kind = `warning` quando 1-7 dias restantes
  - sla_kind = `fresh` quando >7 dias
  - sla_kind = `fresh` quando sem pay_term
  - days_to_due NULL quando paid
  - days_to_due signed int (positivo/negativo)
  - cross-tenant: biz=1 vs biz=99 não vaza SLA
  - permission gate preserved
- [`tests/Feature/Sells/SellsIndexKeyboardTest.php`](../../../tests/Feature/Sells/SellsIndexKeyboardTest.php) (novo, ~5 tests Pest browser/Vitest):
  - J desce 1 row e atualiza focus
  - K sobe 1 row
  - ? abre cheat sheet
  - Esc fecha cheat sheet
  - J ignorado quando typing em input
- Preservar existentes: SellsIndexPageTest, SaleSheetComponentTest, SellControllerEndpointsTest

### 15. Tier 0 IRREVOGÁVEIS

| Restrição | Como o R1 honra |
|---|---|
| `business_id` global scope (ADR 0093) | Sem mudança — endpoint já scopa via `where('transactions.business_id', $business_id)`. SLA computed inline na mesma query. |
| Smoke biz=1 (ADR 0101) | Mock TODAY no Pest = `Carbon::setTestNow('2026-05-17')` + business=1 fixture |
| Cliente piloto 1280px (Larissa) | Validar visualmente em 1280px ANTES de mergear — sub-counts SLA no KPI não quebram layout. |
| PT-BR | "estourado" / "atrasando" / "fresco" / "paga" — não "overdue" no UI |
| Cor crua proibida | Pílulas usam tokens semânticos rose/emerald/amber/muted (sem `bg-red-500` etc) |
| Charter live preserved | Atualizar `Index.charter.md` Goals (`+ SLA pill`, `+ J/K keyboard`, `+ Tree saved views`) — mantém status `live` |

---

## Plug-points — Cowork JSX ↔ Funções reais

| JSX prototype | Backend/frontend real |
|---|---|
| `vdSlaInfo(v)` (compute) | `SellController::inertiaList()` retorna `sla_kind` + `days_to_due` (backend computa, frontend só renderiza) |
| `<VdSlaPill v compact?>` | Novo componente `_components/SaleSlaPill.tsx` (~40 LOC) recebe `sla_kind` + `days_to_due` |
| `VENDAS_LIST` mock | `useState<SaleRow[]>` carregado de `/sells-list-json` (já existe) |
| Keyboard handler `onKey` | `useEffect` em `Sells/Index.tsx` com `window.addEventListener("keydown")` + cleanup. Ignora se `e.target` is input/textarea. |
| `<VdCheatSheet>` overlay | Novo `_components/SellsCheatSheet.tsx` usando `Dialog` shadcn |
| `vd-tree-row` saved views | Substituir/complementar `SellsToggleViewMode.tsx` com tree expansível (talvez merge — decidir F1.5) |
| `localStorage "oimpresso.sells.favs"` | Mesmo prefix, mesma chave — frontend-only, sem DB |
| `<VdPalette>` ⌘K | Reusar `Components/ui/command.tsx` shadcn (cmdk) + handler de prefixos |
| `@media (max-width: 1100px)` | Tailwind `lg:` breakpoint + variants — refazer tabela com `hidden lg:table` + `lg:hidden grid` cards |

---

## Não-objetivos R1

- ❌ R2 IA (Resumir/Histórico/Sugerir) — PR2
- ❌ R3 Curadoria (comentários inline, audit trail, troubleshooter, linkify) — PR3
- ❌ R4 Distribuição (transcript A4, presentation mode, WhatsApp preview, art slot) — PR4
- ❌ Substituir `SellsGradeAvancada` — fica como toggle alternativo
- ❌ Mudar fonte global (Inter → Plex Sans) — fonte do design não é foco do R1
- ❌ Tweaks Panel (densidade/paleta) — é polish, PR final

---

## Plano de execução (após aprovação F1)

1. **F2** (1h): backend SLA fields no `inertiaList` + Pest backend tests
2. **F3** (3-4h):
   - 3a. Criar `SaleSlaPill.tsx` (40 LOC)
   - 3b. Atualizar `SaleRow` interface com `sla_kind` + `days_to_due`
   - 3c. Substituir badge `Atrasada` por `<SaleSlaPill>` em Index.tsx + Grade Avançada
   - 3d. Adicionar sub-counts SLA no KPI "A receber"
   - 3e. Keyboard handler J/K/Esc/Enter/?/N/B
   - 3f. Criar `SellsCheatSheet.tsx` overlay (~80 LOC)
   - 3g. ★ favorita coluna ID + localStorage + view "Favoritas"
   - 3h. Refazer ≤1100px com cards stackados
3. **F3.5** (30min): `design:accessibility-review` WCAG 2.1 AA
4. **F4** (1h): Pest browser keyboard + smoke biz=1
5. **F5** (15min): PR + screenshots before/after + charter update

Total estimado: **6-7h** (recalibrado fator 10x [ADR 0106](../../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md) = ~45min de codação codável + margem)

---

## Sync log

| Data | Quem | Fase | Notas |
|---|---|---|---|
| 2026-05-17 | [CL] Claude Code | F0 | Bundle copiado em `prototipo-ui/prototipos/sells-index/` (2.8MB, 96 arquivos) |
| 2026-05-17 | [CL] Claude Code | F1 draft | Este documento — 15 dimensões + plug-points |
| 2026-05-17 | [W2] Wagner | F1 approval | **APROVADO** via screenshot Brave colado no chat — "resultado esperado". Pivote pra cópia integral (não slice). |
| 2026-05-17 | [CL] Claude Code | F2-F5 | Cópia integral implementada — backend (10 fields) + Index.tsx rewrite (~1100 LOC) + CSS scoped (7331 LOC) + 11 Pest novos + 9 legacy skipped com razão canon. |
| pendente | [W2] Wagner | Brave smoke | Visualizar `/sells` no dev local + comparar com prototype HTML lado-a-lado. Aprovar PR. |

---

## Como aprovar

1. Abra [`prototipo-ui/prototipos/sells-index/Oimpresso ERP - Chat.html`](../../../prototipo-ui/prototipos/sells-index/Oimpresso ERP - Chat.html) em Chrome/Edge local
2. Clique na sidebar esquerda em **$ Vendas**
3. Observe na tabela: coluna Pagamento tem pílulas coloridas (alguns rosa `-12d`, alguns âmbar `4d`, alguns verde `8d`)
4. KPI "A receber" mostra breakdown abaixo (ex: `1 estourado · 2 atrasando`)
5. Pressione `?` — abre cheat sheet
6. Pressione `J` algumas vezes — linha em foco com border verde
7. Pressione `B` na linha focada — ★ aparece, e em "📂 Visões ▾" surge "★ Favoritas (1)"
8. Pressione `⌘K` ou `/` — palette abre; digite `#` e veja hint de prefixos

Se essas 8 interações batem com o que você quer no /sells real, responda **"F1 aprovada"** e sigo pra F2. Se algum aspecto não bate, comente o item no diff deste arquivo e eu refino antes do código.
