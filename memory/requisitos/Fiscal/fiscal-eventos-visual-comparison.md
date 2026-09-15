---
id: requisitos-fiscal-fiscal-eventos-visual-comparison
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

## Medição de runtime — 2026-09-08 · veredito NÃO MEDI (Onda 7)

> Registro do passo 3 do método da Onda 7. O `status: approved` do frontmatter é de
> **2026-05-20** e NÃO foi revisado aqui — auto-declarado não prova paridade de hoje, e
> esta medição não conseguiu substituí-lo. O que segue é o recibo do que foi medido.

| eixo | resultado |
|---|---|
| comando | `node scripts/design/design-diff-lote.mjs --tela Fiscal/Eventos --base-url https://staging.oimpresso.com` |
| veredito | **NÃO MEDI** (`rc=2`) — portão D0 recusou: identidade da view não provada |
| lado design | ✅ **renderizou** — 6/6 copies do contrato D0 presentes no render do espelho |
| lado prod | ❌ **não renderizou a tela** — o staging devolveu `RouteNotFoundException: Route [jana.acoes.index] not defined` (`Modules/Jana/Http/Controllers/DataController.php:295`) |
| causa | **deploy defasado do staging**, não divergência de fidelidade: a rota EXISTE no main (`Modules/Jana/Http/routes.php:157`). Quebra é geral — 4/4 telas de 3 módulos distintos (`/fiscal`, `/fiscal/nfe`, `/sells`, `/cliente`) devolvem a mesma exceção, porque o `MenuBuilder` do AppShell a consome |
| espelho (lado design) | fresco: bundle `2026-09-07T21:00Z` · `--preview-ds` COMPLETO · `ds-mirror-drift` 0 nos 4 temas · a âncora bate **sha256** com o manifesto do bundle |

⚠️ **Ao reabrir:** o D0 desta tela tem contrato (`governance/design/contracts/`), então a medição
volta a valer assim que o lado prod renderizar. Alinhar o tema com `--tema` — nesta rodada o
espelho veio `dark` e o alvo `light`, e comparar temas diferentes fabrica divergência falsa.
