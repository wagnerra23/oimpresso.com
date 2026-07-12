---
page: /financeiro/categorias
component: resources/js/Pages/Financeiro/Categorias/Index.tsx
related_prototype: n/a (herda PT-01 Lista; segue o Padrão de Tela)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: Financeiro
related_adrs: [114, 101, 93]
tier: B
charter_version: 1
---

# Page Charter — /financeiro/categorias (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/Financeiro/Http/Controllers/CategoriaController@index` (rota `financeiro.categorias.index`, grupo `web/auth/language/timezone/AdminSidebarMenu`). CRUD livre de `fin_categorias` — tags complementares ao plano de contas.

---

## Mission
Cadastro leve de categorias financeiras (tags livres com cor) que complementam o plano de contas contábil fixo. O usuário cria/edita/inativa/exclui tags pra organizar lançamentos em relatórios e filtros, opcionalmente vinculando cada categoria a um plano de contas. É tela de CADASTRO, não de valores.

---

## Goals — Features (faz)
- Lista todas as categorias do business em tabela (`<table>`), ordenadas por ativo desc + nome.
- Mostra cor (swatch), nome, tipo (receita/despesa/ambos), plano de contas vinculado e status ativo/inativo.
- Cria categoria via `CategoriaSheet` (POST `/financeiro/categorias`).
- Edita categoria existente no mesmo sheet (PUT `/financeiro/categorias/{id}`).
- Alterna ativo/inativo (POST `/financeiro/categorias/{id}/toggle`, `preserveScroll`).
- Exclui via soft delete com `confirm()` nativo (DELETE `/financeiro/categorias/{id}`).
- Header canon `<PageHeader>` v3.8 + `FinanceiroSubNav` + botão primário "Nova categoria".

---

## Non-Goals — Features (NÃO faz)
- ❌ NÃO altera cálculo de valor, total, saldo ou estoque — categoria é rótulo/organização, sem efeito financeiro (inferência pendente Wagner).
- ❌ NÃO edita o plano de contas contábil (estrutura fixa vive em `/financeiro/plano-contas`).
- ❌ NÃO cruza dados entre businesses — `Categoria` usa `BusinessScope` (session `user.business_id`); cadastro é sempre tenant-isolado, nunca cross-tenant.
- ❌ NÃO faz hard delete (usa soft delete; registros antigos seguem vinculados).

---

## UX targets
- p95 < 1500ms (admin) / < 800ms (produção) ; cabe em 1280px (ROTA LIVRE) ; AppShellV2.

---

## Automation hooks (faz)
- Toasts `sonner` de sucesso/erro por mutação; `preserveScroll` no toggle/delete pra não perder posição.

---

## Anti-hooks (NÃO faz automaticamente)
- ❌ NÃO recategoriza lançamentos existentes automaticamente ao inativar/excluir categoria.
- ❌ NÃO dispara notificação nem job de fila.
- ❌ NÃO muta em GET — toggle/delete/create são POST/PUT/DELETE explícitos.

---

## Pendências antes de `status: live`
- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Smoke visual 1280/1440 (screenshot)
- [ ] Confirmar comportamento esperado de categorias vinculadas ao serem excluídas (soft delete preserva vínculo?)
