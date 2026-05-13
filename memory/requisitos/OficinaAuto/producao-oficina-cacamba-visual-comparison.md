---
slug: oficinaauto-producao-oficina-cacamba-visual-comparison
title: "OficinaAuto — Comparativo visual Produção Oficina Caçamba"
type: visual-comparison
module: OficinaAuto
status: draft
date: 2026-05-13
canon_reference: prototipo-ui/prototipos/producao-oficina/F1.html
blade_source: N/A (módulo novo, sem Blade legacy)
inertia_target: resources/js/Pages/OficinaAuto/ProducaoOficina/Index.tsx
---

# OficinaAuto — Produção Oficina (Caçambas) — Visual comparison

> Referência canônica visual: [`prototipo-ui/prototipos/producao-oficina/F1.html`](../../../prototipo-ui/prototipos/producao-oficina/F1.html) (Cowork APROVADO)
> Adaptação: 5 colunas Kanban estado caçambas (workflow Martinho), reusando ServiceOrderSheet drawer (PR #729).
> Demo: Wagner reunião Martinho **2026-05-13 10h** — quer mostrar layout BONITO Kanban estado-da-arte.

## 7 dimensões essenciais (versão reduzida — tempo curto pré-demo)

| # | Dimensão | F1.html canon | Implementação Inertia | Decisão |
|---|---|---|---|---|
| 1 | **Layout** | Topbar + filter bar sticky + Kanban 5 cols + drawer 480px | AppShellV2 + topbar interno + filter bar sticky `top-0 z-10` + grid `grid-cols-5 gap-4` + ServiceOrderSheet | Paridade — drawer reusado (PR #729 já tem 480px sheet) |
| 2 | **Hierarquia visual** | h1 invisível (placeholder topbar), header coluna sm-bold + dot color, plate bold + capacidade badge | Topbar interno `text-base font-semibold` + header coluna mesma estrutura, plate `text-sm font-bold` mono | Paridade |
| 3 | **Densidade** | Cards `p-3 space-y-2`, header coluna `px-3 py-2.5`, filter bar `px-6 py-3` | Idêntico — mesmas classes Tailwind | Paridade 1:1 |
| 4 | **Iconografia** | Sem ícones nos cards (foco no plate). Filter bar com pills puro texto. | Lucide `Plus`/`Search` só nos botões CTA + filter bar; cards só plate + texto | Paridade — minimalismo respeitado |
| 5 | **Estados visuais (cards + drawer)** | Default `bg-ink-50 border ink-200`; aprovação `bg-accent-50 border-2 accent-200` (destaque amber); pronto `opacity-90 + ✓ verde` | Default `bg-slate-50 border slate-200`; coluna "Aguardando" usa cards `bg-amber-50 border-2 amber-200` + badge "Recolher" amber-500; coluna "Pronta" cards `opacity-90 + ✓ pronta` emerald | Paridade — `accent` mapeado pra `amber` (Tailwind 4 default tem amber, evita config custom) |
| 6 | **Tipografia plate mono** | `font-family: ui-monospace, "Cascadia Code", Menlo, monospace; letter-spacing: .02em` | Inline `style={{ fontFamily: 'ui-monospace, "Cascadia Code", Menlo, monospace' }}` no `<span>` da plate | Inline pra evitar config custom Tailwind 4 (JIT) |
| 7 | **Cores semânticas warm (accent-amber pra aprovação)** | Coluna 3 "Aguardando peças" = bg amber leve + badge aprov accent-500 + bordas accent-200 | Coluna "Aguardando recolhimento" = section `bg-amber-50/30 border-amber-200` + cards `bg-amber-50 border-2 amber-200` + badge "Recolher" `bg-amber-500 text-white` + count chip `bg-amber-100 text-amber-800 font-semibold` | Paridade — semântica "ação imediata" preservada (Wagner pode bater olho e ver oque exige ação) |

## Mapeamento workflow caçamba (vs Cowork carro)

| Coluna F1 (carro) | Coluna OficinaAuto (caçamba) | Mapeamento `current_status` |
|---|---|---|
| Recepção | **Disponível** | `disponivel` |
| Diagnóstico | **Locada** | `locada` (sem overdue) |
| Aguardando peças (destaque amber) | **Aguardando recolhimento** (destaque amber) | `locada` + `currentRental.is_overdue=true` |
| Em execução | **Em manutenção** | `manutencao` |
| Pronto | **Pronta entrega** | `indisponivel` |

## Decisão MWART

- **Paridade 100% F1.html** — layout/densidade/cores semânticas idênticas
- **Adaptado pra caçambas** — workflow real Martinho (5 estágios estado vehicle, não OS)
- **Drawer reusado** — `ServiceOrderSheet` (PR #729) já tem ServiceOrderFsmActionPanel embedded, banner overdue rose, sintoma/timeline/peças. Não recriar.
- **Tokens custom** — usei classes Tailwind nativas (`slate-*` ↔ `ink-*`, `amber-*` ↔ `accent-*`) em vez de config custom (Tailwind 4 JIT performance)
- **useMemo/useCallback** obrigatório (lição PR #717) — `handleCardClick`/`handleSheetOpenChange`/`handleOrderChanged` + `columnsData` memo + `CacambaCard`/`CacambaKanbanColumn` em `memo()`

## Riscos / TODOs

- ⚠️ Demo usa biz=1 (Wagner) — **NUNCA** rodar smoke biz=4 (cliente) — ADR 0101
- 📋 **TODO P1**: drag-and-drop entre colunas (V1 — após validação Martinho)
- 📋 **TODO P1**: filtros adicionais (cliente, valor a receber range)
- 📋 **TODO P2**: realtime via Centrifugo (caçamba retornada em outro device → kanban atualiza)
- 📋 **TODO P2**: histogramas KPI (média dias de locação, pico atrasos por cliente)
