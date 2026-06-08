---
slug: cliente-edit-visual-comparison
title: "Cliente — Comparativo visual /contacts/{id}/edit (Blade legacy → Inertia React + bloco BR)"
type: visual-comparison
module: Cliente
status: approved
approved_by: wagner
date: 2026-05-21
canon_reference: resources/views/contact/edit.blade.php (Blade legacy — sem campos BR)
inertia_target: resources/js/Pages/Cliente/Edit.tsx (charter draft)
controller: app/Http/Controllers/ContactController.php::edit($id) linha ~768
stories: [US-CRM-072, US-CRM-073, US-CRM-074, US-CRM-076]
related_adrs: [0093, 0094, 0104, 0107, 0110, 0149]
---

# Visual Comparison — Cliente/Edit (`/contacts/{id}/edit`)

> **Tipo de tela:** formulário single-page de edição (pré-preenchido)
> **Persona:** Larissa @ ROTA LIVRE corrigindo dados de cadastro existente. Manutenção rápida < 30s.
> **Referência:** mesmo blueprint do Create (Edit reusa 100% das seções)

## Contexto

Edit reusa quase totalmente o layout/seções de Create. Diferenças: campos pré-preenchidos via `useForm` initial values, `type` (customer/supplier) imutável, `opening_balance` ajustado por pagamentos prévios. Backfill `cpf_cnpj` (PR #1319) garante que clientes legacy com `tax_number_1` populado têm o campo BR canon disponível pra edição.

## Matriz consolidada

| Item | Blade legacy | React (pós Slices 2+3+4) | Peso | Score |
|---|:-:|:-:|:-:|:-:|
| Pré-preenchimento CPF/CNPJ | tax_number plain | cpf_cnpj com máscara aplicada | 5 | 10/10 |
| Backfill legacy (tax_number_1 → cpf_cnpj) | manual SQL | `php artisan contacts:backfill-cpf-cnpj` (PR #1319) | 4 | 10/10 |
| type customer/supplier imutável | editável (risco data) | disabled radio + tooltip explicativo | 3 | 10/10 |
| opening_balance ajustado | mostra valor original | calculado via `TransactionUtil::getTotalAmountPaid` | 4 | 10/10 |
| Bloco BR pré-preenchido | ausente | 100% pré-preenchido se persistido | 5 | 10/10 |
| Confirm cancel se dirty | ausente | `confirm()` se `form.isDirty` | 2 | 9/10 |

## 8 dimensões avaliadas

### 1. Layout

Idêntico a Create: `max-w-3xl` single-column, 4 seções (Identificação · Contato · Endereço · Financeiro) + Bloco Fiscal BR. Reuso 100% via composição de `_form/DadosFiscaisBRSection.tsx` + `_form/EnderecoBRSection.tsx`.

**Decisão MWART:** ✅ reuso aprovado — single source of truth pro formulário.

### 2. Hierarquia visual

- h1 "Editar cliente: {NOME}" (com nome inline pra contexto)
- Breadcrumb: Clientes → {NOME} → Editar (volta pro Show)
- Mesma tipografia canon Cockpit V2 (ADR 0110)

### 3. Densidade informacional

Idêntico a Create. Bloco BR vem pré-preenchido (Slice 3 PR #1316) — Larissa visualiza o que já existe antes de editar.

### 4. Multi-tenant Tier 0

- `Contact::where('business_id', session('business.id'))->findOrFail($id)` no `edit()` Controller
- Cross-tenant retorna 404 (anti-enumeração)
- `update()` re-verifica ownership ANTES de persistir
- ADR 0093 obrigatório

**Decisão MWART:** ✅ aprovado (duplo check no edit + update).

### 5. Permissões

- `customer.update` necessária
- Sem permission: 403
- `type` field disabled mesmo com permission (data integrity)

### 6. Acessibilidade & Mobile

- Mesmas regras de Create
- `aria-readonly="true"` no radio `type` (customer/supplier)
- Tooltip explicativo: "Tipo não pode ser alterado após cadastro"

### 7. PII / LGPD handling

- Campos vêm já mascarados do backend? **NÃO** — Edit precisa do valor real pra Larissa ver e ajustar
- Mas: backend só envia plain text se `Auth::user()->can('customer.update')` (permission gate)
- Display do CPF/CNPJ NO FORM pode ser plain (Larissa pode ler/copiar)
- Activity log entry registra mudança SEM logar o valor cru de `cpf_cnpj` (apenas "cpf_cnpj alterado")

**Decisão MWART:** ✅ aprovado (display plain só com permission, log mascarado).

### 8. Estados (loading / empty / error / success)

| Estado | UI |
|---|---|
| Idle dirty=false | Submit disabled (cinza stone) |
| Idle dirty=true | Submit habilitado (sky bg) |
| Validando | Spinner inline |
| Erro inline | Mensagem rose abaixo do campo |
| Sucesso | Redirect Show `/cliente/{id}` + flash |
| Cancel dirty | `confirm("Descartar alterações?")` |

## Score consolidado

| Dimensão | Score | Peso | Contribuição |
|---|:-:|:-:|:-:|
| Layout | 10 | 15% | 1.50 |
| Hierarquia | 9 | 10% | 0.90 |
| Densidade | 9 | 10% | 0.90 |
| Multi-tenant Tier 0 | 10 | 20% | 2.00 |
| Permissões | 9 | 10% | 0.90 |
| Acessibilidade | 8 | 10% | 0.80 |
| PII/LGPD | 9 | 15% | 1.35 |
| Estados | 9 | 10% | 0.90 |

### **Nota final: 92.5 / 100** (sólido aprovado)

## Gaps remanescentes (backlog)

| US futura | Gap | Prioridade |
|---|---|---|
| US-CRM-076 | FormRequest `UpdateContactRequest` wirando Rule\BR\CpfCnpj | P0 |
| (futura) | Histórico de alterações inline (audit timeline) | P2 |
| (futura) | Bulk edit múltiplos clientes | P3 |

## Refs

- HANDOFF: `prototipo-ui/prototipos/clientes/HANDOFF_CLIENTES.md`
- ADR 0107 (gate F1.5)
- ADR 0110 (Cockpit V2)
- ADR 0149 (pattern reuse)
- Charter: [`resources/js/Pages/Cliente/Edit.charter.md`](../../../resources/js/Pages/Cliente/Edit.charter.md)
- PR #1316 — Slices 2+3 UI Create/Edit
- PR #1319 — Slice 4 backfill cpf_cnpj
