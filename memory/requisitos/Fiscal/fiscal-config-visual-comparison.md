---
id: requisitos-fiscal-fiscal-config-visual-comparison
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

## Medição de runtime — 2026-09-08 · veredito NÃO MEDI (Onda 7)

> Registro do passo 3 do método da Onda 7. O `status: approved` do frontmatter é de
> **2026-05-20** e NÃO foi revisado aqui — auto-declarado não prova paridade de hoje, e
> esta medição não conseguiu substituí-lo. O que segue é o recibo do que foi medido.

| eixo | resultado |
|---|---|
| comando | `node scripts/design/design-diff-lote.mjs --tela Fiscal/Config --base-url https://staging.oimpresso.com` |
| veredito | **NÃO MEDI** (`rc=2`) — portão D0 recusou: identidade da view não provada |
| lado design | ✅ **renderizou** — 6/6 copies do contrato D0 presentes no render do espelho |
| lado prod | ❌ **não renderizou a tela** — o staging devolveu `RouteNotFoundException: Route [jana.acoes.index] not defined` (`Modules/Jana/Http/Controllers/DataController.php:295`) |
| causa | **deploy defasado do staging**, não divergência de fidelidade: a rota EXISTE no main (`Modules/Jana/Http/routes.php:157`). Quebra é geral — 4/4 telas de 3 módulos distintos (`/fiscal`, `/fiscal/nfe`, `/sells`, `/cliente`) devolvem a mesma exceção, porque o `MenuBuilder` do AppShell a consome |
| espelho (lado design) | fresco: bundle `2026-09-07T21:00Z` · `--preview-ds` COMPLETO · `ds-mirror-drift` 0 nos 4 temas · a âncora bate **sha256** com o manifesto do bundle |

⚠️ **Ao reabrir:** o D0 desta tela tem contrato (`governance/design/contracts/`), então a medição
volta a valer assim que o lado prod renderizar. Alinhar o tema com `--tema` — nesta rodada o
espelho veio `dark` e o alvo `light`, e comparar temas diferentes fabrica divergência falsa.
