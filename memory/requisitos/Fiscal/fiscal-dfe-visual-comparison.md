---
id: requisitos-fiscal-fiscal-dfe-visual-comparison
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

## Medição de runtime — 2026-09-08 · veredito NÃO MEDI (Onda 7)

> Registro do passo 3 do método da Onda 7. O `status: approved` do frontmatter é de
> **2026-05-20** e NÃO foi revisado aqui — auto-declarado não prova paridade de hoje, e
> esta medição não conseguiu substituí-lo. O que segue é o recibo do que foi medido.

| eixo | resultado |
|---|---|
| comando | `node prototipo-ui/design-diff-lote.mjs --tela Fiscal/Dfe --base-url https://staging.oimpresso.com` |
| veredito | **NÃO MEDI** (`rc=2`) — portão D0 recusou: identidade da view não provada |
| lado design | ✅ **renderizou** — 6/6 copies do contrato D0 presentes no render do espelho |
| lado prod | ❌ **não renderizou a tela** — o staging devolveu `RouteNotFoundException: Route [jana.acoes.index] not defined` (`Modules/Jana/Http/Controllers/DataController.php:295`) |
| causa | **deploy defasado do staging**, não divergência de fidelidade: a rota EXISTE no main (`Modules/Jana/Http/routes.php:157`). Quebra é geral — 4/4 telas de 3 módulos distintos (`/fiscal`, `/fiscal/nfe`, `/sells`, `/cliente`) devolvem a mesma exceção, porque o `MenuBuilder` do AppShell a consome |
| espelho (lado design) | fresco: bundle `2026-09-07T21:00Z` · `--preview-ds` COMPLETO · `ds-mirror-drift` 0 nos 4 temas · a âncora bate **sha256** com o manifesto do bundle |

⚠️ **Ao reabrir:** o D0 desta tela tem contrato (`prototipo-ui/contrato/`), então a medição
volta a valer assim que o lado prod renderizar. Alinhar o tema com `--tema` — nesta rodada o
espelho veio `dark` e o alvo `light`, e comparar temas diferentes fabrica divergência falsa.
