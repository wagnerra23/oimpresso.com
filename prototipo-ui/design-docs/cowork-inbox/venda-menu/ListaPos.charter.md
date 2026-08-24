---
page: /pos
component: (F1) venda-blade.jsx
owner: wagner
status: draft
last_validated: "2026-08-22"
parent_module: Sells
related_prototype: prototipo-ui/cowork/venda-menu/
---

# Charter — Lista de POS

**Fonte legado:** `sale_pos/index.blade.php + partials/sales_table` · **Permissão:** `sell.view (ver) · sell.create (abrir POS) · sell.delete (excluir)`
**Frescor:** 🟠 catch-up real — medido no `main` em 2026-08-22 (tree `6a8e45998ee5`): nenhum `Inertia::render` no controller, a tela ainda é Blade.

## Missão
Índice das vendas de balcão (`is_direct_sale = 0`) — o que a Larissa confere no fim do turno. Mesmas colunas do `sales_table`, mais o rodapé de totais que o DataTable calcula.

## Regras
- R1 A lista mostra só venda de POS; venda direta vive em "Todas as vendas" (`Sells/Index.tsx`, vivo).
- R2 Rodapé soma total, pago e em aberto **do filtro atual**, e conta por status de pagamento e por forma.
- R3 Linha vencida (`overdue`) recebe trilho de urgência; nunca cor crua.
- R4 "Adicionar pagamento" só aparece com saldo devedor.
- R5 Excluir exige `sell.delete`; sem a permissão o item aparece dizendo o motivo, não some.
- R6 Período filtra por `transaction_date` (o daterangepicker do blade).

## Non-goals
- ❌ Não refazer o detalhe da venda: `Sells/Show.tsx` é vivo — o drawer daqui é ponte, não dono.
- ❌ Não emitir NF-e desta lista (é do `Sells/Index` vivo).
