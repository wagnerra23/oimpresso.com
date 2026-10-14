---
page: /expense-categories
component: resources/js/Pages/Financeiro/CategoriasDespesa/Index.tsx
owner: wagner
status: draft
parent_module: Financeiro
related_prototype: prototipo-ui/cowork/Wagner/financeiro-legado.jsx
related_runbook: memory/requisitos/Financeiro/RUNBOOK-despesas.md
related_us: [US-MWART-018]
alcance:
  rota: /expense-categories
  rota_nome: expense-categories.index   # Route::resource('expense-categories') em routes/web.php (core)
  permission: expense.add               # ou expense.edit (ExpenseCategoryController@index)
  menu_hook: app/Http/Middleware/AdminSidebarMenu.php   # dropdown core "Despesas" — escondido com o Financeiro habilitado (decisão [W] 2026-05-20)
  pacote: expenses                      # enabled_modules do business
tier: B
charter_version: 1
---

# Page Charter — Financeiro/CategoriasDespesa/Index (DRAFT · PT-01 Lista)

> Carimbada por `criar-tela.mjs` (PT-01) e preenchida na US-MWART-018, parte 2 (thread financeiro/13).
> Atrás da mesma flag da lista de despesas, `useV2FinanceiroDespesas`; a Blade `expense_category.index`
> segue servindo com a flag desligada e com `?classico=1`. Sobe de `draft` → `live` com screenshot aprovado pelo [W].

## Mission

Manter as categorias e subcategorias de despesa do negócio, com os mesmos campos e regras da tela antiga.

## Goals — Features (faz)

- Lista as categorias do negócio, cada uma com as suas subcategorias, nome e código.
- Cria, edita e exclui pelos MESMOS endpoints da Blade (`store`, `update`, `destroy` do `ExpenseCategoryController`); excluir uma categoria leva as subcategorias junto, como hoje.
- Uma categoria pode virar subcategoria de outra (o `add_as_sub_cat` + `parent_id` da Blade).
- PT-BR em todo label/placeholder/mensagem.

## Non-Goals — Features (NÃO faz)

- _pendente [W]_ — Non-Goals e anti-hooks são preenchidos só pelo [W] (`charter-write`).

## UX Targets

- Cabe em 1280px.

## Refs

- RUNBOOK: `memory/requisitos/Financeiro/RUNBOOK-despesas.md` · Casos: [`Index.casos.md`](Index.casos.md)
- Padrão de Tela: PT-01 Lista · Constituição UI v2: UI-0013
