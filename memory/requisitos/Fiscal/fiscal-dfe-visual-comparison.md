---
tela: Fiscal/Dfe
url: /fiscal/dfe
status: approved
approver: wagner
approved_at: 2026-05-20
prototype_source: "prototipo-ui/.../fiscal-data.jsx DFE_PENDENTE"
implementation: resources/js/Pages/Fiscal/Dfe.tsx
adr: 0107
---

# Visual Comparison — Fiscal/Dfe (PR #3 Wave)

## Blueprint Cowork

`prototipo-ui/.../fiscal-page.jsx PÁGINA 4` + `fiscal-data.jsx::DFE_PENDENTE/DFE_HISTORICO`.

## Approval

Wagner aprovou Wave 3 final 2026-05-20.

## 8 dimensões

### 1. Layout grid
| Aspecto | Cowork | Inertia | OK? |
|---|---|---|---|
| Tabela emissor + chave + status | colunas estendidas pra prazo+valor | ✅ idem | ✅ |

### 2. Tipografia
| Aspecto | Cowork | Inertia | OK? |
|---|---|---|---|
| Emitente bold + CNPJ small | row primary | ✅ idem | ✅ |
| Chave truncada mono | últimos 6 dígitos | ✅ truncKey helper | ✅ |

### 3. Densidade
| Aspecto | Cowork | Inertia | OK? |
|---|---|---|---|
| Row 48px padrão | idem outras tabelas | ✅ `.fx-table` | ✅ |

### 4. Iconografia
| Aspecto | Cowork | Inertia | OK? |
|---|---|---|---|
| Empty state ShieldAlert | lucide | ✅ idem | ✅ |
| Search icon | FileSearch | ✅ idem | ✅ |

### 5. Status pill
| Aspecto | Cowork | Inertia | OK? |
|---|---|---|---|
| Pendente/Ciência warn | warn-soft + warn | ✅ STATUS_META mapping | ✅ |
| Confirmada ok | ok-soft + ok | ✅ idem | ✅ |
| Desconhecida/NãoRealizada bad | bad-soft + bad | ✅ idem | ✅ |

### 6. Pílula temporal prazo
| Aspecto | Cowork | Inertia | OK? |
|---|---|---|---|
| 3 níveis urgência | crit <7d, warn <30d, ok | ✅ prazoUrgency inline | ✅ |
| Mostra "vencido" se ≤0 | fallback | ✅ ternário inline | ✅ |

### 7. Estados condicionais
| Aspecto | Cowork | Inertia | OK? |
|---|---|---|---|
| Empty state nenhum DFe | ShieldAlert + msg | ✅ `.fx-empty` | ✅ |
| Search vazio | hint placeholder | ✅ idem | ✅ |

### 8. Componentes reutilizados
| Aspecto | Cowork | Inertia | OK? |
|---|---|---|---|
| FxShell | shared | ✅ | ✅ |
| formatDoc/brl/truncKey | _lib | ✅ | ✅ |
| `.fx-sefaz` SEFAZ pill | reaproveitado pra status DFe | ✅ | ✅ |
| `.fx-timepill u-{ok,warn,crit}` | reaproveitado pra prazo | ✅ | ✅ |

## Histórico

- **2026-05-20** — Wave 3 final PR.
