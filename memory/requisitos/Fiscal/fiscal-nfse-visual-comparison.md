---
id: requisitos-fiscal-fiscal-nfse-visual-comparison
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

## Medição de runtime — 2026-11-05 · veredito DIVERGE (bug) · 1

> Substitui, como estado atual, a rodada de 2026-09-08 logo abaixo. Aquela não chegou a comparar
> nada (staging com deploy defasado, temas diferentes) e caducou; fica como fato datado.

| eixo | resultado |
|---|---|
| comando | `node scripts/design/design-diff-lote.mjs --incluir-comparadas --tema light --tela Fiscal/Nfse --base-url http://127.0.0.1:8000 --user-id <admin do biz seedado>` |
| onde | `design-smoke-ci.yml` por `workflow_dispatch` no branch de medição `claude/fiscal-lote5-medir-nao-mergear` (run [42085947187](https://github.com/wagnerra23/oimpresso.com/actions/runs/42085947187)), app efêmero buildado de `main` @ `7d997591d7`, seeds Visreg, login pelo `/_visreg-login` |
| tema | `light` forçado nos dois lados (`sameTheme: true`) |
| âncora | `node scripts/design/ancora.mjs Fiscal/Nfse` → `prototipo-ui/cowork/Wagner/fiscal-page.jsx` |
| veredito | **DIVERGE (bug) · 1** |

| dim | campo | produção | protótipo | veredito |
|---|---|---|---|---|
| D4 | título `font-weight` | 700 | 600 | **DÍVIDA A FECHAR** — Δ 100 |
| D9 | subnav (fora da sonda, lido da assinatura de texto) | 7 abas | 9 abas (+ "MDF-e · CT-e", + "Tributação") | **ACHADO, decisão [W]** — o `fiscal-subnav.contract.json` e o `paginas-fiscais.tsx` têm 7; o `FX_TABS` do protótipo tem 9 |

- As regiões D9 (`data-contract`) quase não se casam por **nome**: a produção expõe `fiscal-subnav`, `debitos-conhecidos`, `sb-*` e as âncoras `fiscal-*`, o protótipo `procedencia`, `tabela-*`, `toolbar-*`. O comparador as registra como `SEM-DADO`, não como divergência.
- A produção mostrou o banner de consentimento de cookies por cima da tela; ele entra no texto medido, não nos estilos medidos.


## Medição de runtime — 2026-09-08 · veredito NÃO MEDI (Onda 7)

> ⚠️ **Caducou em 2026-11-05** — ver a medição acima. Preservado como fato datado.


> Registro do passo 3 do método da Onda 7. O `status: approved` do frontmatter é de
> **2026-05-20** e NÃO foi revisado aqui — auto-declarado não prova paridade de hoje, e
> esta medição não conseguiu substituí-lo. O que segue é o recibo do que foi medido.

| eixo | resultado |
|---|---|
| comando | `node scripts/design/design-diff-lote.mjs --tela Fiscal/Nfse --base-url https://staging.oimpresso.com` |
| veredito | **NÃO MEDI** (`rc=2`) — portão D0 recusou: identidade da view não provada |
| lado design | ✅ **renderizou** — 6/6 copies do contrato D0 presentes no render do espelho |
| lado prod | ❌ **não renderizou a tela** — o staging devolveu `RouteNotFoundException: Route [jana.acoes.index] not defined` (`Modules/Jana/Http/Controllers/DataController.php:295`) |
| causa | **deploy defasado do staging**, não divergência de fidelidade: a rota EXISTE no main (`Modules/Jana/Http/routes.php:157`). Quebra é geral — 4/4 telas de 3 módulos distintos (`/fiscal`, `/fiscal/nfe`, `/sells`, `/cliente`) devolvem a mesma exceção, porque o `MenuBuilder` do AppShell a consome |
| espelho (lado design) | fresco: bundle `2026-09-07T21:00Z` · `--preview-ds` COMPLETO · `ds-mirror-drift` 0 nos 4 temas · a âncora bate **sha256** com o manifesto do bundle |

⚠️ **Ao reabrir:** o D0 desta tela tem contrato (`governance/design/contracts/`), então a medição
volta a valer assim que o lado prod renderizar. Alinhar o tema com `--tema` — nesta rodada o
espelho veio `dark` e o alvo `light`, e comparar temas diferentes fabrica divergência falsa.
