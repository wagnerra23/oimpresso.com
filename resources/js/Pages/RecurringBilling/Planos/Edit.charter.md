---
page: /recurring-billing/planos/{id}/editar
component: resources/js/Pages/RecurringBilling/Planos/Edit.tsx
related_us: [US-RB-001]
owner: wagner
status: live
last_validated: "2026-05-17"
parent_module: RecurringBilling
related_adrs: [93, 94, 101, 104, 107, 110]
tier: A
charter_version: 1
sidebar_group: fin (FINANCEIRO)
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
