---
title: "Visual comparison — Purchase/Create modo grade (US-COM-005)"
module: Purchase
tela: Purchase/Create
status: pendente-aprovacao-wagner
last_validated: "2026-06-22"
related_adrs:
  - 0114-prototipo-ui-cowork-loop-formalizado
  - 0107-emendation-0104-visual-comparison-gate-f3
  - 0104-processo-mwart-canonico-unico-caminho
blueprint_cowork: prototipo-ui/prototipos/compras-grade-matrix/Compras - Grade Matrix.html
blueprint_screenshot_approval: "PENDENTE (gate F2/F3 — Wagner aprova screenshot, não tabela)"
---

# Visual comparison — Purchase/Create (modo grade tam×cor)

> Gate visual ADR 0114/0107. A tabela abaixo é referência; **a aprovação é do SCREENSHOT 1280px**
> (monitor Larissa), não desta tabela. Status fica `pendente-aprovacao-wagner` até o `[W2]: approved` no smoke.

## Escopo da mudança visual
Adiciona um bloco **"Adicionar por grade"** dentro do card "Itens da compra" da `Purchase/Create.tsx`:
combobox de produto `variable` → `<GradeMatrixInput>` (matriz tam×cor, Σ linha/coluna/total, teclado
Cin7/Lightspeed) → botão "Adicionar à compra" que acumula as células no repeater `linhas` existente.
O fluxo manual (buscar texto → "Adicionar item") permanece intacto.

## 15 dimensões (referência protótipo Cowork → Inertia)

| # | Dimensão | Protótipo (`compras-grade-matrix`) | Implementação Inertia | OK |
|---|---|---|---|---|
| 1 | Layout matriz | linhas=tam, cols=cor, Σ canto | idem (`GradeMatrixInput`) | ☐ |
| 2 | Σ on-the-fly | useMemo por linha/coluna/grand | idem | ☐ |
| 3 | Teclado | Tab/Enter/Esc/setas | idem (handleKeyDown) | ☐ |
| 4 | Célula com valor | destaque visual | input destacado | ☐ |
| 5 | Empty state single | 1 input qty | mode='single' | ☐ |
| 6 | Custo unitário | 1 por modelo | input unitCost | ☐ |
| 7 | Densidade 1280px | cabe sem scroll-x | conferir no smoke | ☐ |
| 8 | Tokens/cor | neutros stone + accent | Tailwind stone + primary | ☐ |
| 9 | Tipografia | tabular-nums | tabular-nums | ☐ |
| 10 | Barra de atalhos | rodapé kbd | rodapé kbd | ☐ |
| 11 | Combobox produto | select mock | `GradeProductCombobox` (real `/purchases/get_products`) | ☐ |
| 12 | Feedback "adicionado" | badge linhas | linhas no repeater + total | ☐ |
| 13 | Acessibilidade | aria-label célula | aria-label célula | ☐ |
| 14 | Integração ao form | payload `{model,lines}` | expande pra `purchases` (POST único) | ☐ |
| 15 | Coerência com a tela | bloco isolado | dentro do card "Itens da compra" | ☐ |

## Divergências conhecidas (aceitas)
- Visual `Purchase/Create` é Tailwind/shadcn (não bundle Cowork denso) — coerência com a própria tela MWART, não com o cockpit `/compras`. Aceito até cliente reportar (review trigger C1 #1).
- `2× clique col-head` (preencher coluna) do protótipo: **V1.1** (fora deste PR).
- Quick-fill por OCR / paste Excel: fora de escopo (V2).

## Aprovação
- [ ] **F1.5/F2 screenshot 1280px** anexado + `[W2]: approved` (Wagner) — **PENDENTE**
- [ ] **F5 canary biz=4** — Larissa faz 1 entrada por grade cronometrada (meta ≤2min/modelo)

> Anexar screenshot real aqui (smoke `/purchases/create?v=2` com produto variável) antes de marcar approved.
