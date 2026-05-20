---
tela: Fiscal/Eventos
url: /fiscal/eventos
status: approved
approver: wagner
approved_at: 2026-05-20
prototype_source: "prototipo-ui/.../fiscal-page.jsx §11 FiscalEventosPage"
implementation: resources/js/Pages/Fiscal/Eventos.tsx
adr: 0107
---

# Visual Comparison — Fiscal/Eventos (PR #2 Wave)

## Blueprint Cowork

`prototipo-ui/.../fiscal-page.jsx §11 FiscalEventosPage` + `fiscal-data.jsx::EVENTOS` (R#1 KB-9.75).

## Approval

Wagner aprovou Wave consolidada 2026-05-20.

## 8 dimensões

### 1. Layout grid

| Aspecto | Cowork | Inertia | OK? |
|---|---|---|---|
| Timeline vertical | linha vertical + bullets coloridos | ✅ `.fx-timeline` + `::before` | ✅ |
| Item card row | padding 12px border-bottom | ✅ `.fx-tl-item` | ✅ |
| Hero + filtros + body | FxShell padrão | ✅ reusado | ✅ |

### 2. Tipografia

| Aspecto | Cowork | Inertia | OK? |
|---|---|---|---|
| Badge tipo evento | 10.5px font-weight 600 pill | ✅ `.fx-tl-badge` | ✅ |
| cstat mono | 11px mono cinza | ✅ `<b>` mono inline | ✅ |
| Justificativa 12px | dim color | ✅ `.fx-tl-desc` | ✅ |

### 3. Densidade

| Aspecto | Cowork | Inertia | OK? |
|---|---|---|---|
| Bullet 12px com borda 2px white | offset-x -14px | ✅ `::before` | ✅ |
| Gap entre items | border-bottom 1px | ✅ idem | ✅ |
| Filtros gap 6px | idem padrão | ✅ idem | ✅ |

### 4. Iconografia

| Aspecto | Cowork | Inertia | OK? |
|---|---|---|---|
| Activity icon empty | lucide Activity 20px | ✅ idem | ✅ |

### 5. Cores por tipo

| Aspecto | Cowork | Inertia | OK? |
|---|---|---|---|
| CC-e verde | ok-soft bg + ok text | ✅ `.fx-tl-badge.cce` + `.fx-tl-item.cce::before` | ✅ |
| Cancelamento vermelho | bad-soft + bad | ✅ `.cancel` | ✅ |
| EPEC âmbar | warn-soft + warn | ✅ `.epec` | ✅ |
| Manifesto rosa fis | fis-soft + fis | ✅ `.manifest` | ✅ |

### 6. Animações

| Aspecto | Cowork | Inertia | OK? |
|---|---|---|---|
| Sem animação específica timeline | apenas hover bg row | ✅ `.fx-alert:hover` reaproveitado | n/a |

### 7. Estados condicionais

| Aspecto | Cowork | Inertia | OK? |
|---|---|---|---|
| Empty state | sem eventos → Activity + msg | ✅ `.fx-empty` | ✅ |
| Link emissão opcional | só renderiza se evento.emissao | ✅ condicional | ✅ |
| Justificativa opcional | só se truthy | ✅ condicional | ✅ |
| Filter "dias" select | 7/30/90 default 30 | ✅ `<select>` no header | ✅ |

### 8. Componentes reutilizados

| Aspecto | Cowork | Inertia | OK? |
|---|---|---|---|
| FxShell | shared | ✅ reusado | ✅ |
| Inertia Deferred | rows lazy load | ✅ idem | ✅ |
| router.visit pra link cross-página | navegação pra Fiscal/Nfe?focus=N | ✅ idem | ✅ |

## Histórico

- **2026-05-20** — Wave consolidada PR #2.
