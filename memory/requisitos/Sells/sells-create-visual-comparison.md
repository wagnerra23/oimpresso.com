---
id: requisitos-sells-sells-create-visual-comparison
slug: sells-sells-create-visual-comparison
title: "Sells — Comparativo visual da tela Adicionar venda"
type: visual-comparison
module: Sells
status: approved
date: 2026-05-08
canon_reference: os-page.jsx
blade_source: resources/views/sell/create.blade.php
inertia_target: resources/js/Pages/Sells/Create.tsx
approved_by: wagner
approved_at: 2026-05-08
generated_retroactively: true
---

# Comparativo visual — Adicionar venda (`/sells/create`)

> **Tipo de tela:** form de criação com sub-list de produtos
> **Persona alvo:** Larissa (ROTA LIVRE biz=4), monitor 1280px, ~5 vendas/dia
> **Refs:**
> - Blade legacy: [`resources/views/sell/create.blade.php`](../../../resources/views/sell/create.blade.php) (996 LOC, jQuery + Bootstrap panels + Form::)
> - Canon Cockpit list+detail: [`os-page.jsx`](../_DesignSystem/ui_kits/cowork-2026-04-27/os-page.jsx) (1021 LOC)
> - Page MWART atual: [`resources/js/Pages/Sells/Create.tsx`](../../../resources/js/Pages/Sells/Create.tsx)
> - RUNBOOK: [`RUNBOOK-create.md`](RUNBOOK-create.md)
> - SPEC: [`SPEC.md` US-SELL-001..009](SPEC.md)

> ⚠ **Comparativo retroativo** — gerado APÓS migração Sells (PRs #240..#246) pra documentar gaps e direcionar refator. Próximas migrações nascem com este artefato em F1.5 (ADR 0107).

## Resumo executivo

Blade legado tem 18 campos visíveis (3 telas de scroll), header simples sem ações primárias, sem topnav módulo. MWART migrou pra 8 visíveis + 10 colapsáveis (1 tela de scroll), Cards shadcn, defaults inteligentes (Status=final). **Gaps identificados pós-migração:** sem topnav módulo (afeta TODAS telas MWART, P0 separado), sem KPI cards top (canon `os-page.jsx` tem; tela form não cabe — exceção justificada), sem sticky "Salvar venda" (Larissa precisa rolar pra salvar), sem totais visuais durante digitação inicial (só vê quando adiciona produto).

## Tabela comparativa — 8 dimensões

### 1. Layout

| Aspecto | Blade legacy | Canon Cockpit (`os-page.jsx`) | Decisão MWART |
|---|---|---|---|
| Header da tela | `<h1 class="content-header">` simples + título | Header com h1 + p descritiva + 2 ações primárias direita (`os-page-h-l/r`) | PageHeader shared com action slot — **paridade canon** |
| Sidebar | UltimatePOS sidebar 250px com submenus | AppShellV2 sidebar 260px light com tabs Chat/Menu (ADR 0039) | AppShellV2 default (paridade) |
| Topnav módulo | Submenu Vendas dentro do sidebar | Não implementado em AppShellV2 (gap conhecido — ADR 0039 §1) | **GAP P0 — implementar em PR separado** (afeta 78 telas MWART) |
| Footer | Ausente | Ausente | Ausente (paridade) |
| Grid breakpoints | Bootstrap row/col responsive | Tailwind `container max-w-7xl` + `grid-cols-1/2/4 gap-4` | `max-w-7xl` (paridade canon) |
| Sticky elements | Nenhum | Nenhum em os-page (mas usual em forms longos) | **GAP — adicionar "Salvar venda" sticky no scroll** (esta tela é form longo, ≠ canon list) |

### 2. Hierarquia visual

| Aspecto | Blade legacy | Canon Cockpit | Decisão MWART |
|---|---|---|---|
| Ação primária | Botão "Salvar e finalizar" no fim da página (rodapé) | 1 `<button class="os-btn primary">` direita do header | "Salvar venda" no topo direito do PageHeader — **paridade canon** ✅ |
| Ações secundárias | 5+ botões no rodapé ("Salvar como rascunho", "Salvar + imprimir", "Salvar + adicionar", etc) | 1 `<button class="os-btn ghost">` direita | "Cancelar" outline + "Salvar" primary (2 ações) — **simplificação** ✅ |
| Hierarquia tipográfica | h1 (página) + h4 (sub-seções) + spans labels | h1 + p + small labels + bold values (`<small>` + `<b>`) | h1 PageHeader + CardTitle h3 + Label small — **paridade canon** ✅ |
| Densidade de informação | Alta — 18 campos visíveis simultaneamente | Média — KPIs top + tabela rica + filtros | Baixa-média — 8 visíveis + 10 colapsáveis em `<details>` ✅ |

### 3. Densidade

| Aspecto | Blade legacy | Canon Cockpit | Decisão MWART |
|---|---|---|---|
| Espaçamento entre seções | `<div class="row mb-3">` ~16px | `os-stats` gap variable + `os-toolbar` margin | `space-y-6` (24px) — **paridade canon** ✅ |
| Card padding | `.panel-body` ~15px | Tabela rows ~12-14px padding célula | `p-6` (24px Card shadcn) — **mais espaçoso** que canon |
| Line-height | 1.4 Bootstrap | 1.5 Tailwind | 1.5 (paridade) ✅ |
| Tabela density | DataTables jQuery default ~36px row height | `os-row` ~44-48px com avatar + 2 linhas info | Inputs `h-8` (32px) compacto — **mais denso que canon** ⚠ avaliar 1280px |

### 4. Iconografia

| Aspecto | Blade legacy | Canon Cockpit | Decisão MWART |
|---|---|---|---|
| Sistema | Font Awesome 4 misto (`fa-cart`, `fa-money`, `fa-truck`) | lucide-react 100% (Search, Plus, Check) | lucide-react 100% — **paridade canon** ✅ (R-DS-003) |
| Ícones específicos | fa-map-marker (location), fa-shopping-cart, fa-money-bill | I.search, I.plus, I.check | ShoppingCart, Search, Plus, Loader2, Trash2, Wallet — **paridade** ✅ |
| Cor | Hardcoded `text-success` / `text-danger` | Tokens semânticos (sem cor crua) | Tokens shadcn (text-foreground, text-muted-foreground) — **paridade** ✅ (R-DS-002) |

### 5. Estados visuais

| Estado | Blade legacy | Canon Cockpit | Decisão MWART |
|---|---|---|---|
| Default | Bordas planas Bootstrap, sem hover | `bg-card border-border` (panel light) | `bg-card border-border` — **paridade** ✅ |
| Hover | Ausente em rows tabela | `hover:bg-muted/50` em rows clicáveis | Hover na tabela produtos (paridade) ✅ |
| Focus | Outline azul Bootstrap default | `focus-visible:ring-2 ring-accent` | shadcn primitives já trazem (R-DS-006) ✅ |
| Loading | Spinner gif legacy ou nenhum | `<Loader2 className="animate-spin">` | Loader2 lucide (paridade) ✅ pós-PR #243 |
| Empty | Tabela vazia sem CTA, mensagem texto | `os-empty-state` com ícone + título + small + às vezes ação | `<EmptyState>` shared com action `<Button>` — **paridade canon + Q5** ✅ |
| Error | Alert vermelho server-side em rodapé | `<FormError>` por campo + Sentry | errors do useForm por campo (paridade) ✅ |

### 6. Atalhos teclado

| Tecla | Aplicável? | Blade legacy | Canon Cockpit | Decisão MWART |
|---|---|---|---|---|
| ⌘K / Ctrl+K | Sempre (shell) | Ausente | AppShellV2 — busca global | Shell (não esta tela) ✅ |
| J / K | Master/detail | Ausente | Navegar lista (canon list+detail) | N/A — esta tela é form ⚠ |
| E / A | Inbox/triage | Ausente | Concluir/adiar item | N/A |
| / | Tela inteira | Ausente | Focar busca local (canon) | **GAP — focar busca produto (US-SELL-007)** |
| Esc | Sempre | Ausente | Fecha modal/blur input | **GAP — implementar (US-SELL-007)** |
| ⌘+Enter | Form submit | Ausente | Submeter form | **GAP — implementar (US-SELL-007)** |

### 7. Persistência (localStorage)

| Chave | Blade legacy | Canon Cockpit | Decisão MWART |
|---|---|---|---|
| Filtros/abas | URL only | `oimpresso.os.filter` (DESIGN.md §12) | N/A — esta tela é criação ✅ |
| Draft (form) | **Ausente — perde se F5** | N/A canon (canon é list, não form) | **GAP — `oimpresso.sells.create.draft.{biz}.{user}` debounced 500ms (US-SELL-007)** |
| Estado UI colapsável | jQuery local state perdido | localStorage por componente | `oimpresso.sells.create.advanced.open` ✅ implementado |

### 8. Componentes shared

| Shared | Blade legacy | Canon Cockpit | Decisão MWART |
|---|---|---|---|
| PageHeader | bootstrap navbar custom inline | `os-page-h` div custom | `@/Components/shared/PageHeader` shared ✅ (paridade) |
| EmptyState | Tabela vazia sem ação | `os-empty-state` div custom | `@/Components/shared/EmptyState` shared ✅ |
| KpiCard | Ausente | `os-stat` × 4 (Abertas, Atrasadas, Valor aberto, Total mês) | **GAP — esta tela form não tem KPIs por padrão. Considerar: Total venda + Total pago + Itens (3 KPIs sticky)** ⚠ |
| DataTable | Yajra DataTables jQuery | Tabela inline custom rica | Tabela inline shadcn — **simplificação adequada** ✅ |
| StatusBadge | `<span class="label-success">` | `OsStageBadge` color → label | Não usa (status é dropdown editável, não badge readonly) ✅ |
| ProductSearchAutocomplete | Select2 jQuery + DataTables | N/A canon (canon é list) | Componente local `_components/ProductSearchAutocomplete` ✅ |
| PaymentRow | Form rows hardcoded | N/A canon | Componente local `_components/PaymentRow` ✅ |

## Gaps identificados (Wagner valida)

### 🟠 Gaps P0 (afetam confiança visual)

1. **Topnav horizontal módulo ausente em AppShellV2** — afeta TODAS as 78 telas MWART, não só Sells. Não vai ser feito nesta tela; criar PR separado de infra.
2. **Sticky "Salvar venda"** — esta tela é form longo (com `<details>` Mais opções aberto pode ter scroll de 2 telas). Larissa rola muito pra clicar Salvar. **Decisão sugerida:** mover Salvar pra header sticky no scroll OU adicionar barra inferior fixa.

### 🟡 Gaps P1 (melhorias de UX)

3. **KPI cards top** — canon `os-page.jsx` tem 4 KPIs (Abertas/Atrasadas/Valor/Total). Adaptado pra tela de criação: **3 KPIs durante digitação** (Total venda · Total pago · Itens). Atualiza em tempo real com useMemo. Dá ao Larissa "sense of progress" antes de adicionar primeiro produto.
4. **Atalhos `/`, `Esc`, `⌘+Enter`** — canon usa `/` pra busca, esta tela tem busca produto. Implementar em US-SELL-007 (já planejado).
5. **Auto-save draft localStorage** — Larissa atende telefone no meio. Sem auto-save = perde tudo no F5. US-SELL-007 já planejado.

### 🟢 Pontos OK (não mexer)

6. **Iconografia** — 100% lucide-react ✅
7. **Tokens shadcn** — sem cor crua, dark mode funciona ✅
8. **Empty states** — todos com CTA ✅
9. **Persistent Layout AppShellV2** — Persistent Layout pattern correto ✅
10. **Triagem 18→8 campos** — redução de scroll de 3 telas pra 1 ✅
11. **Defaults conservadores ROTA LIVRE** — Status=final, format_now_local, walkInCustomer ✅

## Decisão final (Wagner valida)

> Marcar `[x]` cada item conforme aprovar/rejeitar. Após todos aprovados, mudar `status: draft → approved`.

- [ ] Layout — aceitar paridade canon + topnav P0 separado
- [ ] Hierarquia — aceitar 2 ações primárias (Cancelar + Salvar)
- [ ] Densidade — manter `p-6 space-y-6` (paridade canon)
- [ ] Iconografia — manter 100% lucide
- [ ] Estados — manter (já completos)
- [ ] Atalhos — adicionar `/`, `Esc`, `⌘+Enter` em US-SELL-007
- [ ] Persistência — adicionar draft auto-save em US-SELL-007
- [ ] Componentes shared — adicionar **3 KPIs top** (Total venda · Total pago · Itens) ⚠ decisão pendente
- [ ] Sticky "Salvar venda" — adicionar header sticky on-scroll ⚠ decisão pendente

**Wagner pode adicionar:** observações finais, gaps adicionais, prioridades.

**Aprovado por:** —
**Data:** —

---

**Última atualização:** 2026-05-08
