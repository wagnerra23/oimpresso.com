---
slug: oficinaauto-index-visual-comparison
title: "OficinaAuto — Comparativo visual ProducaoOficina/Index (US-OFICINA-027 drawer rico)"
type: visual-comparison
module: OficinaAuto
status: draft
date: 2026-05-26
canon_reference_v1: prototipo-ui/prototipos/producao-oficina/F1.html
canon_reference_v2: prototipo-ui/prototipos/producao-oficina/visual-source.html
inertia_target: resources/js/Pages/OficinaAuto/ProducaoOficina/Index.tsx
canonical_doc: producao-oficina-cacamba-visual-comparison.md
revisions:
  - 2026-05-26 stub — bate gate MWART (ADR 0107) que procura `<basename>-visual-comparison.md`; conteúdo canon vive em [`producao-oficina-cacamba-visual-comparison.md`](producao-oficina-cacamba-visual-comparison.md) (status: revised, Wagner viu screenshots 2026-05-13)
---

# OficinaAuto · ProducaoOficina/Index — Visual comparison (stub)

> **Documento canônico:** [`producao-oficina-cacamba-visual-comparison.md`](producao-oficina-cacamba-visual-comparison.md) — 15 dimensões + screenshots + decisões aprovadas Wagner 2026-05-13.
>
> Este arquivo é stub pra satisfazer gate MWART que procura `<basename>-visual-comparison.md` no caminho. `tela_pascal=basename(page,.tsx)=Index` → kebab `index` (ver `.github/workflows/mwart-gate.yml`).
>
> Em US-OFICINA-027 (PR #1624), drawer renomeado `CacambaProducaoSheet` → `ServiceOrderRichSheet` pra refletir uso polimórfico (manutenção/locação). Visual NÃO mudou — só nome. Decisões visuais canônicas mantêm-se no documento principal.

## 6 dimensões (resumo)

### 1. Layout · Kanban 5 colunas

Estado-da-arte Trello/Linear/Notion. 5 colunas representam workflow Martinho. **Aprovado canon** — ver doc canônico.

### 2. Hierarquia · sobreposição drawer

Drawer 480px slide-in da direita preserva contexto Kanban (backdrop semi-transparente). Pattern Stripe Dashboard.

### 3. Densidade · cards Kanban compactos

Card mostra placa + status FSM + valor + ⚙️ ação. Densidade alta sem perda de legibilidade. Larissa 1280px sem scroll horizontal.

### 4. Iconografia · lucide-react canon

Apenas ícones lucide (ADR UI-0013). Zero novos ícones nesta US.

### 5. Estados · FSM cascade

5 colunas = 5 estados FSM (orcamento/aprovado/em-execucao/entregue/pago). Drag-drop entre colunas dispara FSM transition via Observer (ADR 0143).

### 6. Componentes · ServiceOrderRichSheet polimórfico

Drawer **NÃO** reusa `ServiceOrderSheet` genérico — embute `ServiceOrderFsmActionPanel` + seção PEÇAS & MÃO DE OBRA específica de manutenção. Decisão Wagner 2026-05-26: polimórfico (manutenção/locação) via `data.kind` prop. Slot reinventado vs PT-01 é trade-off conhecido (registrar `/mwart-override` se Wagner aprovar).

---

**Para revisar 15 dimensões completas + screenshots + dialogue Wagner-Claude**, ver [`producao-oficina-cacamba-visual-comparison.md`](producao-oficina-cacamba-visual-comparison.md).
