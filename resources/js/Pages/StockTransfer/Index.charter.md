---
id: resources-js-pages-stock-transfer-index-charter
page: /stock-transfers
component: resources/js/Pages/StockTransfer/Index.tsx
related_prototype: n/a (herda PT-01 Lista; segue o Padrão de Tela)
related_visual_comparison: memory/requisitos/Estoque/_telas/stock-transfer-index-visual-comparison.md
related_runbook: memory/requisitos/Estoque/_telas/RUNBOOK-stock-transfer-index.md
related_us: [US-MWART-007]
bundle_source: estoque-page.jsx
tela: stock_transfers/index
tipo: LIST
modulo: Inventory / StockTransfer
status: draft
status_note: "F3 implementado"
adr_refs: [0104, 0093, 0114, 0149]
mwart_pattern_reuse:
  blueprint_cowork: prototipo-ui/cowork/Wagner/estoque-page.jsx
  blueprint_screenshot_approval: "SYNC_LOG (pendente)"
  derived_screens: [Index]
  divergence_from_blueprint: "Regiao do blueprint: aba Transferencias (AbaTransferencias). Densidade de tabela herdada de Purchase/Index.tsx."
---

# Charter — StockTransfer/Index.tsx

## Persona
Maiara — listagem rápida transferências (origem→destino, status, total).

## Regras invariantes
- R-XFER-001 (Tier 0)
- R-XFER-002: ownership filter via `view_own_purchase`
- R-XFER-003: status final só após `completed` (estoque movido)
- R-XFER-004: origem ≠ destino — hoje conferida só no formulário; `store()` não valida (ver `Create.casos.md` §Backlog)

## Casos
[Index.casos.md](Index.casos.md) — achados abertos em §Backlog · UC-TRIDX-01/02.

## Permissões
- Abrir: `purchase.view` **ou** `purchase.create` **ou** `view_own_purchase` (403 sem nenhuma).
- Mudar status: `purchase.update`. Excluir: `purchase.delete`.

## Estados
- Cada linha é o par `sell_transfer` (origem) + `purchase_transfer` (destino); status `final` aparece como `completed`.
- Lista vazia sem filtro: "Registrar primeira transferência" (se `create`); com filtro, aviso de filtro sem resultado. Máximo de 200 linhas, sem paginação.
- Só abre em React com `?v=2`; sem isso o mesmo `index()` serve a Blade.
