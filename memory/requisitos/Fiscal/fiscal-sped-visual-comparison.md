---
id: requisitos-fiscal-fiscal-sped-visual-comparison
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

## Medição de runtime — 2026-09-08 · veredito NÃO MEDI (Onda 7)

> Registro do passo 3 do método da Onda 7. O `status: approved` do frontmatter é de
> **2026-05-20** e NÃO foi revisado aqui — auto-declarado não prova paridade de hoje, e
> esta medição não conseguiu substituí-lo. O que segue é o recibo do que foi medido.

| eixo | resultado |
|---|---|
| comando | `node prototipo-ui/design-diff-lote.mjs --tela Fiscal/Sped --base-url https://staging.oimpresso.com` |
| veredito | **NÃO MEDI** (`rc=2`) — portão D0 recusou: identidade da view não provada |
| lado design | ✅ **renderizou** — 6/6 copies do contrato D0 presentes no render do espelho |
| lado prod | ❌ **não renderizou a tela** — o staging devolveu `RouteNotFoundException: Route [jana.acoes.index] not defined` (`Modules/Jana/Http/Controllers/DataController.php:295`) |
| causa | **deploy defasado do staging**, não divergência de fidelidade: a rota EXISTE no main (`Modules/Jana/Http/routes.php:157`). Quebra é geral — 4/4 telas de 3 módulos distintos (`/fiscal`, `/fiscal/nfe`, `/sells`, `/cliente`) devolvem a mesma exceção, porque o `MenuBuilder` do AppShell a consome |
| espelho (lado design) | fresco: bundle `2026-09-07T21:00Z` · `--preview-ds` COMPLETO · `ds-mirror-drift` 0 nos 4 temas · a âncora bate **sha256** com o manifesto do bundle |

⚠️ **Ao reabrir:** o D0 desta tela tem contrato (`prototipo-ui/contrato/`), então a medição
volta a valer assim que o lado prod renderizar. Alinhar o tema com `--tema` — nesta rodada o
espelho veio `dark` e o alvo `light`, e comparar temas diferentes fabrica divergência falsa.
