---
id: requisitos-financeiro-despesas-parity
titulo: Paridade de migração — /expenses (Despesas) Blade↔React
tipo: parity
status: active
owner: W
criado: '2026-10-13'
tela: /expenses
related:
  - ../_DesignSystem/PARITY-TEMPLATE.md
  - RUNBOOK-despesas.md
related_adrs:
  - '0104-processo-mwart-canonico-unico-caminho'
  - '0093-multi-tenant-isolation-tier-0'
  - '0264-governanca-executavel-trio-dominio-e2e'
---

# Paridade — `/expenses` (lista de Despesas)

> Lado Blade lido em 2026-10-13 (`main` 94c005aec5): `resources/views/expense/index.blade.php`,
> `ExpenseController@index` (ramo `ajax()`) e o DataTable em `public/js/app.js` (`expense_table`).
> O lado React ainda não existe: a coluna "Está no React?" fica ⬜ até a US-MWART-016/017.
> Categorias de despesa e o formulário de nova despesa ganham tabela própria na US-MWART-018.

| # | Feature do Blade | Está no React? | Evidência (Blade) | Severidade se perdido | Defendido por (UC/teste) |
|---|---|---|---|---|---|
| 1 | Escopo `business_id` da sessão | ⬜ | `ExpenseController.php:71` | **alta** | US-MWART-014 (98×99) |
| 2 | Escopo `permitted_locations()` | ⬜ | `ExpenseController.php:147-150` | **alta** | US-MWART-014 |
| 3 | Sem `all_expense.access` e não-admin: só `created_by` ou `expense_for` = eu | ⬜ | `ExpenseController.php:161-166` | **alta** | US-MWART-014 |
| 4 | Gate de entrada `all_expense.access` ou `view_own_expense` (403) | ⬜ | `ExpenseController.php:46-48` | **alta** | US-MWART-014 |
| 5 | Tipos `expense` + `expense_refund` na mesma lista | ⬜ | `ExpenseController.php:72` | **alta** | US-MWART-014 |
| 6 | Estorno: total e devido negativos | ⬜ | `ExpenseController.php:202`, `:214` | **alta** | US-MWART-014 |
| 7 | Devido = `final_total − Σ transaction_payments` | ⬜ | `ExpenseController.php:211` | **alta** | US-MWART-014 |
| 8 | Filtro local | ⬜ | `index.blade.php:19-20` | média | US-MWART-016 |
| 9 | Filtro "despesa para" (usuário) | ⬜ | `index.blade.php:26-27` | média | US-MWART-016 |
| 10 | Filtro contato | ⬜ | `index.blade.php:32-33` | média | US-MWART-016 |
| 11 | Filtro categoria e subcategoria | ⬜ | `index.blade.php:39-48` | média | US-MWART-016 |
| 12 | Filtro período; padrão = ano fiscal | ⬜ | `index.blade.php:55-56` · `common.js` `dateRangeSettings` | média | US-MWART-016 |
| 13 | Filtro status de pagamento (pago/devido/parcial) | ⬜ | `index.blade.php:61-62` | média | US-MWART-016 |
| 14 | 14 colunas de dado (data, ref, recorrência, categoria, sub, local, status, imposto, total, devido, para, contato, nota, criado por) | ⬜ | `index.blade.php:93-107` | média | US-MWART-016 |
| 15 | Ordenação padrão: data desc | ⬜ | `app.js` `aaSorting: [[1,'desc']]` | média | US-MWART-016 |
| 16 | Selos na ref: recorrente, gerada por recorrência, estorno | ⬜ | `ExpenseController.php:242-253` | baixa | — |
| 17 | Rodapé: total, devido e contagem por status — **soma só a página visível** | ⬜ | `app.js` `fnDrawCallback` + `functions.js` `sum_table_col` | **alta** (VALOR) | D-DESP-TOTAL ([W]) + US-MWART-019 |
| 18 | Ação Editar (`expense.edit`) | ⬜ | `ExpenseController.php:178-180` | média | US-MWART-017 |
| 19 | Ação baixar/ver documento anexo | ⬜ | `ExpenseController.php:181-187` | média | US-MWART-017 |
| 20 | Ação Excluir (`expense.delete`) — apaga também pagamentos de caixa e `AccountTransaction` | ⬜ | `ExpenseController.php:188-190`, `:524-529` | **alta** | US-MWART-017 (chama o mesmo `destroy`) |
| 21 | Ação adicionar pagamento (só se não pago) e ver pagamentos | ⬜ | `ExpenseController.php:193-196` | **alta** | US-MWART-017 |
| 22 | Botão Adicionar (`expense.add`) | ⬜ | `index.blade.php:71-84` | média | US-MWART-018 |

## Divergências deliberadas

Nenhuma decidida ainda. Candidata: **item 17** (rodapé por página × total do filtro). É mudança de
VALOR exibido; só entra com decisão [W] D-DESP-TOTAL, prova dupla e tabela antes→depois.

## Itens alta → teste (F4)

Os itens 1–7 entram no teste de contrato da US-MWART-014 (antes de qualquer mudança no controller).
Os itens 17, 20 e 21 entram na US-MWART-019, contra a Page React.
