---
page: /expenses
component: resources/js/Pages/Financeiro/Despesas/Index.tsx
owner: wagner
status: draft
parent_module: Financeiro
related_prototype: prototipo-ui/cowork/Wagner/financeiro-legado.jsx
related_runbook: memory/requisitos/Financeiro/RUNBOOK-despesas.md
alcance:
  rota: /expenses
  rota_nome: expenses.index          # Route::resource('expenses') em routes/web.php (core)
  permission: all_expense.access     # ou view_own_expense (ExpenseController@index)
  menu_hook: app/Http/Middleware/AdminSidebarMenu.php   # dropdown core "Despesas" — escondido quando o Financeiro está habilitado (decisão [W] 2026-05-20)
  pacote: expenses                   # enabled_modules do business
tier: B
charter_version: 1
---

# Page Charter — Financeiro/Despesas/Index (DRAFT · PT-01 Lista)

> Carimbada por `criar-tela.mjs` (PT-01) e preenchida na US-MWART-016 (thread financeiro/13).
> Atrás da flag `useV2FinanceiroDespesas`; a Blade `expense.index` segue servindo com a flag desligada
> e com `?classico=1`. Sobe de `draft` → `live` com screenshot aprovado pelo [W].

## Mission

Ver as despesas e os estornos do período, com quanto falta pagar, filtrando como na tela antiga.

## Goals — Features (faz)

- Lista paginada no servidor (25 por página, data mais recente primeiro), com a MESMA consulta e o MESMO escopo do DataTable legado (`ExpenseController::consultaDespesas`).
- Filtros da Blade: local, despesa para, contato, categoria, subcategoria, status de pagamento e período (padrão = ano fiscal).
- Colunas da Blade, estorno com selo e valores negativos, selos de recorrência.
- Rodapé com total, devido e contagem por status **da página visível**, igual à Blade.
- PT-BR em todo label/placeholder/mensagem.

## Non-Goals — Features (NÃO faz)

- _pendente [W]_ — Non-Goals e anti-hooks são preenchidos só pelo [W] (`charter-write`).

## UX Targets

- Cabe em 1280px; a grade rola na horizontal.

## Refs

- Paridade: `memory/requisitos/Financeiro/despesas-parity.md`
- Padrão de Tela: PT-01 Lista · Constituição UI v2: UI-0013
- Casos: [`Index.casos.md`](Index.casos.md)
