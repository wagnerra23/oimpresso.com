---
page: /hrm/settings
component: resources/js/Pages/Essentials/Settings/Index.tsx
related_prototype: n/a (herda PT-02 Formulário; segue o Padrão de Tela)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: Essentials
related_adrs: [114, 101, 93]
tier: B
charter_version: 1
---

# Page Charter — /hrm/settings (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/Essentials/Http/Controllers/EssentialsSettingsController@edit` + `@update` (rotas `GET/POST /hrm/settings`). Formulário admin de configurações do módulo Essentials, persistidas em `businesses.essentials_settings` (JSON).

---

## Mission
Permitir que o admin do business configure prefixos de referência (tarefas, folha, afastamentos), o texto de instruções de afastamento, as janelas de tolerância do ponto (grace periods de entrada/saída) e dois comportamentos (exigir localidade no ponto; calcular meta de vendas sem impostos). Tudo salvo no JSON `essentials_settings` do business.

---

## Goals — Features (faz)
- Formulário em cards agrupados: Prefixos, Instruções de afastamento, Tolerâncias de ponto (4 campos em minutos), Comportamentos (2 switches).
- Campos: `essentials_todos_prefix`, `leave_ref_no_prefix`, `payroll_ref_no_prefix`, `leave_instructions`, `grace_before/after_checkin/checkout`, `is_location_required`, `calculate_sales_target_commission_without_tax`.
- Submete via `POST /hrm/settings` (`useForm`), com toasts de sucesso/erro.
- Prefixo de tarefas alimenta o `task_id` gerado em `ToDoController@store`.

---

## Non-Goals — Features (NÃO faz)
- ❌ NÃO altera settings de outro business — `authorizeAdmin($businessId)` e persistência em `Business::findOrFail($businessId)` (multi-tenant Tier 0).
- ❌ NÃO é acessível a não-admin — apenas admin do business vê/edita.
- ❌ NÃO configura módulos/pacotes por business (isso é superadmin; ver ADR 0093 §3 camadas) — inferência pendente de Wagner.
- ❌ NÃO valida coerência das tolerâncias entre si (só limites de string).

---

## UX targets
- p95 < 1500ms (admin) / < 800ms (produção) ; cabe em 1280px (ROTA LIVRE) ; AppShellV2 quando aplicável

---

## Automation hooks (faz)
- Após salvar, backend reflete o `Business` atualizado na sessão para que `ToDoController` e afins leiam sem refetch.

---

## Anti-hooks (NÃO faz automaticamente)
- ❌ NÃO reprocessa `task_id` de tarefas existentes ao mudar o prefixo (só afeta novas).
- ❌ NÃO recalcula comissões passadas ao alternar "meta sem impostos".
- ❌ NÃO salva automaticamente — exige submit explícito.

---

## Pendências antes de `status: live`
- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Smoke visual 1280/1440 (screenshot)
- [ ] Confirmar breadcrumb/rota (tela vive sob `/hrm/settings` mas é do módulo Essentials)
