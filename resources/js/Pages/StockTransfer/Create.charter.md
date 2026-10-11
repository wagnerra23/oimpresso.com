---
id: resources-js-pages-stock-transfer-create-charter
page: /stock-transfers/create
component: resources/js/Pages/StockTransfer/Create.tsx
related_prototype: n/a (herda PT-02 Form-Drawer; segue o Padrão de Tela)
related_visual_comparison: memory/requisitos/Estoque/_telas/stock-transfer-create-visual-comparison.md
related_runbook: memory/requisitos/Estoque/_telas/RUNBOOK-stock-transfer-create.md
related_us: [US-MWART-007]
bundle_source: estoque-page.jsx
tela: stock_transfers/create
tipo: FORM CREATE
modulo: Inventory / StockTransfer
status: draft
status_note: "F3 implementado"
adr_refs: [0104, 0093, 0114, 0149]
mwart_pattern_reuse:
  blueprint_cowork: prototipo-ui/cowork/Wagner/estoque-forms.jsx
  blueprint_screenshot_approval: "SYNC_LOG (pendente)"
  derived_screens: [Create]
  divergence_from_blueprint: "Origem→Destino destacado no topo (regra crítica R-XFER-004)."
---

# Charter — StockTransfer/Create.tsx

## Regras invariantes
- R-XFER-001 (Tier 0 IRREVOGÁVEL)
- R-XFER-004: origem ≠ destino — conferida **só no cliente**; `store()` não compara as duas filiais (medido 2026-10-06, ver casos §Backlog)
- R-XFER-005: status=completed → estoque movido server-side

## Itens (2026-10-11)
- Cada linha é um **produto do cadastro** (produto + variação), escolhido na busca. A busca só abre com a filial de origem e usa `/products/list` com o local de origem — a mesma rota da Blade.
- Produto sem controle de estoque não aparece; sem saldo na origem aparece desabilitado.
- A linha vem de `GET /stock-transfers/produto-linha` (os dados do `get_product_row` da Blade): saldo no local, unidade, subunidades, lotes e último custo de compra.
- A quantidade não passa do saldo da origem (ou do lote). Trocar a origem zera os itens. Sem item não salva.
- Envio: os campos da linha da Blade, com números em `paraNumUf` (vírgula decimal, sem milhar).

## UX crítica
Bloqueio visual se origem == destino (forma + button disabled). O servidor **não** repete essa checagem hoje — o bloqueio da tela é a única barreira.

## Casos
[Create.casos.md](Create.casos.md) — UC-TRCRT-01/02 (permissão, filiais do próprio business e os 3 status).

## Permissões
- Abrir e salvar: `purchase.create` (403 sem ela).
- `edit_price` decide só o `readonly` do preço; o servidor não re-checa no save.

## Estados
- Status de entrada: `pending`, `in_transit`, `completed`. `completed` é gravado como `final` e move o estoque na hora.
- `completed` tira saldo da origem e põe no destino, em transação. Mudança nesse caminho segue a REGRA MESTRE; merge do [W].
