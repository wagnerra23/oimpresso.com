---
page: /sell-return
component: (F1) venda-blade.jsx
owner: wagner
status: draft
last_validated: "2026-08-22"
parent_module: Sells
related_prototype: prototipo-ui/cowork/venda-menu/
---

# Charter — Devolução de venda

**Fonte legado:** `sell_return/index + add + partials/product_row` · **Permissão:** `access_sell_return · access_own_sell_return`
**Frescor:** 🟠 catch-up real — medido no `main` em 2026-08-22 (tree `6a8e45998ee5`): nenhum `Inertia::render` no controller, a tela ainda é Blade.

## Missão
Devolver item de uma venda: quanto volta pro estoque e quanto vira crédito do cliente.

## Regras
- R1 A devolução é por LINHA: quantidade devolvida nunca maior que a vendida.
- R2 O total devolvido é somatório das linhas — a tela mostra antes de salvar.
- R3 Motivo é campo de texto livre e entra no histórico da venda.
- R4 Item produzido sob medida costuma não voltar: a tela avisa antes, não depois.

## Non-goals
- ❌ Não estornar pagamento aqui (é do Financeiro).
