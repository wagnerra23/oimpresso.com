# Pedido pro [CL] — menu Vendas (o que é catch-up real)

**Lido no `main` em 2026-08-22 (tree `6a8e45998ee5`).** 8 telas do menu já são Inertia vivas (`Sells/{Index,Show,Edit,Drafts,Quotations,Subscriptions,Caixa/Index,Create,CreateV3}`) — **não** entram neste pedido.

## PRs propostos (ordem)
1. **PR-1 · Lista de POS** — `SellPosController@index` → `Inertia::render('Sells/Pos/Index')`, payload com totais por status e por forma (hoje o rodapé é calculado no DataTable).
2. **PR-2 · Remessas** — `SellController@shipments` → `Sells/Shipments/Index`, com o modal de status virando drawer PT-02.
3. **PR-3 · Devolução** — `SellReturnController` index + add; o `Sells/Index` vivo já linka `/sell-return/add/{id}` (charter v7).
4. **PR-4 · Descontos** — `DiscountController`; junto, **FormRequest** (achado A2) e alinhar a permissão da view (`brand.*` → `discount.access`, achado A1).
5. **PR-5 · Importação** — index + preview; mapeamento e validações já existem no controller, é tradução de tela.
6. **PR-6 · Pedido de venda** — `SalesOrderController`, condicional `enable_sales_order`.
7. **PR-7 · Caixa (turno)** — fecha o pendente que o `Sells/Caixa/Index.tsx` declara em tela ("Onda 6+1": movimentos e conferência física).

## Decisões que precisam de [W]
- D1 A permissão `discount.access` deve separar ver × editar? (hoje quem entra, exclui)
- D2 Importação grande vai pra fila? (hoje `max_execution_time=0` no request)
- D3 Reverter lote apaga vendas — mantém hard delete ou passa a cancelar?

## Trio nesta pasta
6 charters + 6 casos + `contrato/venda-menu.contract.json`.
