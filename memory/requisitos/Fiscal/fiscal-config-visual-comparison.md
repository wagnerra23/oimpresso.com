---
tela: Fiscal/Config
url: /fiscal/config
status: approved
approver: wagner
approved_at: 2026-05-20
prototype_source: "prototipo-ui/.../fiscal-data.jsx CONFIG"
implementation: resources/js/Pages/Fiscal/Config.tsx
adr: 0107
---

# Visual Comparison — Fiscal/Config (PR #3 Wave)

## Blueprint Cowork

`prototipo-ui/.../fiscal-data.jsx::CONFIG` (certificado + ambiente + séries + regime).

## Approval

Wagner aprovou Wave 3 final 2026-05-20.

## 8 dimensões

### 1. Layout grid
| Aspecto | Cowork | Inertia | OK? |
|---|---|---|---|
| 2 cards (cert + config) | seções stacked | ✅ `.fx-drawer-sec` reaproveitada como card | ✅ |

### 2. Tipografia
| Aspecto | Cowork | Inertia | OK? |
|---|---|---|---|
| h4 section title uppercase | letter-spacing 0.05em | ✅ `.fx-drawer-sec h4` | ✅ |
| `.fx-kv` dt mute / dd text | 100px grid | ✅ idem | ✅ |

### 3. Densidade
| Aspecto | Cowork | Inertia | OK? |
|---|---|---|---|
| Card padding 18px | spacious | ✅ inline style | ✅ |

### 4. Iconografia
| Aspecto | Cowork | Inertia | OK? |
|---|---|---|---|
| Shield icon section title | lucide 13px | ✅ idem | ✅ |
| Edit3 ação editar | lucide 12px | ✅ idem | ✅ |

### 5. Cores cert
| Aspecto | Cowork | Inertia | OK? |
|---|---|---|---|
| Validade bg pill por urgência | crit/warn/ok | ✅ `.fx-sefaz.{ok,warn,bad}` | ✅ |

### 6. Estados condicionais
| Aspecto | Cowork | Inertia | OK? |
|---|---|---|---|
| Sem cert → empty state | inline empty | ✅ `.fx-empty` no card | ✅ |
| Sem config → empty | inline empty | ✅ idem | ✅ |
| EXPIRADO se diasRestantes ≤0 | label diferente | ✅ ternário inline | ✅ |

### 7. Link edição
| Aspecto | Cowork | Inertia | OK? |
|---|---|---|---|
| Editar → /nfe-brasil/configuracao | botão primary header | ✅ `<a>` com class `.fx-btn.primary` | ✅ |
| Notice "edição vive em NfeBrasil" | rodapé | ✅ `.fx-empty` rodapé | ✅ |

### 8. Reuso
| Aspecto | Cowork | Inertia | OK? |
|---|---|---|---|
| FxShell | shared | ✅ | ✅ |
| `.fx-kv` dt/dd grid | reaproveitado de drawer | ✅ | ✅ |
| Read-only por design | nenhum form/input | ✅ confirmed (sem `<form>`) | ✅ |

## Histórico

- **2026-05-20** — Wave 3 final.
