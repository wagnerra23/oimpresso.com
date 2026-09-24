---
id: resources-js-pages-essentials-settings-index-charter
page: /hrm/settings
component: resources/js/Pages/Essentials/Settings/Index.tsx
related_prototype: n/a (herda PT-02 Formulário; segue o Padrão de Tela)
bundle_source: hrm-page.jsx
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: Essentials
related_us: [US-ESS-014]
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
Permitir que o admin do business configure prefixos de referência (tarefas, folha, afastamentos), o texto de instruções de afastamento e o comportamento de calcular meta de vendas sem impostos. Tudo salvo no JSON `essentials_settings` do business.

---

## Goals — Features (faz)
- Formulário em cards agrupados: Prefixos, Instruções de afastamento, Comportamentos (1 switch).
- Campos: `essentials_todos_prefix`, `leave_ref_no_prefix`, `payroll_ref_no_prefix`, `leave_instructions`, `calculate_sales_target_commission_without_tax`.
- Submete via `POST /hrm/settings` (`useForm`), com toasts de sucesso/erro.
- Prefixo de tarefas alimenta o `task_id` gerado em `ToDoController@store`.

---

## Non-Goals — Features (NÃO faz)
- ❌ NÃO altera settings de outro business — `authorizeAdmin($businessId)` e persistência em `Business::findOrFail($businessId)` (multi-tenant Tier 0).
- ❌ NÃO é acessível a não-admin — apenas admin do business vê/edita.
- ❌ NÃO configura módulos/pacotes por business (isso é superadmin; ver ADR 0093 §3 camadas) — inferência pendente de Wagner.
- ❌ NÃO configura tolerância de marcação nem exigência de localização ([W] 2026-09-24, ADR 0014 emenda). As 5 chaves `grace_before/after_checkin/checkout` e `is_location_required` foram aposentadas: a jornada é do Ponto, onde a lei já fixa as duas coisas (Art. 58 §1º CLT; REP-P com geolocalização obrigatória, Portaria 671). Um save desta tela não grava mais essas chaves.

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
