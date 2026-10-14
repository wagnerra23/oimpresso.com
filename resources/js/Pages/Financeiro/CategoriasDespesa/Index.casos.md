---
casos: Financeiro/CategoriasDespesa/Index · /expense-categories
irmaos: Index.charter.md (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — o contrato de teste nasce junto com a tela, não depois.
owner: wagner
last_run: "2026-10-14"
---

# Casos de Uso & Aceite — Financeiro/CategoriasDespesa/Index

> Derivados do `ExpenseCategoryController` e da Blade `expense_category.index` (lidos em 2026-10-14), não do `.tsx`.
> A tela nova grava pelos mesmos endpoints da Blade, então os casos valem para as duas.
> Teste: `tests/Feature/Expense/CategoriasDespesaContratoTest.php` (lane `financeiro-pest`), tenant 98 × business 2.
> **Status:** ✅ passa · 🧪 teste cita o UC e passa · ⬜ não verificado · ❌ quebrou.

---

## UC-CATD-01 `[T0]` · A lista nova mostra só as categorias do meu negócio
- **Aceite:** Dado uma categoria minha e uma de outro negócio · Quando abro a lista com a flag ligada · Então vejo a minha e não a outra.
- **Status: 🧪**

## UC-CATD-02 · Criar grava no meu negócio; como subcategoria, grava o pai
- **Aceite:** Dado uma categoria de topo · Quando crio uma categoria e uma subcategoria dela · Então as duas ficam no meu negócio e a subcategoria aponta para o pai.
- **Status: 🧪**

## UC-CATD-03 `[T0]` · Editar altera a minha categoria e não alcança a de outro negócio
- **Aceite:** Dado uma categoria minha e uma de outro negócio · Quando tento renomear as duas · Então só a minha muda; a outra responde `success: false` e fica como estava.
- **Status: 🧪**

## UC-CATD-04 `[T0]` · Excluir leva as minhas subcategorias junto e não alcança outro negócio
- **Aceite:** Dado uma categoria com uma subcategoria, e uma categoria de outro negócio cujo pai tem o mesmo id · Quando excluo a minha · Então a minha e a subcategoria saem (exclusão lógica) e a do outro negócio fica.
- **Status: 🧪**

## UC-CATD-05 · Com a flag desligada, ou com `?classico=1`, a tela é a Blade
- **Aceite:** Dado a flag desligada · Quando abro `/expense-categories` · Então recebo a Blade; com a flag ligada e `?classico=1`, também.
- **Status: 🧪**

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

- **[BACKLOG]** Recusar a exclusão de categoria com despesa lançada, como o protótipo mostra. Hoje o servidor exclui (lógico) e as despesas ficam com a categoria antiga. É mudança de comportamento: decisão [W].
- **[BACKLOG]** Colunas "Lançamentos" e "Gasto no mês" do protótipo. Não existem na tela antiga e o gasto é valor exibido novo: decisão [W].
- **[BACKLOG]** Hipótese não testada: o `parent_id` enviado no `store`/`update` não é conferido contra o negócio da sessão. Precisa de teste antes de virar achado.

## Trilha do tempo
- 2026-10-14 · [CL] carimbado por criar-tela.mjs e preenchido na US-MWART-018 parte 2 (thread financeiro/13).
