---
tela: Fiscal/Cockpit
url: /fiscal
status: approved
approver: wagner
approved_at: 2026-05-20
prototype_source: "prototipo-ui/.../fiscal-page.jsx §8 FiscalCockpit"
implementation: resources/js/Pages/Fiscal/Cockpit.tsx
adr: 0107
---

# Visual Comparison — Fiscal/Cockpit (PR #2 Wave)

## Blueprint Cowork

`prototipo-ui/.../fiscal-page.jsx §8 FiscalCockpit` + `fiscal-data.jsx::FISCAL_KPIS/SPARKLINES/FISCAL_ALERTS` (R#1 KB-9.75).

## Approval

Wagner aprovou Wave consolidada (Cockpit + NFS-e + Eventos) — 2026-05-20.

## 8 dimensões

### 1. Layout grid

| Aspecto | Cowork | Inertia | OK? |
|---|---|---|---|
| KPIs grid | grid-template-columns: 1.4fr repeat(5, 1fr) | ✅ idem | ✅ |
| Alertas card branco | bg white + border + padding 14px | ✅ `.fx-alerts` | ✅ |
| Quick links 3 cols | grid-template-columns: repeat(3, 1fr) | ✅ idem | ✅ |

### 2. Tipografia

| Aspecto | Cowork | Inertia | OK? |
|---|---|---|---|
| KPI big number | 22px font-weight 700 | ✅ `.fx-kpi b` | ✅ |
| KPI small label | 10.5px uppercase + tracking | ✅ idem | ✅ |
| Alert título | 12.5px font-weight 600 | ✅ idem | ✅ |

### 3. Densidade

| Aspecto | Cowork | Inertia | OK? |
|---|---|---|---|
| Gap entre KPIs | 8px | ✅ idem | ✅ |
| Padding KPI card | 12px 14px | ✅ idem | ✅ |
| Alertas inset border-left 3px | inset esquerdo colorido por level | ✅ `.fx-alert.{crit,warn,info}` | ✅ |

### 4. Iconografia

| Aspecto | Cowork | Inertia | OK? |
|---|---|---|---|
| Icon hero KPI | sparkline SVG inline branco | ✅ MiniSparkline component | ✅ |
| Icon alert | ShieldAlert/Shield/Receipt/RefreshCw lucide | ✅ ICON map dinâmico | ✅ |
| Icon quick card | Receipt/FileText/Archive/Shield etc. lucide | ✅ idem | ✅ |

### 5. Cores/Estados

| Aspecto | Cowork | Inertia | OK? |
|---|---|---|---|
| KPI hero (emitidas) bg fis | rosa fiscal saturado | ✅ `.fx-kpi.hero` | ✅ |
| KPI rejeitadas pulse | animação box-shadow infinite | ✅ `@keyframes fx-pulse` | ✅ |
| Alert color tone | bad/warn/info via border-left | ✅ idem | ✅ |

### 6. Animações

| Aspecto | Cowork | Inertia | OK? |
|---|---|---|---|
| Pulse rejeitadas | 2.5s infinite (oklch bad 50%→0%) | ✅ idem | ✅ |
| Hover quick card | border-color → fis transition .12s | ✅ idem | ✅ |
| Hover alert | bg fx-bg-2 transition .12s | ✅ idem | ✅ |

### 7. Estados condicionais

| Aspecto | Cowork | Inertia | OK? |
|---|---|---|---|
| Alertas hide se vazio | `alerts.length > 0` render condicional | ✅ idem | ✅ |
| Pulse só se rejeitadas > 0 | classe condicional | ✅ idem | ✅ |
| Cert sem dados → "—" | fallback null | ✅ idem | ✅ |
| Quick cards disabled (4/6/7) | opacity 0.55 | ✅ inline style | ✅ |

### 8. Componentes reutilizados

| Aspecto | Cowork | Inertia | OK? |
|---|---|---|---|
| FxShell wrapper | sub-nav + cheats + atalhos 1-7 | ✅ `_components/FxShell.tsx` | ✅ |
| MiniSparkline SVG | path + circle endpoint | ✅ inline component (140 chars) | ✅ |
| brl helper | format moeda | ✅ `_lib/fiscal-helpers.ts` | ✅ |

## Histórico

- **2026-05-20** — Wave consolidada PR #2 (Cockpit + NFS-e + Eventos). Implementação fiel ao protótipo Cowork.
