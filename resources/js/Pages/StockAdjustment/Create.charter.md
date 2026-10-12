---
id: resources-js-pages-stock-adjustment-create-charter
page: /stock-adjustments/create
component: resources/js/Pages/StockAdjustment/Create.tsx
related_prototype: n/a (herda PT-02 Form-Drawer; segue o Padrão de Tela)
related_visual_comparison: memory/requisitos/Estoque/_telas/stock-adjustment-create-visual-comparison.md
related_runbook: memory/requisitos/Estoque/_telas/RUNBOOK-stock-adjustment-create.md
related_us: [US-MWART-007]
bundle_source: estoque-page.jsx
tela: stock_adjustment/create
tipo: FORM CREATE
modulo: Inventory / StockAdjustment
status: draft
status_note: "F3 implementado"
adr_refs: [0104, 0093, 0114, 0149]
mwart_pattern_reuse:
  blueprint_cowork: prototipo-ui/cowork/Wagner/estoque-forms.jsx
  blueprint_screenshot_approval: "SYNC_LOG (pendente)"
  derived_screens: [Create]
  divergence_from_blueprint: "Tipo Normal/Abnormal destacado com cor (abnormal=rose perda)."
---

# Charter — StockAdjustment/Create.tsx

## Regras invariantes
- R-ADJ-001 (Tier 0)
- R-ADJ-002: adjustment_type ∈ {normal, abnormal}
- R-ADJ-003: total_amount_recovered ≤ final_total — conferida **só no cliente** (botão desabilitado); `store()` não valida (medido 2026-10-06, ver casos §Backlog)
- R-ADJ-004: purchase.create obrigatória

## Itens (2026-10-11)
- Cada linha é um **produto do cadastro** (produto + variação), escolhido na busca. A busca só abre com a filial escolhida e usa `/products/list` com o local — a mesma rota da Blade.
- Produto sem controle de estoque não aparece; sem saldo na filial aparece desabilitado.
- A linha vem de `GET /stock-transfers/produto-linha` (os dados do `get_product_row`, que a Blade também compartilha entre ajuste e transferência): saldo no local, unidade, lotes e último custo de compra.
- Sem subunidade (o ajuste da Blade é na unidade do produto). O mesmo produto pode entrar em mais de uma linha (lotes diferentes).
- A quantidade não passa do saldo da filial (ou do lote). Trocar a filial zera os itens. Sem item não salva.
- Envio: os campos da linha da Blade, com números em `paraNumUf` (vírgula decimal, sem milhar).
- Componente: `@/Components/shared/ItensMovimentacaoEstoque` (o mesmo da transferência).

## Casos
[Create.casos.md](Create.casos.md) — UC-AJCRT-01/02 (permissão e filiais do próprio business).

## Permissões
- Abrir e salvar: `purchase.create` (403 sem ela). Com o Superadmin instalado, exige assinatura ativa.
- `view_purchase_price` e `edit_purchase_price` chegam como `permissions`; `edit_price` decide só o `readonly` do campo — o servidor não re-checa no save.

## Estados
- Formulário vazio com data/hora atual (`default_datetime`) e uma filial a escolher.
- Valor recuperado maior que o total: botão Salvar desabilitado.
- Salvar **baixa saldo** na filial (`decreaseProductQuantity`, em transação) e volta para `/stock-adjustments`. Mudança nesse caminho segue a REGRA MESTRE; merge do [W].
