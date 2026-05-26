---
slug: oficinaauto-producao-oficina-cacamba-visual-comparison
title: "OficinaAuto — Comparativo visual Produção Oficina Caçamba"
type: visual-comparison
module: OficinaAuto
status: revised
date: 2026-05-13
canon_reference_v1: prototipo-ui/prototipos/producao-oficina/F1.html
canon_reference_v2: prototipo-ui/prototipos/producao-oficina/visual-source.html
blade_source: N/A (módulo novo, sem Blade legacy)
inertia_target: resources/js/Pages/OficinaAuto/ProducaoOficina/Index.tsx
revisions:
  - 2026-05-13 V1 (draft) — paridade básica com F1.html (5 cols Kanban + drawer simples reusando ServiceOrderSheet)
  - 2026-05-13 V2 (revised) — overhaul rico espelhando visual-source.html (1213 linhas) — Wagner viu screenshots e disse "informações e modelo são muito superiores, com imagens histórico…"
---

# OficinaAuto — Produção Oficina (Caçambas) — Visual comparison

> Referência canônica visual V1: [`prototipo-ui/prototipos/producao-oficina/F1.html`](../../../prototipo-ui/prototipos/producao-oficina/F1.html) (Cowork APROVADO simples)
> Referência canônica visual V2: [`prototipo-ui/prototipos/producao-oficina/visual-source.html`](../../../prototipo-ui/prototipos/producao-oficina/visual-source.html) (1213 linhas — RICA, fonte canon V2)
> Adaptação: 5 colunas Kanban estado caçambas (workflow Martinho), drawer próprio `CacambaProducaoSheet` (NÃO reusa ServiceOrderSheet — embute `ServiceOrderFsmActionPanel`).
> Demo: Wagner reunião Martinho **2026-05-13 10h** — quer mostrar layout BONITO Kanban estado-da-arte.

---

## V1 — 7 dimensões essenciais (paridade F1.html simples)

| # | Dimensão | F1.html canon | Implementação Inertia | Decisão |
|---|---|---|---|---|
| 1 | **Layout** | Topbar + filter bar sticky + Kanban 5 cols + drawer 480px | AppShellV2 + topbar interno + filter bar sticky `top-0 z-10` + grid `grid-cols-5 gap-4` + ServiceOrderSheet | Paridade — drawer reusado (PR #729 já tem 480px sheet) |
| 2 | **Hierarquia visual** | h1 invisível (placeholder topbar), header coluna sm-bold + dot color, plate bold + capacidade badge | Topbar interno `text-base font-semibold` + header coluna mesma estrutura, plate `text-sm font-bold` mono | Paridade |
| 3 | **Densidade** | Cards `p-3 space-y-2`, header coluna `px-3 py-2.5`, filter bar `px-6 py-3` | Idêntico — mesmas classes Tailwind | Paridade 1:1 |
| 4 | **Iconografia** | Sem ícones nos cards (foco no plate). Filter bar com pills puro texto. | Lucide `Plus`/`Search` só nos botões CTA + filter bar; cards só plate + texto | Paridade — minimalismo respeitado |
| 5 | **Estados visuais (cards + drawer)** | Default `bg-ink-50 border ink-200`; aprovação `bg-accent-50 border-2 accent-200` (destaque amber); pronto `opacity-90 + ✓ verde` | Default `bg-slate-50 border slate-200`; coluna "Aguardando" usa cards `bg-amber-50 border-2 amber-200` + badge "Recolher" amber-500; coluna "Pronta" cards `opacity-90 + ✓ pronta` emerald | Paridade — `accent` mapeado pra `amber` (Tailwind 4 default tem amber, evita config custom) |
| 6 | **Tipografia plate mono** | `font-family: ui-monospace, "Cascadia Code", Menlo, monospace; letter-spacing: .02em` | Inline `style={{ fontFamily: 'ui-monospace, "Cascadia Code", Menlo, monospace' }}` no `<span>` da plate | Inline pra evitar config custom Tailwind 4 (JIT) |
| 7 | **Cores semânticas warm (accent-amber pra aprovação)** | Coluna 3 "Aguardando peças" = bg amber leve + badge aprov accent-500 + bordas accent-200 | Coluna "Aguardando recolhimento" = section `bg-amber-50/30 border-amber-200` + cards `bg-amber-50 border-2 amber-200` + badge "Recolher" `bg-amber-500 text-white` + count chip `bg-amber-100 text-amber-800 font-semibold` | Paridade — semântica "ação imediata" preservada (Wagner pode bater olho e ver o que exige ação) |

---

## V2 — 8 dimensões adicionais (overhaul rico — espelha visual-source.html)

> Wagner viu V1 e disse: "as informações e o modelo são muito superiores, com imagens histórico…". V2 incorpora elementos canônicos do `visual-source.html` (1213 linhas, oficina mecânica de carros) adaptados pra caçambas.

| # | Dimensão | visual-source.html canon | Implementação V2 | Decisão |
|---|---|---|---|---|
| 8 | **Sub-header descritivo** | "Produção · Oficina" + subtitle "Recepção, diagnóstico, peças, execução e entrega de veículos" | Topbar `<h1>Produção · Oficina</h1>` + `<p>Locação, recolhimento, manutenção e entrega de caçambas</p>` (vocabulário caçamba) | Adaptado — verbos do workflow Martinho substituem mecânica |
| 9 | **6 KPI cards (não 3 inline)** | grid 6 colunas: Recepção / Em diagnóstico / Aguardando peças / Em execução / Urgentes (`prod-kpi-urgent`) / Valor em curso (`R$ X · faturamento previsto`) | grid `grid-cols-6` (`sm:grid-cols-3 lg:grid-cols-6`): Total / Locadas / Aguardando / Em manutenção / Atrasadas (bg-rose-50) / Valor em curso (bg-emerald-50). Tones: default/amber/rose/emerald | Espelho 1:1 — KPI "Atrasadas" usa bg-rose destaque (vs Urgentes); "Valor em curso" usa bg-emerald (positivo, faturamento) |
| 10 | **Cards 5-6 linhas ricas** | OS# + chegou hh:mm \| placa + veículo + km + cliente \| sintoma \| progresso/parts \| mecânico avatar \| ETA + prazo | OS# + "desde dd/mm hh" \| placa + capacidade \| cliente (font-medium) \| endereço + MapPin icon \| observação italic \| atendente avatar + dias · diárias \| valor R$ emerald/rose | Mesmos slots informacionais, vocabulário caçamba: km→capacidade, sintoma→observação, mecânico→atendente, ETA→dias locação |
| 11 | **Card URGENTE — strip rose top + ponto pulse** | `.ofc-card-urgent-strip` (2px barra vermelha topo) + `.urgent` class com border + animação pulse | Cards coluna "Aguardando recolhimento" recebem: `border-2 border-rose-300` + strip `<span absolute top-0 h-[2px] bg-rose-500>` + ponto canto `w-2 h-2 rounded-full bg-rose-500 animate-pulse` | Paridade — ação imediata sinalizada visualmente em 3 níveis (border + strip + pulse) |
| 12 | **Atendente avatar (iniciais)** | `.ofc-mech-av` 18px círculo cinza com iniciais ("CR" Carlos Rocha) | Span 18×18px `bg-slate-200 text-slate-700` com iniciais derivadas de `transaction.createdBy` (first_name + last_name → "WR"). Backend projetor `makeIniciais()`. Fallback: "sem atendente" italic | Paridade — derivação automática pelo backend (não hardcoded mock) |
| 13 | **Drawer rico CacambaProducaoSheet (5 sections)** | `.ofc-veh-card` (placa scale 1.15 + dl KV grid) + Sintoma + Aprovação banner + Fotos + Peças&MO + Linha do tempo | NEW component `CacambaProducaoSheet.tsx`: header com `MercosulPlate size=md` + KV grid (Cliente/Capacidade/Endereço/Diárias/Valor) + datas Início/Prazo (rose se overdue) + OBSERVAÇÃO + FOTOS placeholder 3-grid + PIPELINE FSM (embute `ServiceOrderFsmActionPanel`) + LINHA DO TEMPO skeleton derived | Drawer próprio (não reusa ServiceOrderSheet — sections diferentes). Embute FsmActionPanel canon (sem duplicar). NÃO toca ServiceOrderSheet (PR #729 — usado por outras telas) |
| 14 | **FOTOS & LAUDO placeholders** | `.ofc-photos` grid-cols-3 + `.ofc-photo` aspect-ratio 4/3 com pattern listrado + label "FOTO·1 frente" | grid-cols-3 + `aspect-square` divs com pattern repeating-linear-gradient (oklch tokens preservados) + label "FOTO ·entrega/local/assinatura" + botão "+ Adicionar foto" `disabled` (V2 upload real via Modules/Arquivos) | Placeholder visual 1:1 — upload disabled com tooltip "V2" |
| 15 | **LINHA DO TEMPO (timeline vertical)** | `.ofc-timeline` linha vertical 1px + items com bullet (done verde / now rose com ring / future cinza) | `<div relative pl-4>` linha `absolute w-px bg-slate-200` + items `<span -left-[10px] w-[7px] h-[7px] rounded-full>` cores: emerald (done) / rose ring (now) / white border-slate (future). Items derivados de `entered_at` + `expected_return` + `completed_at` + status. Nota "Histórico FSM completo em V2" | Skeleton derived (sem fetch endpoint — V2 puxa `sale_stage_history`). 4-5 items mínimos cobrem narrativa |

---

## Mapeamento workflow caçamba (vs Cowork carro)

| Coluna canon (carro) | Coluna OficinaAuto (caçamba) | Mapeamento `current_status` |
|---|---|---|
| Recepção | **Disponível** | `disponivel` |
| Diagnóstico | **Locada** | `locada` (sem overdue) |
| Aguardando peças (destaque amber) | **Aguardando recolhimento** (destaque rose+amber) | `locada` + `currentRental.is_overdue=true` |
| Em execução | **Em manutenção** | `manutencao` |
| Pronto | **Pronta entrega** | `indisponivel` |

| Filter canon (carro) | Filter OficinaAuto (caçamba) |
|---|---|
| Box B1-B4 + Elevador E1-E2 (recurso) | Capacidade 3m³/5m³/7m³ (volume) |
| Mecânico (CR · Carlos R) | Atendente (W · Wagner) — derivado de `transaction.createdBy` |

| KPI canon (carro) | KPI OficinaAuto (caçamba) |
|---|---|
| Recepção | Total caçambas no estoque |
| Em diagnóstico | Locadas (em campo no momento) |
| Aguardando peças | Aguardando (recolhimento) — bg-amber |
| Em execução | Em manutenção (oficina) |
| Urgentes (bg destaque) | Atrasadas (bg-rose — prazo crítico) |
| Valor em curso | Valor em curso (faturamento previsto) — bg-emerald |

---

## Decisão MWART V2

- **Espelhamento canon visual-source.html** — 6 KPIs ricos + cards 5-6 linhas + drawer 5 sections + FOTOS placeholder + atendente avatar + URGENTE pulse rose
- **Adaptado vocabulário caçamba** — verbos workflow Martinho (locação/recolhimento/manutenção/entrega) **eram aplicados na leitura pré-ADR 0194** (sub-vertical 3 hipotético). **Pós-ADR 0194** sub-vertical 4 real (mecânica pesada caminhão basculante) usa verbos canon mecânica (recepção/diagnóstico/peças/execução/pronto). UI prod biz=164 hoje exibe os verbos antigos preservados — review_trigger M6+ caso refactor vir conforme US-OFICINA-027 catálogo peça hidráulica.
- **Drawer próprio `CacambaProducaoSheet`** — NÃO reusa `ServiceOrderSheet` (PR #729 — sections diferentes). Embute `ServiceOrderFsmActionPanel` canon (sem duplicar lógica FSM)
- **Tokens custom evitados** — Tailwind 4 JIT com classes nativas (`slate-*`, `amber-*`, `rose-*`, `emerald-*`); pattern foto inline `repeating-linear-gradient` com oklch (1 lugar só)
- **Atendente derivado backend** — não hardcoded; backend projetor `makeIniciais()` cria iniciais a partir de `transaction.createdBy.first_name + last_name`. Fallback: "sem atendente" italic
- **useMemo/useCallback** obrigatório (lição PR #717) — `handleCardClick`/`handleSheetOpenChange`/`handleOrderChanged` + `columnsData` + `kpiCards` + `kpiSummary` memo + `CacambaCard`/`CacambaKanbanColumn` em `memo()` + `MercosulPlate` em `memo()`

---

## Riscos / TODOs

- ⚠️ Demo usa biz=1 (Wagner) — **NUNCA** rodar smoke biz=4 (cliente) — ADR 0101
- 📋 **TODO V2 P1**: upload foto real (`Modules/Arquivos` integration) — botão "+ Adicionar foto" hoje `disabled`
- 📋 **TODO V2 P1**: botão "Imprimir fila" funcional (PDF download) — hoje `disabled`
- 📋 **TODO V2 P1**: Linha do tempo via fetch real `/oficina-auto/service-orders/{id}/fsm/history` (requer endpoint Wave 7+1)
- 📋 **TODO V2 P1**: Toggle "Lista" na header — hoje navega pra `/oficina-auto/veiculos`. V2: tabela inline opt-in
- 📋 **TODO V2 P2**: drag-and-drop entre colunas (HTML5 native + transition FSM no drop)
- 📋 **TODO V2 P2**: filtros adicionais (cliente dropdown, valor range)
- 📋 **TODO V2 P2**: realtime via Centrifugo (caçamba retornada em outro device → kanban atualiza sem reload)
- 📋 **TODO V2 P2**: KPIs histogramas (média dias locação, pico atrasos por cliente)
