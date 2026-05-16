---
page: /contacts/create
component: resources/js/Pages/Cliente/Create.tsx
owner: wagner
status: draft
last_validated: 2026-05-15
parent_module: Cliente
related_adrs: [0110, 0107, 0093, 0094, 0104, 0149]
tier: A
charter_version: 1
mwart_pattern_reuse:
  blueprint_cowork: "prototipo-ui/prototipos/clientes/"
  blueprint_screenshot_approval: "SYNC_LOG (pendente)"
  derived_screens: [Create]
  divergence_from_blueprint: "none"
---

# Page Charter — /contacts/create (DRAFT)

> **Status:** draft criado em batch W1-B3 2026-05-15. Backend canon: `app/Http/Controllers/ContactController.php::create()`. Pattern reuse blueprint Cowork Index — formulário derivado da mesma família visual.

## Mission

Formulário de cadastro de novo cliente/fornecedor — substitui Blade `contact.create.blade.php` com layout limpo single-column 3xl, seções colapsáveis lógicas e validação Inertia useForm.

## Goals — Features (faz)

- AppShellV2 + breadcrumb voltar pra /contacts/customer
- 4 seções: Identificação · Contato · Endereço · Financeiro
- Validação client-side básica (required) + display de errors server-side via useForm.errors
- Pre-fill via query `?prefill_name=` (vindo do CustomerSearchAutocomplete em Sells/Create)
- Radio person/business em UI canon (radio nativo, não fancy)
- Dropdown customer_group_id (eager-load — apenas N customer_groups, leve)
- Submit via Inertia POST `/contacts` — backend valida e retorna redirect ou errors
- PT-BR labels obrigatório

## Non-Goals — Features (NÃO faz)

- ❌ Upload de foto/avatar (UPOS não suporta nativo)
- ❌ Validação CNPJ via Receita Federal (vai pra cron separado, não inline)
- ❌ Wizard multi-step (form single-page basta)
- ❌ Lookup CEP automático ViaCEP (futuro; nice-to-have)

## UX Targets

- p95 first-paint < 800ms
- Submit < 1500ms p95
- Cabe em 1280px sem scroll horizontal

## Automation Anti-hooks

- ❌ Não dispara WhatsApp/email ao cadastrar
- ❌ Não cria usuário login automaticamente
- ❌ Não envia pra Receita Federal validar CNPJ inline

## Refs

- Blueprint: `prototipo-ui/prototipos/clientes/cowork-app.jsx`
- Backend: `ContactController::create()` linha 536
- Pattern reuse: ADR 0149
