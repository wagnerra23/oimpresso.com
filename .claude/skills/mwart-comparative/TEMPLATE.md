# TEMPLATE — `<tela>-visual-comparison.md`

> Copiar abaixo como ponto de partida. Substituir todo `<placeholder>` por valor real. Apagar `> Dica:` antes de salvar.

```markdown
---
slug: <mod-lower>-<tela-kebab>-visual-comparison
title: "<Mod> — Comparativo visual da tela <Nome legível>"
type: visual-comparison
module: <Mod>
status: draft
date: <YYYY-MM-DD>
canon_reference: os-page.jsx
blade_source: resources/views/<modulo>/<tela>.blade.php
inertia_target: resources/js/Pages/<Mod>/<Tela>.tsx
approved_by: —
approved_at: —
---

# Comparativo visual — <Nome legível>

> **Tipo de tela:** <form | list+detail | inbox | chat | dashboard | master-detail>
> **Persona alvo:** <Larissa biz=4, monitor 1280px, ~5 vendas/dia>
> **Refs:**
> - Blade legacy: [`<blade_source>`](../../../<blade_source>)
> - Canon Cockpit: [`<canon_reference>`](../../_DesignSystem/ui_kits/cowork-2026-04-27/<canon_reference>)
> - RUNBOOK: [`RUNBOOK-<tela>.md`](RUNBOOK-<tela>.md)
> - SPEC: [`SPEC.md` US-<MOD>-<NNN>](SPEC.md#US-<MOD>-<NNN>)

## Resumo executivo (Wagner lê em 30s)

<1 parágrafo, 3-4 linhas: o que esta tela faz hoje no Blade vs o que vamos fazer no MWART. Destacar o GAP principal que justifica a migração além de paridade técnica (ex: "Blade tem 18 campos visíveis, scroll 3 telas; MWART vai ter 8 visíveis + 10 colapsáveis, scroll 1 tela").>

## Tabela comparativa — 8 dimensões

### 1. Layout

| Aspecto | Blade legacy | Canon Cockpit | Decisão MWART |
|---|---|---|---|
| Header | <ex: barra azul UltimatePOS + breadcrumb> | <ex: AppShellV2 header com avatar+empresa+toggle dark> | <ex: AppShellV2 header (paridade canon)> |
| Sidebar | <ex: sidebar UltimatePOS 250px com submenus> | <ex: sidebar 260px light com tabs Chat/Menu (ADR 0039)> | <ex: AppShellV2 default (paridade)> |
| Topnav módulo | <ex: tabs horizontais "Vendas / Adicionar venda / Pedidos / Cotações..."> | <ex: AINDA não implementado em AppShellV2 — gap conhecido> | <ex: P0 BLOQUEADOR — implementar em PR separado antes desta tela> |
| Footer | <ex: ausente> | <ex: ausente> | <ex: ausente (paridade)> |
| Grid breakpoints | <ex: tabela responsive Bootstrap> | <ex: container max-w-7xl + grid responsive Tailwind> | <ex: max-w-7xl (paridade canon)> |

### 2. Hierarquia visual

| Aspecto | Blade legacy | Canon Cockpit | Decisão MWART |
|---|---|---|---|
| Ação primária | <ex: botão "Salvar e imprimir" verde no fim da página> | <ex: 1 botão `<Button>` shadcn primary com label clara> | <ex: "Salvar venda" sticky no topo direito após scroll> |
| Ações secundárias | <ex: 5 botões "Salvar como rascunho", "Salvar e adicionar", etc> | <ex: 1-2 botões `variant="outline"`> | <ex: "Cancelar" outline + "Salvar" primary (2 ações)> |
| Hierarquia tipográfica | <ex: h2 título + h4 sub-seções + spans labels> | <ex: PageHeader h1 + CardTitle h3 + Label> | <ex: paridade canon> |

### 3. Densidade

| Aspecto | Blade legacy | Canon Cockpit | Decisão MWART |
|---|---|---|---|
| Espaçamento entre seções | <ex: `<div class="row mb-3">` ~16px> | <ex: `space-y-6` (24px)> | <ex: `space-y-6` (paridade canon)> |
| Card padding | <ex: `panel-body` ~15px> | <ex: `p-6` (24px)> | <ex: `p-6` (paridade)> |
| Line-height | <ex: 1.4 default Bootstrap> | <ex: 1.5 Tailwind default> | <ex: 1.5 (paridade)> |

### 4. Iconografia

| Aspecto | Blade legacy | Canon Cockpit | Decisão MWART |
|---|---|---|---|
| Sistema | <ex: Font Awesome 4 misto> | <ex: lucide-react 100%> | <ex: lucide-react (R-DS-003)> |
| Ícones específicos | <ex: fa-cart, fa-money, fa-truck> | <ex: ShoppingCart, Wallet, Truck> | <ex: paridade lucide> |
| Cor | <ex: hardcoded `text-success`> | <ex: tokens semânticos (text-foreground, text-muted-foreground)> | <ex: tokens shadcn (R-DS-002)> |

### 5. Estados visuais

| Estado | Blade legacy | Canon Cockpit | Decisão MWART |
|---|---|---|---|
| Default | <ex: bordas planas, sem hover> | <ex: `bg-card border-border`> | <ex: paridade> |
| Hover | <ex: ausente em rows> | <ex: `hover:bg-muted/50` em rows clicáveis> | <ex: hover na tabela produtos> |
| Focus | <ex: outline azul Bootstrap default> | <ex: `focus-visible:ring-2 ring-accent`> | <ex: paridade canon (R-DS-006)> |
| Loading | <ex: spinner gif legacy> | <ex: `<Loader2 className="animate-spin">`> | <ex: Loader2 lucide (paridade)> |
| Empty | <ex: tabela vazia sem CTA> | <ex: `<EmptyState icon=" " title=" " action=<Button>"> | <ex: EmptyState shared com CTA (Q5 audit)> |
| Error | <ex: alert vermelho server-side> | <ex: `<FormError>` por campo + Sentry> | <ex: errors do useForm por campo> |

### 6. Atalhos teclado

| Tecla | Aplicável? | Blade legacy | Canon Cockpit | Decisão MWART |
|---|---|---|---|---|
| ⌘K / Ctrl+K | Sempre (shell) | <ex: ausente> | <ex: AppShellV2 — busca global> | <ex: shell (não desta tela)> |
| J / K | Master/detail | <ex: N/A (form)> | <ex: navegar lista> | <ex: N/A (esta tela é form)> |
| E / A | Inbox | N/A | <ex: concluir/adiar> | N/A |
| / | Tela inteira | <ex: N/A> | <ex: focar busca local> | <ex: focar busca produto (US-SELL-007)> |
| Esc | Sempre | <ex: ausente> | <ex: fecha modal/blur input> | <ex: implementar (US-SELL-007)> |
| ⌘+Enter | Form | <ex: ausente> | <ex: submeter form> | <ex: implementar (US-SELL-007)> |

### 7. Persistência (localStorage)

| Chave | Blade legacy | Canon Cockpit | Decisão MWART |
|---|---|---|---|
| Filtros/abas | <ex: ausente (URL only)> | <ex: `oimpresso.<mod>.filter` (DESIGN.md §12)> | <ex: `oimpresso.<mod>.<tela>.<estado>`> |
| Draft (form) | <ex: ausente — perde se F5> | <ex: `oimpresso.<mod>.<tela>.draft.<biz>.<user>` debounced 500ms> | <ex: implementar (US-SELL-007)> |
| Estado UI | <ex: jQuery local state perdido> | <ex: `oimpresso.<mod>.<tela>.<comp>.open`> | <ex: implementar pra `<details>` Mais opções> |

### 8. Componentes shared

| Shared | Blade legacy | Canon Cockpit | Decisão MWART |
|---|---|---|---|
| PageHeader | <ex: bootstrap navbar custom> | <ex: `@/Components/shared/PageHeader`> | <ex: PageHeader (paridade canon)> |
| EmptyState | <ex: tabela vazia sem CTA> | <ex: `@/Components/shared/EmptyState`> | <ex: EmptyState (R-DS-001 reuso)> |
| KpiCard | <ex: blocos custom> | <ex: `@/Components/shared/KpiCard` (vide os-page.jsx)> | <ex: paridade se tela tem KPIs> |
| DataTable | <ex: Yajra DataTables jQuery> | <ex: `@/Components/shared/DataTable` ou tabela inline shadcn> | <ex: tabela inline (esta tela tem só 1 lista)> |
| StatusBadge | <ex: `<span class="label-success">`> | <ex: `@/Components/shared/StatusBadge`> | <ex: StatusBadge se houver status> |

## Gaps identificados (Wagner valida)

> Listar concretamente o que **NÃO** vai bater com canon ou Blade — e justificativa.

- ⚠ <ex: Topnav horizontal módulo: gap canon — implementar em AppShellV2 antes de F3 desta tela>
- ⚠ <ex: KPI cards top: canon `os-page.jsx` tem; tela form não cabe — exceção justificada>
- ⚠ <ex: Auto-save draft: gap funcional vs Blade (que perde tudo no F5) — vira US-SELL-007 follow-up>

## Decisão final

> Wagner aprova / ajusta / rejeita. Após aprovação, mudar `status: draft → approved` no frontmatter + assinar `approved_by: wagner` + `approved_at: <data>`.

- [ ] Layout aprovado
- [ ] Hierarquia aprovada
- [ ] Densidade aprovada
- [ ] Iconografia aprovada
- [ ] Estados aprovados
- [ ] Atalhos aprovados
- [ ] Persistência aprovada
- [ ] Componentes shared aprovados
- [ ] Gaps revisados + decisão registrada

**Aprovado por:** <wagner | maira | felipe>
**Data:** <YYYY-MM-DD>

---

**Última atualização:** <YYYY-MM-DD>
```
