---
id: resources-js-pages-financeiro-categorias-index-casos
casos: Categorias · /financeiro/categorias
irmaos: charter ao lado (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso.
owner: wagner
last_run: "2026-08-17"
---

# Casos de uso — /financeiro/categorias

> **Status:** ✅ passa (prova no manifesto) · 🧪 prova existe mas o `it()` ainda **não cita o id** (G-2 — corrigir no mesmo PR) · ⬜ sem prova · ❌ quebrou.
>
> Redigido pelo [CC] em 2026-08-17 a partir do charter da tela + do protótipo Cowork. Regra G-2 ([ADR 0264]) respeitada: comportamento **sem teste** fica em **[BACKLOG] sem id** — `UC-*` órfão quebra o `casos-gate`.

Charter em **draft**. CRUD leve de tags que complementam o plano de contas.

## UC-CAT-01 — Criar categoria com cor e tipo
Status: 🧪 (`tests/Feature/Modules/Financeiro/CategoriaCrudTest.php`)
Quando o usuário salva o `CategoriaSheet` com nome, cor, tipo (receita/despesa/ambos) e plano opcional · Então a categoria nasce no business atual e aparece na lista.

## UC-CAT-02 — Editar categoria existente
Status: 🧪 (`CategoriaCrudTest`)
PUT no mesmo sheet altera só os campos enviados; a lista reflete sem recarregar a página.

## UC-CAT-03 — Inativar não apaga histórico
Status: 🧪 (`CategoriaCrudTest`)
Toggle ativo/inativo preserva os vínculos dos lançamentos antigos e mantém a posição de scroll.

## UC-CAT-04 — Exclusão é soft delete
Status: 🧪 (`CategoriaCrudTest`)
DELETE marca `deleted_at`; lançamentos antigos seguem vinculados (nunca hard delete).

## UC-CAT-05 — Tier 0
Status: 🧪 (`CategoriaCrudTest` / `MultiTenantIsolationTest`)
Categoria usa `BusinessScope`; nenhuma operação atravessa business ([ADR 0093]).

## Backlog de casos (sem id)
- **[BACKLOG] Não recategoriza lançamentos ao inativar/excluir** — Anti-hook do charter, hoje sem asserção.
- **[BACKLOG] `confirm()` nativo → `AlertDialog` DS** — a tela ainda usa `window.confirm` (regressão de DS catalogada em outras telas).
- **[PENDÊNCIA [W]]** comportamento esperado de categoria vinculada ao ser excluída (o charter deixou a pergunta aberta).

## Trilha do tempo
- 2026-08-17 · [CC] criado no espelho Cowork. Existe teste de CRUD; falta os `it()` citarem os ids (G-2).

[ADR 0264]: ../../memory/decisions/0264-governanca-executavel-trio-dominio-e2e.md
[ADR 0093]: ../../memory/decisions/0093-multi-tenant-isolation-tier-0.md
