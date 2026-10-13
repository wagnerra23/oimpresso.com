---
slug: financeiro-runbook-despesas
title: "Financeiro — Runbook da migração MWART de Despesas e Categorias de despesa"
type: runbook
module: Financeiro
tela: Financeiro/Despesas/Index
owner: W
status: ativo
last_validated: "2026-10-13"
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0104-processo-mwart-canonico-unico-caminho
  - 0114-prototipo-ui-cowork-loop-formalizado
---

# RUNBOOK — Despesas (`/expenses`) e Categorias de despesa (`/expense-categories`) → Inertia

> **Tipo:** F1 PLAN do processo MWART ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)).
> **Dona da migração:** [US-MWART-009](../Mwart/SPEC.md) (Onda 6, camada Account). Este runbook é a fatia
> "despesas" dela; as subtasks são US-MWART-014..019.
> **Origem:** thread 13 do playbook `cowork-inbox/financeiro` ([CL] 2026-10-13).

As duas telas são do core UltimatePOS (`app/Http/Controllers/ExpenseController.php` e
`ExpenseCategoryController.php`), hoje 100% Blade + DataTables. A despesa é uma linha de `transactions`
com `type IN ('expense','expense_refund')`. Persona: quem lança e paga despesa no escritório
(Eliana[E]). Layout: AppShellV2 + PT-01 Lista + drawer 760.

## 1. Objetivo

Trocar a lista de despesas e a de categorias por Pages Inertia, com a Blade como fallback
(`?classico=1`) até o cutover, sem perder nenhum filtro, coluna ou ação da Blade
([`despesas-parity.md`](despesas-parity.md)).

**Lido, não inventado:** a despesa **já vira título** do Financeiro. O `TituloAutoService` gera
`fin_titulos` com `origem='despesa'` na criação (`TituloAutoServiceExpenseTest`), e o
`BridgeExpenseToTitulosCommand` cobre o histórico. A migração da tela **não muda isso**: ela
continua gravando `transactions` pelo mesmo `store()`.

## 2. Pré-condições

- [ ] Permissões Spatie: `all_expense.access` **ou** `view_own_expense` (ver); `expense.add`, `expense.edit`, `expense.delete`. Categorias: `expense.add` **ou** `expense.edit`.
- [ ] Alvos do protótipo gravados: `governance/design/targets/financeiro--despesas--index.alvo.json` e `financeiro--categorias-despesa--index.alvo.json` (thread A3).
- [ ] Fonte de design: `prototipo-ui/cowork/Wagner/financeiro-legado.jsx` (`telaDespesas`, `telaCategorias`), rotas `fin-despesas` / `fin-categorias`.
- [ ] Pages: `resources/js/Pages/Financeiro/Despesas/Index.tsx` e `resources/js/Pages/Financeiro/CategoriasDespesa/Index.tsx`. Os caminhos casam com os slugs dos alvos. `Pages/Financeiro/Categorias` já existe e é a `fin_categorias`, outra tabela.

## 3. Passo-a-passo (= US-MWART-014..019)

1. **014 · F2 baseline.** Pest de contrato do `index` JSON atual (filtros, escopo `permitted_locations`, `view_own_expense`, `expense_refund` negativo), tenant fictício 98 × 99 ([ADR 0358](../../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)). Nada muda no controller.
2. **015 · F2 dual action.** `index()` sem `ajax()`: `?classico=1` → Blade; senão `Inertia::render('Financeiro/Despesas/Index')`. **O ramo `ajax()` fica intacto**: o relatório "despesas por representante" (`report/partials/sales_representative_expenses.blade.php`) consome o mesmo JSON pelo filtro `expense_for`.
3. **016 · F3 lista.** Payload paginado no servidor (`Inertia::defer` nas props caras), filtros da Blade, colunas, rodapé. Trio `.tsx` + `.charter.md` + `.casos.md`.
4. **017 · F3 drawer.** Clique na linha abre drawer 760 com a despesa (dados de `show()`). Ações Editar / Pagamento / Documento / Excluir com os mesmos gates de permissão.
5. **018 · F3 nova despesa + categorias.** `create` vira sheet; `CategoriasDespesa/Index` substitui `expense_category.index` (pai/sub, código).
6. **019 · F4 QA.** Itens `alta` da paridade com teste de comportamento; `secao-check` contra os alvos; smoke biz=1.

## 4. Tokens CSS

Só tokens semânticos do app. Nada de cor crua. O status de pagamento usa o mesmo badge do Financeiro Unificado.

## 5. Estados visuais

Vazio ("Nenhuma despesa no período"), carregando (skeleton do `<Deferred>`), erro de rede, linha `expense_refund` com selo "Estorno" e valor negativo, despesa recorrente com selo de recorrência.

## 6. Responsividade

1280px é o piso (monitor do cliente piloto). Abaixo disso a grade rola na horizontal e os filtros quebram em 2 linhas.

## 7. Atalhos

`/` foca a busca; `Esc` fecha o drawer. Listener com `removeEventListener` no cleanup e bloqueado dentro de `<input>`.

## 8. Component contract

Props do servidor: `filtros` (eager); `opcoes` (locais, usuários, contatos, categorias, subcategorias — defer); `despesas` (paginado — defer); `pode` (`add`/`edit`/`delete`/`verTodas`).

## 9. DoD checklist

- [ ] Cada US 014..019 em PR próprio, ≤300 linhas.
- [ ] Ramo `ajax()` do `index` igual ao de hoje (o relatório depende dele).
- [ ] D-DESP-TOTAL decidida antes da 016 (ver §10).
- [ ] Nenhum `store`/`update`/`destroy` reescrito: a React chama os mesmos endpoints.

## 10. Pegadinhas

- **O rodapé soma só a página visível.** Na Blade, `fnDrawCallback` soma `final-total` e `payment_due` das linhas renderizadas (25 por página), com o estorno negativo (`public/js/app.js` + `sum_table_col` em `public/js/functions.js`). Um total "do filtro inteiro" no React **muda o número exibido**. Isso é VALOR (regra mestre em `proibicoes.md`), então a **D-DESP-TOTAL** fica com o [W]: manter por página, ou trocar por total do filtro com rótulo explícito, prova dupla e tabela antes→depois.
- **Período padrão = ano fiscal do business** (`dateRangeSettings` em `public/js/common.js`), não o mês.
- **Escopo do usuário:** sem `all_expense.access` e sem ser admin, vê só o que criou **ou** o que é `expense_for` dele. E sempre `permitted_locations()`.
- **`destroy` apaga também** `cash_register_payments` e `AccountTransaction` da despesa. A React não pode reimplementar a exclusão.
- Pagamento e "ver pagamentos" são modais Blade do `TransactionPaymentController`. No drawer, abrir pela rota existente até eles migrarem.

## 11. ADR de origem

[ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) (processo) · [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) (Tier 0) · [ADR 0114](../../decisions/0114-prototipo-ui-cowork-loop-formalizado.md) (protótipo como fonte de design).
