---
tela: Fiscal/Nfse
url: /fiscal/nfse
status: approved
approver: wagner
approved_at: 2026-05-20
prototype_source: "prototipo-ui/.../fiscal-page.jsx §10 FiscalNFSePage"
implementation: resources/js/Pages/Fiscal/Nfse.tsx
adr: 0107
---

# Visual Comparison — Fiscal/Nfse (PR #2 Wave)

## Blueprint Cowork

`prototipo-ui/.../fiscal-page.jsx §10 FiscalNFSePage` + `fiscal-data.jsx::NOTAS_NFSE` (R#1 KB-9.75).

## Approval

Wagner aprovou Wave consolidada 2026-05-20.

## 8 dimensões

### 1. Layout grid

| Aspecto | Cowork | Inertia | OK? |
|---|---|---|---|
| Hero + sub-nav + body + cheats | FxShell padrão | ✅ reusado | ✅ |
| Tabela full-width card | border-radius 10px white | ✅ `.fx-table` | ✅ |
| Filtros chip-row | flex wrap gap 6px | ✅ `.fx-filters` | ✅ |

### 2. Tipografia

| Aspecto | Cowork | Inertia | OK? |
|---|---|---|---|
| Número NFS-e mono | 13.5px bold + small ver. abaixo | ✅ `.fx-mono` | ✅ |
| Tomador 12.5px + doc small | tomador font padrão | ✅ idem | ✅ |
| Status pill 11px | pill rounded | ✅ `.fx-sefaz` reusada | ✅ |

### 3. Densidade

| Aspecto | Cowork | Inertia | OK? |
|---|---|---|---|
| Row 48px | padding 9px | ✅ idem | ✅ |
| Filtros gap 6px | idem | ✅ idem | ✅ |
| Month picker compact | input month inline no hero | ✅ inline `<input type="month">` | ✅ |

### 4. Iconografia

| Aspecto | Cowork | Inertia | OK? |
|---|---|---|---|
| Search icon | FileSearch lucide 13px | ✅ idem | ✅ |
| Empty state icon | FileText 20px | ✅ idem | ✅ |

### 5. Cores/Estados

| Aspecto | Cowork | Inertia | OK? |
|---|---|---|---|
| Status authorized | tone ok (verde) | ✅ STATUS_LABEL.authorized | ✅ |
| Status rejected/cancelled | tone bad (vermelho) | ✅ idem | ✅ |
| Status pending/sent | tone warn (âmbar) | ✅ idem | ✅ |
| ISS subtext cinza | mute color sob valor | ✅ small inline style | ✅ |

### 6. Animações

| Aspecto | Cowork | Inertia | OK? |
|---|---|---|---|
| Row hover bg | transição .12s | ✅ `.fx-table tr:hover` | ✅ |

### 7. Estados condicionais

| Aspecto | Cowork | Inertia | OK? |
|---|---|---|---|
| Empty state | sem dados → card border-dashed | ✅ `.fx-empty` | ✅ |
| error_msg em title (hover) | apenas hover, não em texto inline | ✅ `title={errorMsg}` | ✅ |
| codigoVerificacao opcional | só exibe se presente | ✅ condicional | ✅ |

### 8. Componentes reutilizados

| Aspecto | Cowork | Inertia | OK? |
|---|---|---|---|
| FxShell | wrapper compartilhado | ✅ reusado | ✅ |
| brl/formatDoc helpers | _lib | ✅ idem | ✅ |
| `.fx-sefaz` SEFAZ pill | reaproveitado pra status NFS-e | ✅ idem | ✅ |

## Histórico

- **2026-05-20** — Wave consolidada PR #2.
