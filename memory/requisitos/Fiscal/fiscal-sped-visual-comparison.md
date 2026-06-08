---
tela: Fiscal/Sped
url: /fiscal/sped
status: approved
approver: wagner
approved_at: 2026-05-20
prototype_source: "prototipo-ui/.../fiscal-data.jsx SPED_PERIODOS/LIVROS"
implementation: resources/js/Pages/Fiscal/Sped.tsx
adr: 0107
---

# Visual Comparison — Fiscal/Sped (PR #3 Wave)

## Blueprint Cowork

`prototipo-ui/.../fiscal-data.jsx::SPED_PERIODOS/LIVROS`. **PR #3 entrega placeholder** — gerador SPED real em PR dedicado.

## Approval

Wagner aprovou Wave 3 final 2026-05-20 — placeholder consciente, notice claro "em desenvolvimento".

## 8 dimensões

### 1. Layout grid
| Aspecto | Cowork | Inertia | OK? |
|---|---|---|---|
| Notice banner topo | warn gradient bg | ✅ inline style | ✅ |
| Tabela períodos | comp/status/notas/valor/prazo/export | ✅ `.fx-table` | ✅ |

### 2. Tipografia
| Aspecto | Cowork | Inertia | OK? |
|---|---|---|---|
| Competência mono bold | format MM/YYYY | ✅ `.fx-mono.fx-strong` | ✅ |
| Valor mono right-aligned | brl helper | ✅ idem | ✅ |

### 3. Densidade
| Aspecto | Cowork | Inertia | OK? |
|---|---|---|---|
| Row padding default | igual outras tabelas | ✅ `.fx-table` | ✅ |

### 4. Iconografia
| Aspecto | Cowork | Inertia | OK? |
|---|---|---|---|
| Download icon export disabled | lucide 11px | ✅ idem | ✅ |
| Archive icon livros section | lucide 20px | ✅ idem | ✅ |

### 5. Status pill
| Aspecto | Cowork | Inertia | OK? |
|---|---|---|---|
| Aberto warn | warn-soft + warn | ✅ STATUS_META | ✅ |
| Pronto/Entregue ok | ok-soft + ok | ✅ idem | ✅ |

### 6. Notice "em desenvolvimento"
| Aspecto | Cowork | Inertia | OK? |
|---|---|---|---|
| Banner warn gradient | linear-gradient warn-soft → white | ✅ inline style | ✅ |
| Texto explicativo | ref MemCofre/NfeBrasil SPEC futuro | ✅ idem | ✅ |

### 7. Export disabled
| Aspecto | Cowork | Inertia | OK? |
|---|---|---|---|
| Button download disabled | title hover | ✅ `<button disabled title="...">` | ✅ |

### 8. Reuso
| Aspecto | Cowork | Inertia | OK? |
|---|---|---|---|
| FxShell | shared | ✅ | ✅ |
| `.fx-table` + `.fx-sefaz` + `.fx-empty` | reaproveitados | ✅ | ✅ |
| brl helper | _lib | ✅ | ✅ |

## Histórico

- **2026-05-20** — Wave 3 final, **placeholder consciente**. Gerador SPED EFD ICMS/IPI + PIS/COFINS em PR dedicado pós-MVP fiscal.
