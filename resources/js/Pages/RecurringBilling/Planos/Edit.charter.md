---
page: /recurring-billing/planos/{id}/editar
component: resources/js/Pages/RecurringBilling/Planos/Edit.tsx
owner: wagner
status: live
last_validated: "2026-05-17"
parent_module: RecurringBilling
related_adrs: [93, 94, 101, 104, 107, 110]
tier: A
charter_version: 1
sidebar_group: fin (FINANCEIRO)
related_prototype: n/a (herda PT-02 Form/Drawer; segue o Padrao de Tela)  # 2026-11-19 [CC]: o motivo de 2026-09-09 caducou — a aba "planos" do `cobranca-recorrente-page.jsx` deixou de ser `<Placeholder>` (commit 75e44b87d8, 2026-10-28) e hoje mapeia `rb-planos` com drawers `novo`→Planos/Create e `editar`→Planos/Edit (`:1059`). O n/a fica porque o arquivo segue porte REVERSO (`:2` "Reescreve a RecurringBilling do git"): promover ancoraria a tela nela mesma (§5 2026-06-05); promover é decisão [W]. Registro de 2026-09-09 preservado: 2026-09-09 [C]: idem `Create.charter.md` ao lado (mesma medição, mesma aba placeholder, mesmo porte reverso). ⚠️ PT-02 sem template renderizado — decisão [W] em §5.7. medido 2026-09-09 pelas 3 pernas (repo inteiro com --hidden, projeto Cowork por ID via DesignSync.list_files, espelho). Ver memory/requisitos/_DesignSystem/INVENTARIO-ANCORAS-2026-09-09.md §5.3 + §5.7.
related_us: [US-RB-001]
---

# Page Charter — /recurring-billing/planos/{id}/editar (Editar Plano · v1)

## Mission

Formulário pra editar plano existente — submete PUT `/recurring-billing/planos/{id}` e redireciona pra Index com flash de sucesso.

## Goals — Features (faz)

- AppShellV2 layout
- Header `Editar plano · {nome}` + breadcrumb Voltar
- Form idêntico ao Create.tsx (componente de form reutilizável seria ideal — v1 duplica conscientemente, refactor pode vir em Onda 9 quando padrão estiver provado)
- Pré-preenchido via prop `plan`
- `useForm()` Inertia (`form.put`)
- Validation errors inline
- Submit redirect Index com flash
- Botões: Salvar / Cancelar

## Non-Goals

- ❌ Histórico de mudanças (Activitylog grava, Page não mostra) — Onda futura
- ❌ Compare-diff campos alterados — Onda futura
- ❌ Audit log inline na lateral — Onda futura

## UX Targets

- p95 first-paint < 800ms
- Mudança de slug avisa risco (slug é parte da URL pública futura)
- Required fields marcados com `*`

## UX Anti-patterns

- ❌ Mudar `id` (PK) via form — apenas mutáveis editáveis
- ❌ Permitir editar plano deletado (soft delete) — Controller usa findOrFail que ignora trashed

## Endpoints

| Método | Rota | Retorna |
|---|---|---|
| GET | `/recurring-billing/planos/{id}/editar` | Inertia render `Planos/Edit` props `{plan}` |
| PUT | `/recurring-billing/planos/{id}` | redirect Index + flash success ou 422 |
