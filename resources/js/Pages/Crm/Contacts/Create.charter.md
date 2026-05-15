---
page: /contacts/create
component: resources/js/Pages/Crm/Contacts/Create.tsx
owner: wagner
status: draft
last_validated: 2026-05-14
parent_module: Crm
related_adrs: [0110, 0107, 0104, 0093, 0141]
tier: A
charter_version: 1
canary_clients: [martinho-caçambas-biz-164]
---

# Page Charter — /contacts/create (Crm/Contacts/Create)

> **Status:** draft (US-CRM-CONT-002). Migração Blade legacy `contact/create.blade.php` (modal) + `contact/create-page.blade.php` → Inertia React form rápido.

---

## Mission

Cadastrar cliente novo em **< 5 segundos** — clique "Novo" → form aberto → 3 campos obrigatórios (tipo + nome + telefone) → Salvar → redireciona pra `/contacts/{id}`. Demais campos (endereço, fiscal, observações) em sections colapsáveis. Pain-point #1 reunião Martinho: velocidade.

---

## Goals — Features (faz)

- AppShellV2 persistent layout
- Header inline: h1 "Novo cliente" + breadcrumb "Clientes / Novo cliente"
- Form com 3 sections:
  - **1. Identificação (sempre aberta)** — Tipo (radio: cliente/fornecedor/ambos) + Nome/Razão social + Telefone obrigatório + Email opcional
  - **2. CPF/CNPJ + endereço (collapsible)** — CPF/CNPJ com mask + validação dígito + endereço completo (logradouro/cidade/UF/CEP)
  - **3. Avançado (collapsible)** — pay_term, customer_group, observações, custom_fields, opening_balance
- CPF/CNPJ mask automática (11 dígitos = CPF, 14 = CNPJ) + validação dígito verificador
- Submit AJAX (não recarrega) → POST `/contacts` (existente) → response success: redireciona `/contacts/{id}` (Show)
- Submit error → mostra erros inline (validação Laravel) + toast `Erro ao salvar`
- Atalho `Cmd/Ctrl+S` salva (UX power user — champion Wagner usa)
- Atalho `Esc` cancela + redireciona `/contacts?type=customer`
- Pre-fill `prefill_name` via query param (PR #694 preservado — vem de Sells/Create autocomplete)
- Pre-fill `type` via query param (`?type=supplier` para fluxo de Compras)
- Permissão `customer.create`/`supplier.create` validada server-side (existente)

---

## Non-Goals — Features (NÃO faz)

- ❌ Upload foto/anexo (mantém Blade `contact/edit.blade.php` para futuras)
- ❌ Shipping address múltiplo (não no MVP)
- ❌ Reward points input (preservado backend, hidden form V1)
- ❌ Export custom fields 1-6 (não no MVP, mantém via Edit Blade)
- ❌ Customer group auto-create (apenas dropdown existente)
- ❌ Salvar e adicionar outro (botão duplo) — backlog
- ❌ Upload em massa (CSV/Excel) — mantém Blade `/contacts/import`

---

## UX Targets

- **p95 form rendered < 1000ms** após click "Novo cliente" (pain-point #1)
- **p95 submit success → redirect < 2000ms** (POST + commit DB + redirect)
- 0 erros JS console em smoke biz=1
- Cabe em monitor 1280px sem scroll horizontal
- Cabe em monitor 1280px com scroll vertical mínimo (3 sections collapsíveis ajuda)
- Foco automático em "Nome" ao abrir (atalho de teclado essencial)
- Validação inline (não submit pra ver erro de CPF inválido)
- Botão "Salvar" sticky bottom se scroll > 50% (canary monitora low-res)

---

## UX Anti-patterns

- ❌ Submit full-form com 30 campos visíveis ao mesmo tempo (atual Blade)
- ❌ Select2 jQuery (canon = `<Select>` shadcn nativo)
- ❌ Date picker custom (canon = `<Input type=date>` HTML5 nativo + format BR via JS)
- ❌ Modal `contact_quick_edit` reusado (canon = full page Create)
- ❌ CSRF inline em `<input type=hidden>` (canon = meta `csrf-token` header via fetch)
- ❌ `format_date(now())` em campo `created_at` display (shift +3h legacy)
- ❌ Console.log dados do form com CPF (LGPD)

---

## Endpoints alimentadores

| Método | Rota | Comportamento |
|---|---|---|
| GET | `/contacts/create?type=customer&prefill_name=Foo` (X-Inertia) | Inertia render Crm/Contacts/Create com `{type, types[], permissions, prefill_name}` |
| GET | `/contacts/create?type=customer` (sem X-Inertia) | Blade legacy `contact.create` (preservado canary) |
| POST | `/contacts` | JSON `{success: true, data: {id, ...}, msg}` ou `{success: false, msg}` |

---

## Tests anti-regressão

- [tests/Feature/Crm/ContactsInertiaTest.php](../../../../tests/Feature/Crm/ContactsInertiaTest.php) — render Create + store success + business_id scope + prefill_name preserved
- [tests/Feature/Contact/ContactCreatePrefillNameTest.php](../../../../tests/Feature/Contact/ContactCreatePrefillNameTest.php) — pre-existente, não regressar

---

## Refs

- [RUNBOOK-contacts.md](../../../../memory/requisitos/Crm/RUNBOOK-contacts.md)
- [ADR 0110 Cockpit Pattern V2](../../../../memory/decisions/0110-cockpit-pattern-v2-canon-list-detail.md)
- [Index.charter.md](./Index.charter.md)
- Padrão referência: [Pages/Sells/Create.tsx](../../Sells/Create.tsx)
