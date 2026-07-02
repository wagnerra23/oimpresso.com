---
tela: purchase/create
modulo: Purchase
tipo: FORM CREATE
generated_at: 2026-05-15
generated_by: Agent W2-D (Wave2 B5 Stock/Purchase MWART)
status: aguardando-screenshot-wagner
runbook: memory/requisitos/Compras/_telas/RUNBOOK-purchase-create.md
draft_tsx: resources/js/Pages/Purchase/Create.tsx
controller_delta: app/Http/Controllers/PurchaseController.php@createInertia
cowork_source: prototipo-ui/prototipos/compras/visual-source.html
---

# Visual Comparison — `purchase/create` (FORM CREATE)

> Gate visual ADR 0114. Wagner aprova SCREENSHOT, não tabela.

## Como rodar smoke local

```bash
# 1. Build front
npm run build
# 2. Login user biz=1 em http://oimpresso.test/login
# 3. Acessar Blade legacy: http://oimpresso.test/purchases/create
# 4. Acessar Inertia novo: http://oimpresso.test/purchases/create?v=2
# 5. Comparar lado-a-lado
```

## 15 dimensões — visão executiva

| # | Dimensão | Blade legacy | Draft Inertia | Status |
|---|---|---|---|---|
| 1 | Hierarquia visual | H1 sem ícone, sections widget | PageHeader + 4 Cards | OK |
| 2 | Densidade campos | 18+ campos pull-request espalhados | 8 sempre visíveis + 4 cards focados | melhor |
| 3 | Filtros | n/a (FORM) | n/a | OK |
| 4 | Tabela itens | Sortable+inline JQuery | React state + inline edit | reduzido MVP |
| 5 | Ações | 4 botões (Save/Cancel/Print/Stock) | 2 botões (Cancelar/Salvar) | reduzido MVP |
| 6 | Status pill | (não no form) | select dropdown | OK |
| 7 | Tipografia | system 14px | system 13px | mais compacto |
| 8 | Cores | bootstrap blue | stone-50 + rose-700 | aderente |
| 9 | Espaçamento | row gutter 20px | gap-3 grid | OK |
| 10 | Interatividade | jQuery select2 + datepicker | shadcn Input HTML5 | simplificado |
| 11 | Multi-tenant Tier 0 | ✅ business_id sessão | ✅ business_id via Controller param | OK |
| 12 | Performance | JS pesado (~120kb select2+datepicker) | useForm Inertia | melhor |
| 13 | a11y | bootstrap default | shadcn defaults | OK |
| 14 | Responsividade | row-col bootstrap | grid md/lg | OK |
| 15 | i18n | __() lang keys | PT-BR hardcoded MVP | 🟡 v0.2 |

## Limitações MVP1 (conscientes)

1. **Autocomplete fornecedor reduzido** — Input texto + ID (não busca). v0.2: ContactSearchAutocomplete reutilizar do Sells.
2. **Autocomplete produto reduzido** — Input texto manual. v0.2: ProductSearchAutocomplete.
3. **Sem pagamento embutido** — pagamento V2 (drawer/modal). Foco entrada estoque primeiro.
4. **Sem custom_field_1..4** — v0.2 dentro de `<details>` "Mais opções".
5. **Sem upload document** — v0.2.

## Decisão Wagner — aprova screenshot?

- [ ] Renderiza sem erro com biz=1
- [ ] Permite criar compra mínima (filial + fornecedor_id + 1 item + total)
- [ ] POST `/purchases` aceita FormData e cria `transactions.type='purchase'`
- [ ] Tier 0 preservado (locations só da business)

---

## Modo grade tam×cor (US-COM-005) — comparação adicional

> Consolidado P5 (2026-07-02): incorpora o gate visual do **modo grade** (antes em `Purchase/create-visual-comparison.md`, `last_validated 2026-06-22`). Gate visual ADR 0114/0107 — Wagner aprova o **SCREENSHOT 1280px**, não a tabela. Blueprint Cowork: `prototipo-ui/prototipos/compras-grade-matrix/Compras - Grade Matrix.html`. Status: `pendente-aprovacao-wagner`.

Adiciona um bloco **"Adicionar por grade"** dentro do card "Itens da compra" da `Purchase/Create.tsx`: combobox de produto `variable` → `<GradeMatrixInput>` (matriz tam×cor, Σ linha/coluna/total, teclado Cin7/Lightspeed) → botão "Adicionar à compra" que acumula as células no repeater `linhas` existente. O fluxo manual (buscar texto → "Adicionar item") permanece intacto.

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

**Divergências conhecidas (aceitas):** visual Tailwind/shadcn (não bundle Cowork denso) — coerência com a própria tela MWART, não com o cockpit `/compras`; `2× clique col-head` (preencher coluna) fica V1.1; quick-fill OCR/paste Excel fora de escopo (V2).

**Aprovação grade:**
- [ ] **F1.5/F2 screenshot 1280px** anexado + `[W2]: approved` (Wagner) — **PENDENTE**
- [ ] **F5 canary biz=4** — Larissa faz 1 entrada por grade cronometrada (meta ≤2min/modelo)
