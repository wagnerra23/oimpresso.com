---
page: /modulos
component: resources/js/Pages/Modules/Index.tsx
related_prototype: n/a (herda PT-01 Lista; segue o Padrão de Tela)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: Superadmin
related_adrs: [114, 101, 93]
tier: B
charter_version: 1
---

# Page Charter — /modulos (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `app/Http/Controllers/ModuleManagementController` (gerenciamento de módulos app-wide). Espelha `Pages/Cliente/Index.tsx` (tabela + FilterDropdown + StatusBadge + ActionsMenu).
>
> ⚠️ **Rota cross-tenant intencional** — `/modulos` gerencia módulos app-wide, NÃO por-business. Sem `business_id` scope aqui (ADR 0093 §exceções superadmin). É a exceção documentada, não drift.

---

## Mission

Listagem dos módulos nWidart instalados no app (gerenciamento superadmin app-wide): habilitados/desabilitados, com filtro e ações. Dá a visão única de quais módulos existem e seu estado, sem abrir `/manage-modules` cru nem a CLI. Escopo é global (cross-tenant), não per-business.

---

## Goals — Features (faz)

- Tabela dos módulos com `StatusBadge` (habilitado/desabilitado) e metadados
- Filtro (FilterDropdown) e menu de ações por linha (ActionsMenu)
- AppShellV2 + PageHeader shared, tokens DS

---

## Non-Goals — Features (NÃO faz)

- ❌ NÃO habilita/desabilita módulo POR BUSINESS aqui — isso é compra de pacote no superadmin (Camada 1, `/superadmin/packages/{id}/edit`)
- ❌ NÃO aplica `business_id` scope (rota cross-tenant intencional — gerenciamento app-wide)
- ❌ NÃO é acessível por usuário comum de business (superadmin)
- ❌ NÃO instala/remove código de módulo (só reflete estado; install vive nas rotas do módulo)

---

## UX targets

- p95 < 1500ms (tela admin)
- Cabe em 1280px
- Estado de cada módulo legível por badge (tokens), não texto solto

---

## Automation hooks (faz)

- Reflete o estado nWidart dos módulos (fonte: ModuleManagementController)

---

## Anti-hooks (NÃO faz automaticamente)

- ❌ NÃO ativa módulo pra business sem passar pelo fluxo de pacote superadmin
- ❌ NÃO grava nada em GET
- ❌ NÃO dispara deploy/migration ao alternar estado

---

## Pendências antes de `status: live`

- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Confirmar exatamente quais ações o ActionsMenu expõe (e RBAC)
- [ ] Smoke visual 1280/1440 (screenshot)
